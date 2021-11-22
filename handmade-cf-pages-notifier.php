<?php
/*
 * Plugin Name: Handmade Web - CF Pages Notifier
 * Plugin URI: https://github.com/handmadeweb/handmadeweb-cf-pages-notifier
 * Description: Handmade Web - CF Pages Notifier
 * Author: Handmade Web
 * Version: 1.0.1
 * Author URI: https://www.handmadeweb.com.au/
 * GitHub Plugin URI: https://github.com/handmadeweb/handmadeweb-cf-pages-notifier
 * Primary Branch: main
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

defined('ABSPATH') || exit;

register_deactivation_hook(__FILE__, ['HMW_CF_PAGES_NOTIFIER', 'clearPendingDeployment']);

if (!class_exists('HMW_CF_PAGES_NOTIFIER') && defined('HMW_CF_PAGES_NOTIFIER_TRIGGER_URL') && !empty(HMW_CF_PAGES_NOTIFIER_TRIGGER_URL)) {
    add_action('init', ['HMW_CF_PAGES_NOTIFIER', 'init']);

    class HMW_CF_PAGES_NOTIFIER
    {
        protected static $option_key = 'HMW_CF_PAGES_NOTIFIER_pending_deployment';
        protected static $deployment_trigger_url;

        public static function init()
        {
            $deployment_trigger_url = HMW_CF_PAGES_NOTIFIER_TRIGGER_URL;

            if (!is_array($deployment_trigger_url)) {
                $deployment_trigger_url = [$deployment_trigger_url];
            }

            static::$deployment_trigger_url = $deployment_trigger_url;

            if (static::hasPendingDeployment()) {
                add_action('admin_notices', [static::class, 'pendingDeploymentNotice'], 1);

                add_action('HMW_CF_PAGES_NOTIFIER_deployment_cron_hook', [static::class, 'runPendingDeployment']);

                if (is_admin() && !empty($_GET['HMW_CF_PAGES_NOTIFIER_TASK'])) {
                    if ('run_pending_deployment' === $_GET['HMW_CF_PAGES_NOTIFIER_TASK']) {
                        static::runPendingDeployment();
                    }
                }
            }

            add_action('save_post', [static::class, 'listenToPostEvents']);
            add_action('delete_post', [static::class, 'listenToPostEvents']);
        }

        public static function pendingDeploymentNotice()
        {
            $nextDeployment = static::GetPendingDeployment();

            if ($nextDeployment instanceof DateTime) {
                $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
                $countdown = $now->diff($nextDeployment);

                if ($countdown->i > 0) {
                    echo sprintf(file_get_contents(__DIR__.'/notices/pending_deployment.html'), 'notice notice-error', $countdown->i);
                }
            }
        }

        public static function clearPendingDeployment(): bool
        {
            static::clearNextCron();

            return update_option(static::$option_key, null);
        }

        public static function getPendingDeployment()
        {
            return get_option(static::$option_key);
        }

        public static function hasPendingDeployment(): bool
        {
            $option = get_option(static::$option_key);

            return !empty($option) ? true : false;
        }

        public static function runPendingDeployment()
        {
            $failedRequest = false;

            foreach (static::$deployment_trigger_url as $trigger_url) {
                $request = wp_remote_post(static::$deployment_trigger_url);
                if ($request instanceof WP_Error) {
                    $failedRequest = true;
                }
            }

            if ($failedRequest) {
                static::clearPendingDeployment();
            }

            return $request;
        }

        public static function setPendingDeployment(): bool
        {
            $nextRun = new DateTime('+1 hours', new DateTimeZone(wp_timezone_string()));

            static::scheduleNextCron();

            return update_option(static::$option_key, $nextRun);
        }

        protected static function clearNextCron()
        {
            if ($timestamp = wp_next_scheduled('HMW_CF_PAGES_NOTIFIER_deployment_cron_hook')) {
                wp_unschedule_event($timestamp, 'HMW_CF_PAGES_NOTIFIER_deployment_cron_hook');
            }
        }

        protected static function scheduleNextCron()
        {
            static::clearNextCron();

            wp_schedule_event(time(), 'hourly', 'HMW_CF_PAGES_NOTIFIER_deployment_cron_hook');
        }

        public static function listenToPostEvents()
        {
            static::setPendingDeployment();
        }
    }
}
