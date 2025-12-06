# CraftCMS Formie Integration for Mautic
A [CraftCMS](https://craftcms.com/) [Verbb Formie](https://verbb.io/craft-plugins/formie/features) integration module for the [Mautic](https://mautic.org/) email marketing Platform. Tested with **Mautic 6.x and 7.x**. It allows Formie form submissions to automatically create or update contacts in Mautic, map custom fields, apply tags/segments, and handle opt-in consent. PRs and suggestions are welcome!

## Requirements

| System     | Version       |
|------------|----------------|
| CraftCMS   | 5.x           |
| Formie     | 3.x           |
| Mautic     | 6.x or 7.x API |
| PHP        | 8.1+          |


## Mautic Setup

Ideally you have a Segment created with a Campaign that can ingest the contact and process it. If not, the contact will simply be added to Mautic. 

## Installation

At this time the module is distributed via **GitHub only**, but can be installed with Composer using the repository URL.

**1. Install the module with composer**

```bash
composer require adrianjean/craft-formie-mautic
```

**2. Enable the Module in CraftCMS**

Add to `config/app.php`:

```php
'modules' => [
    'formie-mautic' => [
        'class' => \modules\formiemautic\FormieMauticModule::class,
    ],
],
'bootstrap' => ['formie-mautic'],
```

## Configuration

1. Generate the private and public keys in Mautic: **Mautic → Settings → Configuration → API Credentials**
2. Configure Formie: **Formie → Settings → Integrations**, add a new **Mautic Integration**.
3. Add the required credentials:

- **Base URL** — this is the URL to your Mautic 6.x or 7.x install.
- **Public Key** — generated in step 1.
- **Private Key** — generated in step 1.

You can use environment variables in your CraftCMS `.env` file like the following, or use your own:

```
$MAUTIC_BASE_URL
$MAUTIC_PUBLIC_KEY
$MAUTIC_PRIVATE_KEY
```

## Usage

1. Create a Formie form  
2. Add your fields + optional opt-in consent  
3. Go to **Form → Integrations**  
4. Add the Mautic Integation you created
5. Click the refresh arrows, and then map your fields to Mautic fields 
6. Choose a Mautic Segment to assign contacts to  
7. Submit your form and verify contact creation/update in Mautic  

## Features

- Map Formie fields to Mautic custom fields  
- Opt-in / consent checkbox  
- Automatically assign a Mautic segment to new contacts  
- Works with Mautic 6.x and 7.x API - Might work with Mautic 5.x but untested.
  
## Known Issues or Future Functionality

No issues or features at this time.
