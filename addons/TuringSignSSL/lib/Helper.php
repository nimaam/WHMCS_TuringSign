<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

class TuringSignSSL_Helper
{
    const CACHE_TTL_MINUTES = 15;

    public static function createTables()
    {
        if (!Capsule::schema()->hasTable('mod_turingsign_ssl_sync_jobs')) {
            Capsule::schema()->create('mod_turingsign_ssl_sync_jobs', function ($table) {
                $table->increments('id');
                $table->integer('product_id');
                $table->string('status', 20)->default('pending');
                $table->text('message')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->index('status');
            });
        }

        if (!Capsule::schema()->hasTable('mod_turingsign_ssl_cache')) {
            Capsule::schema()->create('mod_turingsign_ssl_cache', function ($table) {
                $table->integer('order_id')->primary();
                $table->string('valid_from', 64)->nullable();
                $table->string('valid_to', 64)->nullable();
                $table->string('api_status', 64)->nullable();
                $table->string('common_name', 255)->nullable();
                $table->text('sans')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }
    }

    public static function getProducts()
    {
        return Capsule::table('tblproducts')
            ->where('servertype', '=', 'TuringSign')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    public static function getOrders()
    {
        return Capsule::table('tblsslorders as s')
            ->join('tblhosting as h', 'h.id', '=', 's.serviceid')
            ->join('tblclients as c', 'c.id', '=', 'h.userid')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('s.module', '=', 'TuringSign')
            ->orderBy('s.id', 'desc')
            ->select([
                's.id as order_id',
                's.serviceid',
                's.remoteid',
                's.status as order_status',
                's.completiondate',
                'h.domain',
                'h.domainstatus',
                'h.regdate',
                'h.nextduedate',
                'h.firstpaymentamount',
                'h.userid',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'p.id as product_id',
                'p.name as product_name',
                'p.configoption1',
                'p.configoption2',
                'p.configoption3',
            ])
            ->get()
            ->all();
    }

    public static function getOrderWithProduct($orderId)
    {
        return Capsule::table('tblsslorders as s')
            ->join('tblhosting as h', 'h.id', '=', 's.serviceid')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('s.id', '=', (int) $orderId)
            ->where('s.module', '=', 'TuringSign')
            ->select([
                's.*',
                'h.domainstatus',
                'h.domain',
                'h.nextduedate',
                'h.userid as service_userid',
                'p.name as product_name',
                'p.configoption1',
                'p.configoption2',
                'p.configoption3',
            ])
            ->first();
    }

    public static function getCacheMap(array $orderIds)
    {
        if (!Capsule::schema()->hasTable('mod_turingsign_ssl_cache')) {
            return [];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));

        if (empty($orderIds)) {
            return [];
        }

        $rows = Capsule::table('mod_turingsign_ssl_cache')
            ->whereIn('order_id', $orderIds)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->order_id] = $row;
        }

        return $map;
    }

    public static function createApi($product)
    {
        $autoload = ROOTDIR . '/modules/servers/TuringSign/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new \RuntimeException('The TuringSign module autoload file was not found.');
        }

        require_once $autoload;

        $url = trim((string) ($product->configoption1 ?? ''));
        $appId = trim((string) ($product->configoption2 ?? ''));
        $secret = trim((string) ($product->configoption3 ?? ''));

        if ($url === '' || $appId === '' || $secret === '') {
            throw new \RuntimeException('The TuringSign API credentials are incomplete for this product.');
        }

