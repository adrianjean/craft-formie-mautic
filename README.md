# CraftCMS Formie Integration for Mautic
*A CraftCMS Formie Integration module for the Mautic Email Marketing Platform*  
**Tested with Mautic 6.x and 7.x**

## Overview

**CraftCMS Formie Integration for Mautic** is a CraftCMS module that adds a native **Mautic** integration to **[Verbb Formie](https://verbb.io/craft-plugins/formie/features)**. 
It allows Formie form submissions to automatically create or update contacts in Mautic, map custom fields, apply tags/segments, and handle opt-in consent.

## Requirements

| System     | Version       |
|------------|----------------|
| CraftCMS   | 5.x           |
| Formie     | 3.x           |
| Mautic     | 6.x or 7.x API |
| PHP        | 8.1+          |

## Installation

At this time the module is distributed via **GitHub only**, but can be installed with Composer using the repository URL.

### 1. Add the repository to your composer.json

```bash
composer config repositories.formie-mautic vcs https://github.com/adrianjean/formie-mautic
```

Then require it:

```bash
composer require adrianjean/formie-mautic
```

### 2. Enable the Module in CraftCMS

Add to `config/app.php`:

```php
'modules' => [
    'formie-mautic' => [
        'class' => \modules\formiemautic\FormieMauticModule::class,
    ],
],
'bootstrap' => ['formie-mautic'],
```

### 3. Add Composer Autoload Entry

Add this module to your project’s composer.json. You may already have an entry, just add it as another entry:

```json
"autoload": {
    "psr-4": {
        "modules\\": "modules/",
        "modules\\formiemautic\\": "modules/formie-mautic/src/"
    }
}
```

Then run:

```bash
composer dump-autoload
```


## 🔧 Configuration

In **Formie → Settings → Integrations**, add a new **Mautic Integration**.

Credentials required:

- **Base URL**
- **Public Key**
- **Private Key**

These are created under:

**Mautic → Settings → API Credentials**

Environment variables are supported:

```
$MAUTIC_BASE_URL
$MAUTIC_PUBLIC_KEY
$MAUTIC_PRIVATE_KEY
```

## 🎛 Features

- ✔ Map Formie fields to Mautic custom fields  
- ✔ Opt-in / consent checkbox  
- ✔ Automatically assign a Mautic segment to new contacts  
- ✔ Works with Mautic 6.x and 7.x API  
- ✔ Simple configuration through Formie UI  

## 🧪 Usage

1. Create a Formie form  
2. Add your fields + optional opt-in consent  
3. Go to **Form → Integrations**  
4. Add **Mautic**  
5. Map your fields to Mautic fields  
6. Choose a Mautic Segment to assign contacts to  
7. Submit your form and verify contact creation/update in Mautic  

## 🐞 Known Issues

No known issues at this time.

## 🤝 Contributing

PRs and suggestions are welcome!

## 📄 License

MIT License

## 🙌 Credits

Created by **Adrian Jean (Spark* Advocacy)**  
