<?php
/**
 * @var string $tab
 * @var string $moduleUrl
 * @var string $logoUrl
 * @var string $csrfInput
 * @var array|null $flash
 * @var array $orders
 * @var array $cache
 * @var string $period
 * @var string $start
 * @var string $end
 * @var array $productStats
 * @var array $statusStats
 * @var int $totalOrders
 * @var float $totalAmount
 * @var string $reportJson
 * @var array $products
 * @var array $queueJobs
 * @var array|null $configureOutput
 */
?>
<link rel="stylesheet" href="modules/addons/TuringSignSSL/assets/css/dashboard.css">

<div class="ts-dash">
    <div class="ts-dash-head">
        <img src="<?= TuringSignSSL_Helper::h($logoUrl) ?>" alt="TuringSign" class="ts-logo">
        <div>
            <h2>TuringSign SSL</h2>
            <p class="text-muted">Certificates, reporting and tools for the TuringSign WHMCS module.</p>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
            <?= TuringSignSSL_Helper::h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs ts-tabs">
        <li class="<?= $tab === 'ssl' ? 'active' : '' ?>">
            <a href="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl">
                <i class="fas fa-certificate"></i> SSL Certificates
            </a>
        </li>
        <li class="<?= $tab === 'report' ? 'active' : '' ?>">
            <a href="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=report">
                <i class="fas fa-chart-pie"></i> Reports
            </a>
        </li>
        <li class="<?= $tab === 'tools' ? 'active' : '' ?>">
            <a href="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=tools">
                <i class="fas fa-tools"></i> Tools
            </a>
        </li>
    </ul>

    <div class="tab-content ts-tab-content">
        <?php if ($tab === 'ssl'): ?>
            <div class="ts-toolbar">
                <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl" class="ts-inline">
                    <?= $csrfInput ?>
                    <input type="hidden" name="ts_action" value="refresh_cache">
                    <button type="submit" class="btn btn-default btn-sm">
                        <i class="fas fa-sync"></i> Refresh statuses
                    </button>
                </form>
                <input type="text" id="tsSslSearch" class="form-control input-sm ts-search"
                       placeholder="Search domain, client or product...">
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover ts-table" id="tsSslTable">
                    <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Client</th>
                        <th>Product</th>
                        <th>Valid From</th>
                        <th>Valid To</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No TuringSign SSL orders found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $cacheRow = $cache[(int) $order->order_id] ?? null;
                            $statusRaw = !empty($cacheRow->api_status) ? $cacheRow->api_status : $order->order_status;
                            $status = TuringSignSSL_Helper::normalizeStatus($statusRaw);
                            $domain = TuringSignSSL_Helper::domainName($order, $cacheRow);
                            $validFrom = trim((string) ($cacheRow->valid_from ?? ''));
                            $validTo = trim((string) ($cacheRow->valid_to ?? ''));
                            $isCancelled = strcasecmp((string) $order->domainstatus, 'Cancelled') === 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= TuringSignSSL_Helper::h($domain ?: '—') ?></strong><br>
                                    <small class="text-muted">Service #<?= (int) $order->serviceid ?></small>
                                </td>
                                <td><?= TuringSignSSL_Helper::h(TuringSignSSL_Helper::clientName($order)) ?></td>
                                <td><?= TuringSignSSL_Helper::h($order->product_name) ?></td>
                                <td><?= TuringSignSSL_Helper::h($validFrom !== '' ? $validFrom : '—') ?></td>
                                <td><?= TuringSignSSL_Helper::h($validTo !== '' ? $validTo : '—') ?></td>
                                <td>
                                    <span class="label label-<?= TuringSignSSL_Helper::h(TuringSignSSL_Helper::statusClass($status)) ?>">
                                        <?= TuringSignSSL_Helper::h($status) ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?= TuringSignSSL_Helper::h($order->order_status) ?></small>
                                </td>
                                <td class="text-right ts-actions">
                                    <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl"
                                          class="ts-action-form"
                                          onsubmit="return confirm('Revoke this certificate? This cannot be undone.');">
                                        <?= $csrfInput ?>
                                        <input type="hidden" name="ts_action" value="revoke">
                                        <input type="hidden" name="order_id" value="<?= (int) $order->order_id ?>">
                                        <button type="submit" class="btn btn-danger btn-xs"
                                                <?= empty($order->remoteid) ? 'disabled' : '' ?>>Revoke</button>
                                    </form>
                                    <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl"
                                          class="ts-action-form"
                                          onsubmit="return confirm('Cancel this service? The certificate stays valid until expiry, but the service will not renew.');">
                                        <?= $csrfInput ?>
                                        <input type="hidden" name="ts_action" value="cancel">
                                        <input type="hidden" name="order_id" value="<?= (int) $order->order_id ?>">
                                        <button type="submit" class="btn btn-default btn-xs"
                                                <?= $isCancelled ? 'disabled' : '' ?>>Cancel</button>
                                    </form>
                                    <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl"
                                          class="ts-action-form"
                                          onsubmit="return confirm('Start a renewal? The order will be marked Awaiting Renew and the customer will receive the configuration email.');">
                                        <?= $csrfInput ?>
                                        <input type="hidden" name="ts_action" value="renew">
                                        <input type="hidden" name="order_id" value="<?= (int) $order->order_id ?>">
                                        <button type="submit" class="btn btn-primary btn-xs"
                                                <?= empty($order->remoteid) ? 'disabled' : '' ?>>Renew</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted">
                <small>
                    Valid from/to and the API status are refreshed from TuringSign and cached for
                    <?= (int) TuringSignSSL_Helper::CACHE_TTL_MINUTES ?> minutes.
                </small>
            </p>
        <?php elseif ($tab === 'report'): ?>
            <?php
            $periodLabels = [
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
                'custom' => 'From / To',
            ];
            ?>
            <form method="get" action="addonmodules.php" class="ts-report-filter">
                <input type="hidden" name="module" value="TuringSignSSL">
                <input type="hidden" name="tab" value="report">
                <label for="tsPeriod">Period</label>
                <select name="period" id="tsPeriod" class="form-control input-sm">
                    <?php foreach ($periodLabels as $value => $label): ?>
                        <option value="<?= TuringSignSSL_Helper::h($value) ?>"
                                <?= $period === $value ? 'selected' : '' ?>>
                            <?= TuringSignSSL_Helper::h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span id="tsCustomRange" class="ts-custom-range">
                    <input type="date" name="from" value="<?= TuringSignSSL_Helper::h($start) ?>"
                           class="form-control input-sm">
                    <span>to</span>
                    <input type="date" name="to" value="<?= TuringSignSSL_Helper::h($end) ?>"
                           class="form-control input-sm">
                </span>
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </form>

            <p class="text-muted">
                Showing <strong><?= TuringSignSSL_Helper::h($start) ?></strong> to
                <strong><?= TuringSignSSL_Helper::h($end) ?></strong>.
            </p>

            <div class="row ts-report-cards">
                <div class="col-sm-4">
                    <div class="ts-card">
                        <span>Orders</span>
                        <strong><?= (int) $totalOrders ?></strong>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="ts-card">
                        <span>Amount</span>
                        <strong><?= TuringSignSSL_Helper::money($totalAmount) ?></strong>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="ts-card">
                        <span>Products</span>
                        <strong><?= count($productStats) ?></strong>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="ts-card">
                        <h4>SSL status</h4>
                        <div class="ts-chart">
                            <canvas id="tsStatusChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ts-card">
                        <h4>Amount by product</h4>
                        <div class="ts-chart">
                            <canvas id="tsProductChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="ts-card">
                        <h4>Products</h4>
                        <table class="table table-striped ts-table">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($productStats)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No data for this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productStats as $product): ?>
                                    <tr>
                                        <td><?= TuringSignSSL_Helper::h($product['name']) ?></td>
                                        <td class="text-right"><?= (int) $product['count'] ?></td>
                                        <td class="text-right"><?= TuringSignSSL_Helper::money($product['amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ts-card">
                        <h4>SSL status breakdown</h4>
                        <table class="table table-striped ts-table">
                            <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-right">Orders</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($statusStats)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No data for this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($statusStats as $status => $count): ?>
                                    <tr>
                                        <td>
                                            <span class="label label-<?= TuringSignSSL_Helper::h(TuringSignSSL_Helper::statusClass($status)) ?>">
                                                <?= TuringSignSSL_Helper::h($status) ?>
                                            </span>
                                        </td>
                                        <td class="text-right"><?= (int) $count ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                window.TuringSignSSLReport = <?= $reportJson ?>;
            </script>
            <script src="modules/addons/TuringSignSSL/assets/js/chart.umd.min.js"></script>
        <?php else: ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="ts-card">
                        <h4>Run configuration</h4>
                        <p>
                            Creates or updates the email templates and client custom fields used by the
                            TuringSign server module.
                        </p>
                        <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=tools">
                            <?= $csrfInput ?>
                            <input type="hidden" name="ts_action" value="run_configure">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play"></i> Run configure.php
                            </button>
                        </form>

                        <?php if (is_array($configureOutput)): ?>
                            <p class="text-muted" style="margin-top:12px;">
                                Exit code: <strong><?= (int) $configureOutput['exit_code'] ?></strong>
                            </p>
                            <pre class="ts-pre"><?= TuringSignSSL_Helper::h($configureOutput['output'] !== '' ? $configureOutput['output'] : '(no output)') ?></pre>
                        <?php endif; ?>

                        <p class="text-muted">
                            <small>CLI: <code>php -q modules/servers/TuringSign/configure.php</code></small>
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="ts-card">
                        <h4>Synchronize products</h4>
                        <p>
                            Queue a catalog synchronization. It runs on the next WHMCS cron using the selected
                            product's TuringSign API credentials.
                        </p>
                        <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=tools">
                            <?= $csrfInput ?>
                            <input type="hidden" name="ts_action" value="queue_sync">
                            <select name="product_id" class="form-control" required>
                                <option value="">Select a TuringSign product...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= (int) $product->id ?>">
                                        <?= TuringSignSSL_Helper::h($product->name) ?> (#<?= (int) $product->id ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                                <i class="fas fa-sync"></i> Queue synchronization
                            </button>
                        </form>
                        <p class="text-muted">
                            <small>
                                CLI: <code>php -q modules/servers/TuringSign/syncProducts.php [apiUrl] [applicationId] [secretKey]</code>
                            </small>
                        </p>
                    </div>
                </div>
            </div>

            <div class="ts-card">
                <h4>Sync queue</h4>
                <div class="table-responsive">
                    <table class="table table-striped ts-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Completed</th>
                            <th>Message</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($queueJobs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No sync jobs yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queueJobs as $job): ?>
                                <?php
                                $jobStatus = strtolower((string) $job->status);
                                $jobClass = 'default';
                                if ($jobStatus === 'completed') {
                                    $jobClass = 'success';
                                } elseif ($jobStatus === 'failed') {
                                    $jobClass = 'danger';
                                } elseif ($jobStatus === 'running') {
                                    $jobClass = 'warning';
                                }
                                ?>
                                <tr>
                                    <td><?= (int) $job->id ?></td>
                                    <td><?= TuringSignSSL_Helper::h($job->product_name ?: ('Product #' . (int) $job->product_id)) ?></td>
                                    <td>
                                        <span class="label label-<?= TuringSignSSL_Helper::h($jobClass) ?>">
                                            <?= TuringSignSSL_Helper::h(ucfirst($jobStatus)) ?>
                                        </span>
                                    </td>
                                    <td><?= TuringSignSSL_Helper::h($job->created_at ?: '—') ?></td>
                                    <td><?= TuringSignSSL_Helper::h($job->completed_at ?: '—') ?></td>
                                    <td><?= TuringSignSSL_Helper::h($job->message ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="modules/addons/TuringSignSSL/assets/js/dashboard.js"></script>