        return new \ModulesGarden\TuringSign\Api\TlsManagerApi($url, $appId, $secret);
    }

    public static function refreshOrderCache($orders, $limit = 15)
    {
        if (empty($orders) || !Capsule::schema()->hasTable('mod_turingsign_ssl_cache')) {
            return 0;
        }

        $limit = max(1, (int) $limit);
        $now = date('Y-m-d H:i:s');
        $staleAfter = date('Y-m-d H:i:s', time() - (self::CACHE_TTL_MINUTES * 60));
        $cache = self::getCacheMap(array_map(function ($order) {
            return (int) $order->order_id;
        }, $orders));

        $apis = [];
        $updated = 0;

        foreach ($orders as $order) {
            if ($updated >= $limit) {
                break;
            }

            if (empty($order->remoteid)) {
                continue;
            }

            $cached = $cache[(int) $order->order_id] ?? null;

            if ($cached && !empty($cached->updated_at) && $cached->updated_at >= $staleAfter) {
                continue;
            }

            $productKey = (int) $order->product_id;

            if (!isset($apis[$productKey])) {
                try {
                    $apis[$productKey] = self::createApi($order);
                } catch (\Throwable $e) {
                    continue;
                }
            }

            try {
                $data = $apis[$productKey]->getOrder((string) $order->remoteid);

                if (!is_array($data)) {
                    $data = [];
                }

                $info = $data['order_certificate_info'] ?? [];
                if (!is_array($info)) {
                    $info = [];
                }

                $sans = $info['sans']['dns'] ?? [];
                if (!is_array($sans)) {
                    $sans = [];
                }

                Capsule::table('mod_turingsign_ssl_cache')->updateOrInsert(
                    ['order_id' => (int) $order->order_id],
                    [
                        'valid_from' => (string) ($info['valid_from'] ?? ''),
                        'valid_to' => (string) ($info['valid_to'] ?? ''),
                        'api_status' => strtoupper((string) ($data['order_status'] ?? '')),
                        'common_name' => (string) ($info['common_name'] ?? ''),
                        'sans' => implode(', ', $sans),
                        'updated_at' => $now,
                    ]
                );

                $updated++;
            } catch (\Throwable $e) {
                if (function_exists('logModuleCall')) {
                    logModuleCall(
                        'TuringSignSSL',
                        'Cache refresh failed',
                        (string) $order->remoteid,
                        $e->getMessage(),
                        $e->getMessage()
                    );
                }
            }
        }

        return $updated;
    }

    public static function refreshStaleCache($limit = 20)
    {
        return self::refreshOrderCache(self::getOrders(), $limit);
    }

    public static function revoke($orderId)
    {
        $order = self::getOrderWithProduct($orderId);

        if (!$order) {
            throw new \RuntimeException('The SSL order was not found.');
        }

        if (empty($order->remoteid)) {
            throw new \RuntimeException('This order has no TuringSign order reference yet.');
        }

        $api = self::createApi($order);
        $api->revokeCertificate((string) $order->remoteid);

        Capsule::table('tblsslorders')->where('id', '=', (int) $orderId)->update([
            'status' => 'Revoked',
        ]);

        Capsule::table('mod_turingsign_ssl_cache')->where('order_id', '=', (int) $orderId)->update([
            'api_status' => 'REVOKED',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        logActivity('TuringSign SSL: certificate revoked (order #' . (int) $orderId . ', service #' . (int) $order->serviceid . ')');
    }

    public static function cancel($orderId)
    {
        $order = self::getOrderWithProduct($orderId);

        if (!$order) {
            throw new \RuntimeException('The SSL order was not found.');
        }

        Capsule::table('tblhosting')->where('id', '=', (int) $order->serviceid)->update([
            'domainstatus' => 'Cancelled',
        ]);

        Capsule::table('tblsslorders')->where('id', '=', (int) $orderId)->update([
            'status' => 'Cancelled',
        ]);

        logActivity('TuringSign SSL: service cancelled from dashboard (order #' . (int) $orderId . ', service #' . (int) $order->serviceid . ')');
    }

    public static function renew($orderId)
    {
        $order = self::getOrderWithProduct($orderId);

        if (!$order) {
            throw new \RuntimeException('The SSL order was not found.');
        }

        $autoload = ROOTDIR . '/modules/servers/TuringSign/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new \RuntimeException('The TuringSign module autoload file was not found.');
        }

        require_once $autoload;

        $renew = new \ModulesGarden\TuringSign\Actions\Renew([
            'serviceid' => (int) $order->serviceid,
        ]);

        $result = $renew->execute();

        if (!empty($result)) {
            throw new \RuntimeException((string) $result);
        }

        logActivity('TuringSign SSL: renewal configuration started from dashboard (order #' . (int) $orderId . ', service #' . (int) $order->serviceid . ')');
    }

    public static function queueSync($productId)
    {
        if (!Capsule::schema()->hasTable('mod_turingsign_ssl_sync_jobs')) {
            throw new \RuntimeException('The TuringSign SSL addon is not activated.');
        }

        $product = Capsule::table('tblproducts')
            ->where('id', '=', (int) $productId)
            ->where('servertype', '=', 'TuringSign')
            ->first();

        if (!$product) {
            throw new \RuntimeException('The selected product is not a TuringSign product.');
        }

        Capsule::table('mod_turingsign_ssl_sync_jobs')->insert([
            'product_id' => (int) $productId,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        logActivity('TuringSign SSL: product synchronization queued for product #' . (int) $productId);
    }

    public static function getQueueJobs($limit = 25)
    {
        if (!Capsule::schema()->hasTable('mod_turingsign_ssl_sync_jobs')) {
            return [];
        }

        return Capsule::table('mod_turingsign_ssl_sync_jobs as j')
            ->leftJoin('tblproducts as p', 'p.id', '=', 'j.product_id')
            ->orderBy('j.id', 'desc')
            ->limit((int) $limit)
            ->select([
                'j.*',
                'p.name as product_name',
            ])
            ->get()
            ->all();
    }

    public static function processQueue($limit = 3)
    {
        if (!Capsule::schema()->hasTable('mod_turingsign_ssl_sync_jobs')) {
            return 0;
        }

        $jobs = Capsule::table('mod_turingsign_ssl_sync_jobs')
            ->where('status', '=', 'pending')
            ->orderBy('id')
            ->limit((int) $limit)
            ->get();

        $processed = 0;

        foreach ($jobs as $job) {
            Capsule::table('mod_turingsign_ssl_sync_jobs')->where('id', '=', (int) $job->id)->update([
                'status' => 'running',
                'started_at' => date('Y-m-d H:i:s'),
            ]);

            try {
                $product = Capsule::table('tblproducts')
                    ->where('id', '=', (int) $job->product_id)
                    ->where('servertype', '=', 'TuringSign')
                    ->first();

                if (!$product) {
                    throw new \RuntimeException('The product was not found or is not a TuringSign product.');
                }

                self::createApi($product);

                $autoload = ROOTDIR . '/modules/servers/TuringSign/vendor/autoload.php';
                if (!is_file($autoload)) {
                    throw new \RuntimeException('The TuringSign module autoload file was not found.');
                }

                require_once $autoload;

                $sync = new \ModulesGarden\TuringSign\Services\SyncProducts(
                    (string) $product->configoption1,
                    (string) $product->configoption2,
                    (string) $product->configoption3
                );

                $sync->sync();

                Capsule::table('mod_turingsign_ssl_sync_jobs')->where('id', '=', (int) $job->id)->update([
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'message' => 'Synchronization completed.',
                ]);

                logActivity('TuringSign SSL: product synchronization completed (job #' . (int) $job->id . ')');
                $processed++;
            } catch (\Throwable $e) {
                Capsule::table('mod_turingsign_ssl_sync_jobs')->where('id', '=', (int) $job->id)->update([
                    'status' => 'failed',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'message' => substr($e->getMessage(), 0, 1000),
                ]);

                if (function_exists('logModuleCall')) {
                    logModuleCall('TuringSignSSL', 'Product sync failed', (string) $job->product_id, $e->getMessage(), $e->getMessage());
                }
            }
        }

        return $processed;
    }

    public static function runConfigure()
    {
        $path = ROOTDIR . '/modules/servers/TuringSign/configure.php';

        if (!is_file($path)) {
            throw new \RuntimeException('configure.php was not found.');
        }

        if (!function_exists('exec')) {
            throw new \RuntimeException('The PHP exec() function is disabled on this server.');
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            throw new \RuntimeException('The PHP exec() function is disabled on this server.');
        }

        $php = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        if (!is_executable($php)) {
            $php = PHP_BINARY;
        }

        $command = escapeshellarg($php) . ' -q ' . escapeshellarg($path) . ' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        return [
            'exit_code' => (int) $exitCode,
            'output' => implode("\n", $output),
        ];
    }

    public static function resolvePeriod($period, $from, $to)
    {
        $today = date('Y-m-d');
        $period = (string) $period;

        if ($period === 'daily') {
            return [$period, $today, $today];
        }

        if ($period === 'weekly') {
            return [$period, date('Y-m-d', strtotime('-6 days')), $today];
        }

        if ($period === 'custom') {
            $from = trim((string) $from);
            $to = trim((string) $to);

            if (!self::isDate($from)) {
                $from = date('Y-m-d', strtotime('-29 days'));
            }

            if (!self::isDate($to)) {
                $to = $today;
            }

            if ($from > $to) {
                $swap = $from;
                $from = $to;
                $to = $swap;
            }

            return ['custom', $from, $to];
        }

        return ['monthly', date('Y-m-d', strtotime('-29 days')), $today];
    }

    public static function getReport($start, $end)
    {
        $query = Capsule::table('tblhosting as h')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->leftJoin('tblsslorders as s', function ($join) {
                $join->on('s.serviceid', '=', 'h.id')
                    ->where('s.module', '=', 'TuringSign');
            })
            ->where('p.servertype', '=', 'TuringSign')
            ->whereBetween('h.regdate', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->select([
                'p.id as product_id',
                'p.name as product_name',
                'h.firstpaymentamount',
                'h.regdate',
                's.id as order_id',
                's.status as local_status',
            ]);

        if (Capsule::schema()->hasTable('mod_turingsign_ssl_cache')) {
            $query->leftJoin('mod_turingsign_ssl_cache as c', 'c.order_id', '=', 's.id');
            $query->addSelect('c.api_status');
        } else {
            $query->addSelect(Capsule::raw('NULL as api_status'));
        }

        $rows = $query->get();

        $productStatuses = ['Pending', 'Issued', 'Revoked', 'Cancelled'];
        $reportStatuses = ['Pending', 'Issued', 'Revoked', 'Cancelled', 'Expired', 'Suspended', 'Other'];
        $productStats = [];
        $statusStats = array_fill_keys($reportStatuses, 0);
        $totalOrders = 0;
        $totalAmount = 0.0;

        foreach ($rows as $row) {
            $productId = (int) $row->product_id;

            if (!isset($productStats[$productId])) {
                $productStats[$productId] = [
                    'name' => (string) $row->product_name,
                    'count' => 0,
                    'amount' => 0.0,
                    'statuses' => array_fill_keys($productStatuses, 0),
                    'other' => 0,
                ];
            }

            $amount = (float) $row->firstpaymentamount;
            $status = self::reportStatus(
                !empty($row->api_status) ? $row->api_status : (!empty($row->local_status) ? $row->local_status : '')
            );

            $productStats[$productId]['count']++;
            $productStats[$productId]['amount'] += $amount;

            if (in_array($status, $productStatuses, true)) {
                $productStats[$productId]['statuses'][$status]++;
            } else {
                $productStats[$productId]['other']++;
            }

            $statusStats[$status] = ($statusStats[$status] ?? 0) + 1;
            $totalOrders++;
            $totalAmount += $amount;
        }

        foreach ($productStats as &$product) {
            $product['share'] = $totalOrders > 0
                ? round(($product['count'] * 100) / $totalOrders, 1)
                : 0.0;
        }
        unset($product);

        uasort($productStats, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        arsort($statusStats);

        return [
            'productStats' => $productStats,
            'statusStats' => $statusStats,
            'totalOrders' => $totalOrders,
            'totalAmount' => $totalAmount,
        ];
    }

    public static function reportStatus($status)
    {
        $status = self::normalizeStatus($status);

        if (in_array($status, ['Pending', 'Configuration Submitted', 'Awaiting Configuration', 'Awaiting Renew'], true)) {
            return 'Pending';
        }

        if (in_array($status, ['Issued', 'Active', 'Valid', 'Completed'], true)) {
            return 'Issued';
        }

        if (in_array($status, ['Revoked', 'Cancelled', 'Expired', 'Suspended'], true)) {
            return $status;
        }

        return 'Other';
    }

    public static function statusColor($status)
    {
        switch ($status) {
            case 'Issued':
                return '#5bc758';
            case 'Pending':
                return '#2f8de4';
            case 'Revoked':
            case 'Cancelled':
                return '#ed4040';
            case 'Suspended':
                return '#ff9900';
            case 'Expired':
            default:
                return '#7f8c8d';
        }
    }

    public static function getViewData($tab)
    {
        $data = [];

        if ($tab === 'ssl') {
            $orders = self::getOrders();
            self::refreshOrderCache($orders, 15);

            $data['orders'] = $orders;
            $data['cache'] = self::getCacheMap(array_map(function ($order) {
                return (int) $order->order_id;
            }, $orders));
        } elseif ($tab === 'report') {
            list($period, $start, $end) = self::resolvePeriod(
                $_GET['period'] ?? 'monthly',
                $_GET['from'] ?? '',
                $_GET['to'] ?? ''
            );

            $report = self::getReport($start, $end);

            $data['period'] = $period;
            $data['start'] = $start;
            $data['end'] = $end;
            $data['productStats'] = $report['productStats'];
            $data['statusStats'] = $report['statusStats'];
            $data['totalOrders'] = $report['totalOrders'];
            $data['totalAmount'] = $report['totalAmount'];

            $products = array_values($report['productStats']);
            $productColors = [];
            $palette = ['#2f8de4', '#5bc758', '#ff9900', '#ed4040', '#8e44ad', '#16a085', '#f1c40f', '#e67e22'];

            foreach ($products as $index => $product) {
                $productColors[] = $palette[$index % count($palette)];
            }

            $statusLabels = array_keys($report['statusStats']);

            $data['reportJson'] = json_encode([
                'products' => [
                    'labels' => array_map(function ($product) {
                        return $product['name'];
                    }, $products),
                    'amounts' => array_map(function ($product) {
                        return round((float) $product['amount'], 2);
                    }, $products),
                    'counts' => array_map(function ($product) {
                        return (int) $product['count'];
                    }, $products),
                    'colors' => $productColors,
                ],
                'statuses' => [
                    'labels' => $statusLabels,
                    'data' => array_values($report['statusStats']),
                    'colors' => array_map(function ($status) {
                        return self::statusColor($status);
                    }, $statusLabels),
                ],
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } elseif ($tab === 'tools') {
            $data['products'] = self::getProducts();
            $data['queueJobs'] = self::getQueueJobs(25);
            $data['configureOutput'] = $_SESSION['TuringSignSSLConfigureOutput'] ?? null;
            unset($_SESSION['TuringSignSSLConfigureOutput']);
        }

        return $data;
    }

    public static function handlePost()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        check_token('WHMCS.admin.default');

        $action = (string) ($_POST['ts_action'] ?? '');

        try {
            switch ($action) {
                case 'revoke':
                    self::revoke((int) ($_POST['order_id'] ?? 0));
                    self::flash('success', 'Certificate revoked.');
                    self::redirect('addonmodules.php?module=TuringSignSSL&tab=ssl');
                    break;

                case 'cancel':
                    self::cancel((int) ($_POST['order_id'] ?? 0));
                    self::flash('success', 'Service cancelled.');
                    self::redirect('addonmodules.php?module=TuringSignSSL&tab=ssl');
                    break;

                case 'renew':
                    self::renew((int) ($_POST['order_id'] ?? 0));
                    self::flash('success', 'Renewal configuration started and the customer was emailed.');
                    self::redirect('addonmodules.php?module=TuringSignSSL&tab=ssl');
                    break;

                case 'refresh_cache':
                    $updated = self::refreshStaleCache(100);
                    self::flash('success', 'Refreshed ' . (int) $updated . ' certificate status(es).');
                    self::redirect('addonmodules.php?module=TuringSignSSL&tab=ssl');
                    break;

                case 'run_configure':
                    $result = self::runConfigure();
                    $_SESSION['TuringSignSSLConfigureOutput'] = $result;
                    self::flash(
                        ((int) $result['exit_code'] === 0) ? 'success' : 'error',
                        ((int) $result['exit_code'] === 0)
                            ? 'configure.php completed successfully.'
                            : 'configure.php finished with exit code ' . (int) $result['exit_code'] . '.'
                    );
                    self::redirect('addonmodules.php?module=TuringSignSSL&tab=tools');
                    break;

                case 'queue_sync':
                    self::queueSync((int) ($_POST['product_id'] ?? 0));
                    self::flash('success', 'Product synchronization queued. It will run on the next WHMCS cron.');
                    self::redirect('addonmodules.php?module=TuringSignSSL&tab=tools');
                    break;

                case 'run_queue':
                    $processed = self::processQueue(10);
                    self::flash('success', 'Processed ' . (int) $processed . ' queued synchronization job(s).');
                    self::redirect('addonmodules.php?module=TuringSignSSL&tab=tools');
                    break;
            }
        } catch (\Throwable $e) {
            self::flash('error', $e->getMessage());
            $errorTab = in_array($action, ['run_configure', 'queue_sync'], true) ? 'tools' : 'ssl';
            self::redirect('addonmodules.php?module=TuringSignSSL&tab=' . $errorTab);
        }
    }

    public static function flash($type, $message)
    {
        $_SESSION['TuringSignSSLFlash'] = [
            'type' => $type === 'success' ? 'success' : 'error',
            'message' => (string) $message,
        ];
    }

    public static function pullFlash()
    {
        $flash = $_SESSION['TuringSignSSLFlash'] ?? null;
        unset($_SESSION['TuringSignSSLFlash']);

        return $flash;
    }

    public static function redirect($url)
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        echo '<script>window.location.href = ' . json_encode($url) . ';</script>';
        exit;
    }

    public static function normalizeStatus($status)
    {
        $status = trim((string) $status);

        if ($status === '') {
            return 'Unknown';
        }

        $map = [
            'PENDING' => 'Pending',
            'ISSUED' => 'Issued',
            'ACTIVE' => 'Active',
            'REVOKED' => 'Revoked',
            'CANCELLED' => 'Cancelled',
            'CANCELED' => 'Cancelled',
            'EXPIRED' => 'Expired',
            'SUSPENDED' => 'Suspended',
            'CONFIGURATION SUBMITTED' => 'Configuration Submitted',
            'AWAITING CONFIGURATION' => 'Awaiting Configuration',
            'AWAITING RENEW' => 'Awaiting Renew',
            'COMPLETED' => 'Completed',
            'VALID' => 'Valid',
        ];

        $key = strtoupper(str_replace('_', ' ', $status));

        return $map[$key] ?? ucwords(strtolower(str_replace('_', ' ', $status)));
    }

    public static function statusClass($status)
    {
        $key = strtoupper((string) $status);

        if (in_array($key, ['ACTIVE', 'ISSUED', 'VALID', 'COMPLETED'], true)) {
            return 'success';
        }

        if (in_array($key, ['PENDING', 'AWAITING CONFIGURATION', 'AWAITING RENEW', 'CONFIGURATION SUBMITTED'], true)) {
            return 'warning';
        }

        if (in_array($key, ['REVOKED', 'CANCELLED', 'CANCELED', 'EXPIRED', 'SUSPENDED'], true)) {
            return 'danger';
        }

        return 'default';
    }

    public static function clientName($order)
    {
        $company = trim((string) ($order->companyname ?? ''));

        if ($company !== '') {
            return $company;
        }

        $name = trim((string) ($order->firstname ?? '') . ' ' . (string) ($order->lastname ?? ''));

        return $name !== '' ? $name : ('Client #' . (int) ($order->userid ?? 0));
    }

    public static function domainName($order, $cacheRow = null)
    {
        $commonName = trim((string) ($cacheRow->common_name ?? ''));

        if ($commonName !== '') {
            return $commonName;
        }

        return (string) ($order->domain ?? '');
    }

    public static function money($amount)
    {
        if (function_exists('formatCurrency')) {
            return formatCurrency((float) $amount);
        }

        return number_format((float) $amount, 2);
    }

    public static function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function isDate($value)
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value);
    }
}
