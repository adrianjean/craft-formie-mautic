<?php
namespace modules\formiemautic;

use Craft;
use yii\base\Module;
use yii\base\Event;
use craft\web\View;
use craft\events\RegisterTemplateRootsEvent;
use verbb\formie\services\Integrations;
use verbb\formie\events\RegisterIntegrationsEvent;
use modules\formiemautic\integrations\FormieMauticIntegration;

class FormieMauticModule extends Module
{
    public function init(): void
    {
        parent::init();
        
        // Define a custom alias using the module ID
        Craft::setAlias('@formie-mautic', __DIR__);
        
        // Register template roots
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $e) {
            $e->roots[$this->id] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
        });

        // Register the integration
        Event::on(
            Integrations::class, 
            Integrations::EVENT_REGISTER_INTEGRATIONS, 
            function(RegisterIntegrationsEvent $event) {
                $event->emailMarketing[] = FormieMauticIntegration::class;
            }
        );
    }
}