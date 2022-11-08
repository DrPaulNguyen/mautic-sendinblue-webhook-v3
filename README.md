# Mautic Sendinblue Plugin

This Plugin brings Sendinblue integration to Mautic 2.15.2 and newer.

Licensed under GNU General Public License v3.0.

## Installation via composer
1. Execute `composer require dazzle/mautic-sendinblue-bundle` in the main directory of the Mautic installation.
2. Clear the cache via console command `sudo -u www-data php bin/console cache:clear --env=prod` (might take a while) *OR* manually delete the `app/cache/prod` directory.

## Configuration
Navigate to the Plugins page and click "Install/Upgrade Plugins". You should now see a "Sendinblue integration" plugin.

### Emails
Navigate to the Configuration page and open Email Settings section. Set "Sendinblue - API" service to send email through and enter your Sendinblue API key (use v3).

### Webhooks
1. Navigate to your Sendinblue account and open the Webhook page in Transactional settings.
2. Add a new webhook:
    1. URL to call: https://SITENAME/mailer/sendinblue_api/callback
    2. Supported events:
        * Error
        * Soft Bounce
        * Hard Bounce
        * Invalid email
        * Complaint
        * Unsubscribed
        * Blocked
