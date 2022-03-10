# HandmadeWeb-CF-Pages-Notifier
WordPress plugin to trigger Cloudflare Pages deployments via webhook

## Requirements
* PHP 7.0 or higher
* WordPress 4.4 or higher

## Configuration

Create a new webhook in Cloudflare Pages, by navigating to the project > Settings > Builds & deployments, then scroll down to `Deploy hooks` and create a new hook. Copy the webhook url and add it to your `wp-config.php` file.

```php
define('HMW_CF_PAGES_NOTIFIER_TRIGGER_URL', 'https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/<PAGES WEBHOOK URL>');

```

This plugin will then flag updates on `save_post` and `delete_post` events and queue the trigger for the webhook to process after an hour (Via WP Cron), this is to avoid deployments from being triggered on every save/update of a post.

In the event that you wish to trigger the deployment earlier, a banner should appear towards the top of your WP Admin dashboard, you can click on the link within that banner to trigger the deployment earlier than the scheduled cron.
