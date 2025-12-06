<?php
namespace modules\formiemautic\integrations;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;
use verbb\formie\base\EmailMarketing;
use verbb\formie\base\Integration;
use verbb\formie\elements\Submission;
use verbb\formie\models\IntegrationFormSettings;
use verbb\formie\models\IntegrationField;
use Throwable;

class FormieMauticIntegration extends EmailMarketing
{
    public ?string $baseUrl = null;
    public ?string $segmentId = null;
    public ?string $optInFieldHandle = null;
    public ?string $publicKey = null;
    public ?string $privateKey = null;
    
    private ?string $_accessToken = null;
    private ?int $_tokenExpiry = null;
    protected ?Client $_client = null;

    public static function displayName(): string
    {
        return Craft::t('formie', 'Mautic Integration');
    }

    // Enable list support - we'll use segments as lists
    public static function supportsLists(): bool
    {
        return true;
    }

    // Settings template
    public function getSettingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie-mautic/settings', [
            'integration' => $this,
        ]);
    }

    // Form-specific settings template
    public function getFormSettingsHtml($form): string
    {
        return Craft::$app->getView()->renderTemplate('formie-mautic/form-settings', [
            'integration' => $this,
            'form' => $form,
        ]);
    }

    
    // Get OAuth2 access token (cached)
    private function getMauticAccessToken(): string
    {
        // Return cached token if still valid
        if ($this->_accessToken && $this->_tokenExpiry && time() < $this->_tokenExpiry) {
            return $this->_accessToken;
        }
        
        $baseUrl = rtrim(App::parseEnv($this->baseUrl) ?? '', '/');
        $publicKey = App::parseEnv($this->publicKey) ?? '';
        $privateKey = App::parseEnv($this->privateKey) ?? '';
        
        if (empty($publicKey) || empty($privateKey)) {
            throw new \Exception('Mautic Public Key and Private Key are required');
        }
        
        Craft::info('Fetching OAuth2 access token', __METHOD__);
        
        $tokenClient = Craft::createGuzzleClient(['timeout' => 30]);
        
        $response = $tokenClient->post($baseUrl . '/oauth/v2/token', [
            'form_params' => [
                'client_id' => $publicKey,
                'client_secret' => $privateKey,
                'grant_type' => 'client_credentials',
            ],
        ]);
        
        $data = json_decode((string)$response->getBody(), true);
        
        if (!isset($data['access_token'])) {
            throw new \Exception('No access token in OAuth2 response');
        }
        
        $this->_accessToken = $data['access_token'];
        // Cache for 55 minutes (tokens usually expire in 1 hour)
        $this->_tokenExpiry = time() + 3300;
        
        return $this->_accessToken;
    }
    
    
    // Get HTTP client with OAuth2 token
    public function getClient(): Client
    {
        if ($this->_client) {
            return $this->_client;
        }

        $baseUrl = rtrim(App::parseEnv($this->baseUrl) ?? '', '/');

        if (empty($baseUrl)) {
            throw new \Exception('Mautic Base URL is required');
        }

        // Get OAuth2 token (will be cached)
        $token = $this->getMauticAccessToken();
        
        Craft::info("Creating Mautic OAuth2 client for: {$baseUrl}", __METHOD__);

        $this->_client = Craft::createGuzzleClient([
            'base_uri' => $baseUrl . '/api/',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
            'verify' => true,
        ]);

        return $this->_client;
    }

    // Make API request
    public function request(string $method, string $uri, array $options = []): mixed
    {
        try {
            Craft::info("Mautic: About to get client for API request", __METHOD__);
            $client = $this->getClient();
            
            Craft::info("Mautic: API Request: {$method} {$uri}", __METHOD__);
            
            $response = $client->request($method, $uri, $options);
            $body = (string)$response->getBody();
            $statusCode = $response->getStatusCode();
            
            Craft::info("Mautic: API Response [{$statusCode}]: " . substr($body, 0, 500), __METHOD__);
            
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }
            
            return $data;
        } catch (Throwable $e) {
            $errorMsg = "Mautic: API Error ({$method} {$uri}): {$e->getMessage()}";
            Craft::error($errorMsg, __METHOD__);
            Craft::error("Mautic: Exception class: " . get_class($e), __METHOD__);
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $responseBody = (string)$e->getResponse()->getBody();
                Craft::error("Mautic: Error response body: " . $responseBody, __METHOD__);
            }
            Integration::error($this, Craft::t('formie', 'API request failed: {message}', [
                'message' => $e->getMessage(),
            ]));
            return false;
        }
    }

    // Test connection
    public function fetchConnection(): bool
    {
        try {
            Craft::info('Mautic: === Starting Connection Test ===', __METHOD__);
            
            // Step 1: Validate configuration
            $baseUrl = rtrim(App::parseEnv($this->baseUrl) ?? '', '/');
            $publicKey = App::parseEnv($this->publicKey) ?? '';
            $privateKey = App::parseEnv($this->privateKey) ?? '';
            
            if (empty($baseUrl)) {
                Integration::error($this, 'Base URL is not configured');
                return false;
            }
            
            if (empty($publicKey) || empty($privateKey)) {
                Integration::error($this, 'Public Key or Private Key is not configured');
                return false;
            }
            
            Craft::info("Mautic: Step 1: Configuration validated - Base URL: {$baseUrl}", __METHOD__);
            Craft::info("Mautic: Public Key: " . substr($publicKey, 0, 20) . "...", __METHOD__);
            
            // Step 2: Test OAuth2 token endpoint directly (like CLI test)
            Craft::info('Mautic: Step 2: Testing OAuth2 token endpoint', __METHOD__);
            try {
                $tokenClient = Craft::createGuzzleClient(['timeout' => 30]);
                
                $tokenResponse = $tokenClient->post($baseUrl . '/oauth/v2/token', [
                    'form_params' => [
                        'client_id' => $publicKey,
                        'client_secret' => $privateKey,
                        'grant_type' => 'client_credentials',
                    ],
                ]);
                
                $tokenData = json_decode((string)$tokenResponse->getBody(), true);
                
                if (!isset($tokenData['access_token'])) {
                    Craft::error('Mautic: Step 2 FAILED: No access_token in response - ' . print_r($tokenData, true), __METHOD__);
                    Integration::error($this, 'OAuth2 response missing access_token');
                    return false;
                }
                
                $token = $tokenData['access_token'];
                Craft::info('Mautic: Step 2: SUCCESS - OAuth2 token obtained', __METHOD__);
                Craft::info('Mautic: Token preview: ' . substr($token, 0, 20) . '...', __METHOD__);
                
            } catch (Throwable $e) {
                Craft::error('Mautic: Step 2 FAILED: OAuth2 request exception - ' . $e->getMessage(), __METHOD__);
                Integration::error($this, 'Failed to get OAuth2 access token: ' . $e->getMessage());
                return false;
            }
            
            // Step 3: Make test API request using the token
            Craft::info('Mautic: Step 3: Testing API connection with GET /segments', __METHOD__);
            $response = $this->request('GET', 'segments');

            // Step 4: Validate response
            if ($response === false) {
                Craft::error('Mautic: Step 4 FAILED: API request returned false', __METHOD__);
                Integration::error($this, 'API request failed - check logs for details');
                return false;
            }
            
            if (isset($response['errors'])) {
                $errorMsg = isset($response['errors'][0]['message']) ? $response['errors'][0]['message'] : 'Unknown error';
                Craft::error('Mautic: Step 4 FAILED: API returned errors - ' . $errorMsg, __METHOD__);
                Integration::error($this, 'Mautic API Error: ' . $errorMsg);
                return false;
            }
            
            // Step 5: Check for valid response structure
            if (!isset($response['total']) && !isset($response['lists'])) {
                Craft::error('Mautic: Step 4 FAILED: Unexpected response structure - ' . print_r($response, true), __METHOD__);
                Integration::error($this, 'Unexpected API response structure');
                return false;
            }
            
            Craft::info('Mautic: Step 4: SUCCESS - Valid response received', __METHOD__);
            Craft::info('Mautic: === Connection Test Complete - All checks passed ===', __METHOD__);
            
            return true;
            
        } catch (Throwable $e) {
            Craft::error('Mautic: Connection test exception: ' . $e->getMessage(), __METHOD__);
            Craft::error('Mautic: Stack trace: ' . $e->getTraceAsString(), __METHOD__);
            Integration::error($this, 'Connection failed: ' . $e->getMessage());
            return false;
        }
    }

    // Fetch available contact fields from Mautic
    public function fetchFormSettings(): IntegrationFormSettings
    {
        Craft::info('Mautic: === Starting fetchFormSettings ===', __METHOD__);
        $settings = [];

        try {
            // Fetch segments to use as lists
            Craft::info('Mautic: Fetching segments...', __METHOD__);
            $segmentsResponse = $this->request('GET', 'segments');
            $lists = [];
            
            if (is_array($segmentsResponse) && isset($segmentsResponse['lists'])) {
                Craft::info('Mautic: Found ' . count($segmentsResponse['lists']) . ' segments', __METHOD__);
                foreach ($segmentsResponse['lists'] as $segment) {
                    $lists[] = [
                        'id' => (string)$segment['id'],
                        'name' => $segment['name'],
                    ];
                }
            }
            
            $settings['lists'] = $lists;
            Craft::info('Mautic: Added ' . count($lists) . ' segments as lists', __METHOD__);

            // Fetch all contact fields from Mautic
            Craft::info('Mautic: Fetching all contact fields from Mautic...', __METHOD__);
            $response = $this->request('GET', 'fields/contact');
            
            $fields = [];
            
            if (is_array($response) && isset($response['fields'])) {
                Craft::info('Mautic: Found ' . count($response['fields']) . ' fields', __METHOD__);
                foreach ($response['fields'] as $field) {
                    $handle = $field['alias'] ?? '';
                    $isRequired = isset($field['isRequired']) && $field['isRequired'];
                    
                    // Mark email as required if it's not already
                    if ($handle === 'email') {
                        $isRequired = true;
                    }
                    
                    $fields[] = new IntegrationField([
                        'handle' => $handle,
                        'name' => $field['label'] ?? '',
                        'required' => $isRequired,
                    ]);
                }
            } else {
                Craft::warning('Mautic: No fields key found in API response', __METHOD__);
                Craft::info('Mautic: Fields response: ' . print_r($response, true), __METHOD__);
            }

            Craft::info('Mautic: Total fields loaded: ' . count($fields), __METHOD__);
            $settings['fields'] = $fields;
            
            Craft::info('Mautic: === fetchFormSettings complete ===', __METHOD__);
        } catch (Throwable $e) {
            Craft::error('Mautic: fetchFormSettings exception: ' . $e->getMessage(), __METHOD__);
            Integration::error($this, Craft::t('formie', 'Unable to fetch fields: {message}', [
                'message' => $e->getMessage(),
            ]));
        }

        return new IntegrationFormSettings($settings);
    }



    // Send submission to Mautic
    public function sendPayload(Submission $submission): bool
    {
        try {
            // Check if opt-in checkbox is enabled (per-form setting)
            $formSettings = $submission->form->settings['integrations'][$this->handle] ?? [];
            $optInField = $formSettings['optInField'] ?? null;
            
            if ($optInField) {
                $field = $submission->getFieldByHandle($optInField);
                
                if (!$field) {
                    Craft::info('Mautic: Opt-in field was specified, but not found. Check the form settings. Skipping submission', __METHOD__);
                    return true; // Not an error, just skipped
                }
                
                // Get the serialized value for Agree field
                $fieldValue = $submission->getFieldValue($optInField);
                Craft::info('Mautic: Opt-in field value: ' . var_export($fieldValue, true), __METHOD__);
                
                // Check if the Agree field is checked
                if (!$fieldValue || $fieldValue === '0' || $fieldValue === 0 || $fieldValue === false || $fieldValue === "no" || $fieldValue === "No") {
                    Craft::info('Mautic: Opt-in not checked, skipping submission', __METHOD__);
                    return true; // Not an error, just skipped
                }
                
                Craft::info('Mautic: Opt-in is checked, proceeding with submission', __METHOD__);
            }

            // Get field mappings
            $fieldValues = $this->getFieldMappingValues($submission, $this->fieldMapping);

            // Build contact payload
            $payload = [];
            foreach ($fieldValues as $key => $value) {
                if ($value !== null && $value !== '') {
                    $payload[$key] = $value;
                }
            }

            // Create or update contact
            Craft::info('Mautic: Creating contact with payload: ' . print_r($payload, true), __METHOD__);
            $response = $this->request('POST', 'contacts/new', [
                'json' => $payload,
            ]);

            if ($response === false || !isset($response['contact'])) {
                Craft::error('Mautic: Failed to create contact', __METHOD__);
                return false;
            }

            $contactId = $response['contact']['id'];
            Craft::info("Mautic: Contact created with ID: {$contactId}", __METHOD__);

            // Add to segment from per-form listId (selected segment)
            $formSettings = $submission->form->settings['integrations'][$this->handle] ?? [];
            $listId = $formSettings['listId'] ?? null;
            
            if ($listId) {
                Craft::info("Mautic: Adding contact to segment: {$listId}", __METHOD__);
                $this->request('POST', "segments/{$listId}/contact/{$contactId}/add");
            }

            Craft::info("Mautic: Contact added with ID: {$contactId}", __METHOD__);
            
            return true;
        } catch (Throwable $e) {
            Integration::error($this, Craft::t('formie', 'Failed to send to Mautic: {message}', [
                'message' => $e->getMessage(),
            ]));
            return false;
        }
    }

}