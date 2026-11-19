<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Helper.php';

function TuringSignSSL_config()
{
    return [
        'name' => 'TuringSign SSL',
        'description' => 'TuringSign SSL certificate dashboard: certificates, reports and tools.',
        'version' => '1.0.7.8',
        'author' => 'Ultahost',
        'fields' => [],
    ];
}

function TuringSignSSL_activate()
{
    try {
        TuringSignSSL_Helper::createTables();

        return [
            'status' => 'success',
            'description' => 'TuringSign SSL dashboard activated. The synchronization queue and certificate cache tables are ready.',
        ];
    } catch (\Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

function TuringSignSSL_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'TuringSign SSL dashboard deactivated. Its data tables were kept.',
    ];
}

function TuringSignSSL_output($vars)
{
    TuringSignSSL_Helper::handlePost();

    $tab = (string) ($_GET['tab'] ?? 'ssl');

    if (!in_array($tab, ['ssl', 'report', 'tools'], true)) {
        $tab = 'ssl';
    }

    $data = TuringSignSSL_Helper::getViewData($tab);
    $flash = TuringSignSSL_Helper::pullFlash();
    $moduleUrl = 'addonmodules.php?module=TuringSignSSL';

    $systemUrl = rtrim((string) \WHMCS\Config\Setting::getValue('SystemURL'), '/');
    $assetBase = $systemUrl !== ''
        ? $systemUrl . '/modules/addons/TuringSignSSL'
        : '../modules/addons/TuringSignSSL';

    $logoUrl = $assetBase . '/logo.svg';
    $csrfInput = generate_token();

    extract($data, EXTR_SKIP);

    include __DIR__ . '/templates/dashboard.php';
}
