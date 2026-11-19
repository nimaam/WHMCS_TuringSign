<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Cron.php';

add_hook('AfterCronJob', 1, function () {
    TuringSignSSL_Cron::run();
});
