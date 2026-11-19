<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Helper.php';

class TuringSignSSL_Cron
{
    public static function run()
    {
        try {
            TuringSignSSL_Helper::processQueue(3);
        } catch (\Throwable $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('TuringSignSSL', 'Cron queue', '', $e->getMessage(), $e->getMessage());
            }
        }

        try {
            TuringSignSSL_Helper::refreshStaleCache(20);
        } catch (\Throwable $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('TuringSignSSL', 'Cron cache', '', $e->getMessage(), $e->getMessage());
            }
        }
    }
}
