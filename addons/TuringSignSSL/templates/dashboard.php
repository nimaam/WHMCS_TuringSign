<?php
/**
 * @var string $tab
 * @var string $moduleUrl
 * @var string $logoUrl
 * @var string $assetBase
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

$periodLabels = [
    'daily' => 'Today',
    'weekly' => 'Last Week',
    'monthly' => 'Last Month',
    'custom' => 'From / To',
];

$palette = ['#2f8de4', '#5bc758', '#ff9900', '#ed4040', '#8e44ad', '#16a085', '#f1c40f', '#e67e22'];
?>
<link rel="stylesheet" href="<?= TuringSignSSL_Helper::h($assetBase) ?>/assets/css/layers-ui.css">
<link rel="stylesheet" href="<?= TuringSignSSL_Helper::h($assetBase) ?>/assets/css/mg_styles.css">
<link rel="stylesheet" href="<?= TuringSignSSL_Helper::h($assetBase) ?>/assets/css/dashboard.css">

<div id="layers">
    <div class="lu-app">
        <div class="lu-app-main">
            <div class="lu-app-main__body ts-ox-body">

                <div class="ts-brand">
                    <img src="<?= TuringSignSSL_Helper::h($logoUrl) ?>" alt="TuringSign" class="ts-logo">
                    <div class="ts-brand-text">
                        <h2>TuringSign SSL</h2>
                        <p>Certificates, reporting and tools for the TuringSign WHMCS module.</p>
                    </div>
                </div>

                <?php if (!empty($flash)): ?>
                    <div class="lu-alert lu-alert--<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
                        <div class="lu-alert__body"><?= TuringSignSSL_Helper::h($flash['message']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="ts-tabs">
                    <a href="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl"
                       class="lu-btn lu-btn--sm <?= $tab === 'ssl' ? 'lu-btn--primary' : 'lu-btn--default' ?>">
                        <span class="lu-btn__text"><i class="lu-zmdi lu-zmdi-lock"></i> SSL Certificates</span>
                    </a>
                    <a href="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=report"
                       class="lu-btn lu-btn--sm <?= $tab === 'report' ? 'lu-btn--primary' : 'lu-btn--default' ?>">
                        <span class="lu-btn__text"><i class="lu-zmdi lu-zmdi-chart-donut"></i> Reports</span>
                    </a>
                    <a href="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=tools"
                       class="lu-btn lu-btn--sm <?= $tab === 'tools' ? 'lu-btn--primary' : 'lu-btn--default' ?>">
                        <span class="lu-btn__text"><i class="lu-zmdi lu-zmdi-settings"></i> Tools</span>
                    </a>
                </div>

                <?php if ($tab === 'ssl'): ?>
                    <div class="lu-widget widgetActionComponent vueDatatableTable">
                        <div class="lu-widget__header">
                            <div class="lu-widget__top lu-top">
                                <div class="lu-top__title">
                                    <i class="lu-zmdi lu-zmdi-lock"></i> SSL Certificates
                                </div>
                                <div class="lu-top__toolbar">
                                    <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl" class="ts-inline">
                                        <?= $csrfInput ?>
                                        <input type="hidden" name="ts_action" value="refresh_cache">
                                        <button type="submit" class="lu-btn lu-btn--sm lu-btn--default">
                                            <span class="lu-btn__text"><i class="lu-zmdi lu-zmdi-refresh"></i> Refresh statuses</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="lu-widget__body">
                            <div class="lu-t-c datatableLoader">
                                <div class="lu-t-c__top lu-top mob-top-search">
                                    <div class="lu-top__toolbar">
                                        <input type="text" id="tsSslSearch" class="lu-form-control"
                                               placeholder="Search domain, client or product...">
                                    </div>
                                </div>
                                <div class="dataTables_wrapper no-footer">
                                    <div class="ts-table-scroll">
                                    <table class="lu-table lu-table--mob-collapsible dataTable no-footer dtr-column"
                                           id="tsSslTable" width="100%">
                                        <thead>
                                        <tr>
                                            <th><span class="lu-table__text">Domain</span></th>
                                            <th><span class="lu-table__text">Client</span></th>
                                            <th><span class="lu-table__text">Product</span></th>
                                            <th><span class="lu-table__text">Valid From</span></th>
                                            <th><span class="lu-table__text">Valid To</span></th>
                                            <th><span class="lu-table__text">Status</span></th>
                                            <th class="mgTableActionsHeader"></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($orders)): ?>
                                            <tr>
                                                <td colspan="7" class="ts-empty">No TuringSign SSL orders found.</td>
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
                                                        <small>Service #<?= (int) $order->serviceid ?></small>
                                                    </td>
                                                    <td><?= TuringSignSSL_Helper::h(TuringSignSSL_Helper::clientName($order)) ?></td>
                                                    <td><?= TuringSignSSL_Helper::h($order->product_name) ?></td>
                                                    <td><?= TuringSignSSL_Helper::h($validFrom !== '' ? $validFrom : '—') ?></td>
                                                    <td><?= TuringSignSSL_Helper::h($validTo !== '' ? $validTo : '—') ?></td>
                                                    <td>
                                                        <span class="lu-label lu-label--sm lu-label--<?= TuringSignSSL_Helper::h(TuringSignSSL_Helper::statusClass($status)) ?>">
                                                            <?= TuringSignSSL_Helper::h($status) ?>
                                                        </span>
                                                        <br>
                                                        <small><?= TuringSignSSL_Helper::h($order->order_status) ?></small>
                                                    </td>
                                                    <td class="lu-cell-actions mgTableActions">
                                                        <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl"
                                                              class="ts-inline"
                                                              onsubmit="return confirm('Revoke this certificate? This cannot be undone.');">
                                                            <?= $csrfInput ?>
                                                            <input type="hidden" name="ts_action" value="revoke">
                                                            <input type="hidden" name="order_id" value="<?= (int) $order->order_id ?>">
                                                            <button type="submit" class="lu-btn lu-btn--sm lu-btn--danger"
                                                                    <?= empty($order->remoteid) ? 'disabled' : '' ?>>
                                                                <span class="lu-btn__text">Revoke</span>
                                                            </button>
                                                        </form>
                                                        <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl"
                                                              class="ts-inline"
                                                              onsubmit="return confirm('Cancel this service? The certificate stays valid until expiry, but the service will not renew.');">
                                                            <?= $csrfInput ?>
                                                            <input type="hidden" name="ts_action" value="cancel">
                                                            <input type="hidden" name="order_id" value="<?= (int) $order->order_id ?>">
                                                            <button type="submit" class="lu-btn lu-btn--sm lu-btn--default"
                                                                    <?= $isCancelled ? 'disabled' : '' ?>>
                                                                <span class="lu-btn__text">Cancel</span>
                                                            </button>
                                                        </form>
                                                        <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=ssl"
                                                              class="ts-inline"
                                                              onsubmit="return confirm('Start a renewal? The order will be marked Awaiting Renew and the customer will receive the configuration email.');">
                                                            <?= $csrfInput ?>
                                                            <input type="hidden" name="ts_action" value="renew">
                                                            <input type="hidden" name="order_id" value="<?= (int) $order->order_id ?>">
                                                            <button type="submit" class="lu-btn lu-btn--sm lu-btn--primary"
                                                                    <?= empty($order->remoteid) ? 'disabled' : '' ?>>
                                                                <span class="lu-btn__text">Renew</span>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="ts-footnote">
                        Valid from/to and the API status are refreshed from TuringSign and cached for
                        <?= (int) TuringSignSSL_Helper::CACHE_TTL_MINUTES ?> minutes.
                    </p>

                <?php elseif ($tab === 'report'): ?>
                    <div class="lu-widget mg-ox-sales-report">
                        <div class="lu-widget__header">
                            <div class="lu-widget__top lu-top">
                                <div class="lu-top__title">
                                    <i class="lu-zmdi lu-zmdi-chart-donut"></i> Sales report
                                </div>
                            </div>
                        </div>
                        <div class="lu-widget__body mg-ox-sales-report__body">
                            <div class="mg-ox-sales-report__filters">
                                <span class="mg-ox-sales-report__label">Period:</span>

                                <?php foreach ($periodLabels as $periodValue => $periodLabel): ?>
                                    <a href="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=report&period=<?= TuringSignSSL_Helper::h($periodValue) ?>&from=<?= TuringSignSSL_Helper::h($start) ?>&to=<?= TuringSignSSL_Helper::h($end) ?>"
                                       class="lu-btn lu-btn--sm <?= $period === $periodValue ? 'lu-btn--primary' : 'lu-btn--default' ?>">
                                        <span class="lu-btn__text"><?= TuringSignSSL_Helper::h($periodLabel) ?></span>
                                    </a>
                                <?php endforeach; ?>

                                <?php if ($period === 'custom'): ?>
                                    <span class="mg-ox-sales-report__custom">
                                        <form method="get" action="addonmodules.php" class="ts-inline">
                                            <input type="hidden" name="module" value="TuringSignSSL">
                                            <input type="hidden" name="tab" value="report">
                                            <input type="hidden" name="period" value="custom">
                                            <label>From
                                                <input type="date" name="from" class="lu-form-control" value="<?= TuringSignSSL_Helper::h($start) ?>">
                                            </label>
                                            <label>To
                                                <input type="date" name="to" class="lu-form-control" value="<?= TuringSignSSL_Helper::h($end) ?>">
                                            </label>
                                            <button type="submit" class="lu-btn lu-btn--sm lu-btn--success">
                                                <span class="lu-btn__text">Apply</span>
                                            </button>
                                        </form>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="mg-ox-sales-report__range">
                                Showing between <b><?= TuringSignSSL_Helper::h($start) ?></b> and
                                <b><?= TuringSignSSL_Helper::h($end) ?></b>
                            </div>

                            <div class="mg-ox-sales-report__cards">
                                <div class="mg-ox-sales-report__card mg-ox-sales-report__card--total">
                                    <div class="mg-ox-sales-report__card-value"><?= (int) $totalOrders ?></div>
                                    <div class="mg-ox-sales-report__card-label">Total</div>
                                </div>
                                <?php foreach ($statusStats as $status => $count): ?>
                                    <?php $statusColor = TuringSignSSL_Helper::statusColor($status); ?>
                                    <div class="mg-ox-sales-report__card"
                                         style="border-top-color: <?= TuringSignSSL_Helper::h($statusColor) ?>;">
                                        <div class="mg-ox-sales-report__card-value"
                                             style="color: <?= TuringSignSSL_Helper::h($statusColor) ?>;">
                                            <?= (int) $count ?>
                                        </div>
                                        <div class="mg-ox-sales-report__card-label"><?= TuringSignSSL_Helper::h($status) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mg-ox-sales-report__hint">Period totals</div>

                            <div class="lu-row mg-ox-sales-report__main">
                                <div class="lu-col-md-5">
                                    <div class="mg-ox-sales-report__chart">
                                        <canvas id="tsProductChart"></canvas>
                                    </div>
                                </div>
                                <div class="lu-col-md-7">
                                    <div class="ts-table-scroll">
                                    <table class="lu-table mg-ox-sales-report__table" width="100%">
                                        <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="mg-ox-num">Orders</th>
                                            <th class="mg-ox-num">Amount</th>
                                            <th class="mg-ox-num">Pending</th>
                                            <th class="mg-ox-num">Issued</th>
                                            <th class="mg-ox-num">Revoked</th>
                                            <th class="mg-ox-num">Cancelled</th>
                                            <th class="mg-ox-num">Other</th>
                                            <th class="mg-ox-num">Share</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($productStats)): ?>
                                            <tr>
                                                <td colspan="9" class="ts-empty">No data for this period.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $productIndex = 0; ?>
                                            <?php foreach ($productStats as $product): ?>
                                                <tr>
                                                    <td>
                                                        <span class="mg-ox-sales-report__dot"
                                                              style="background-color: <?= TuringSignSSL_Helper::h($palette[$productIndex % count($palette)]) ?>;"></span>
                                                        <?= TuringSignSSL_Helper::h($product['name']) ?>
                                                    </td>
                                                    <td class="mg-ox-num"><b><?= (int) $product['count'] ?></b></td>
                                                    <td class="mg-ox-num"><?= TuringSignSSL_Helper::money($product['amount']) ?></td>
                                                    <td class="mg-ox-num"><?= (int) ($product['statuses']['Pending'] ?? 0) ?></td>
                                                    <td class="mg-ox-num"><?= (int) ($product['statuses']['Issued'] ?? 0) ?></td>
                                                    <td class="mg-ox-num"><?= (int) ($product['statuses']['Revoked'] ?? 0) ?></td>
                                                    <td class="mg-ox-num"><?= (int) ($product['statuses']['Cancelled'] ?? 0) ?></td>
                                                    <td class="mg-ox-num"><?= (int) $product['other'] ?></td>
                                                    <td class="mg-ox-num"><?= TuringSignSSL_Helper::h($product['share']) ?>%</td>
                                                </tr>
                                                <?php $productIndex++; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>

                            <div class="mg-ox-sales-report__current">
                                <span class="mg-ox-sales-report__label">Current totals:</span>
                                <?php foreach ($statusStats as $status => $count): ?>
                                    <span class="mg-ox-sales-report__pill">
                                        <?= TuringSignSSL_Helper::h($status) ?>:
                                        <b style="color: <?= TuringSignSSL_Helper::h(TuringSignSSL_Helper::statusColor($status)) ?>;">
                                            <?= (int) $count ?>
                                        </b>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="lu-widget">
                        <div class="lu-widget__header">
                            <div class="lu-widget__top lu-top">
                                <div class="lu-top__title">
                                    <i class="lu-zmdi lu-zmdi-chart-pie"></i> SSL status
                                </div>
                            </div>
                        </div>
                        <div class="lu-widget__body">
                            <div class="ts-chart ts-chart--status">
                                <canvas id="tsStatusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <script>
                        window.TuringSignSSLReport = <?= $reportJson ?>;
                    </script>
                    <script src="<?= TuringSignSSL_Helper::h($assetBase) ?>/assets/js/chart.umd.min.js"></script>

                <?php else: ?>
                    <div class="lu-row">
                        <div class="lu-col-md-6">
                            <div class="lu-widget">
                                <div class="lu-widget__header">
                                    <div class="lu-widget__top lu-top">
                                        <div class="lu-top__title">
                                            <i class="lu-zmdi lu-zmdi-play"></i> Run configuration
                                        </div>
                                    </div>
                                </div>
                                <div class="lu-widget__body ts-widget-pad">
                                    <p>
                                        Creates or updates the email templates and client custom fields used by the
                                        TuringSign server module.
                                    </p>
                                    <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=tools">
                                        <?= $csrfInput ?>
                                        <input type="hidden" name="ts_action" value="run_configure">
                                        <button type="submit" class="lu-btn lu-btn--sm lu-btn--primary">
                                            <span class="lu-btn__text">Run configure.php</span>
                                        </button>
                                    </form>

                                    <?php if (is_array($configureOutput)): ?>
                                        <p class="ts-footnote">Exit code: <strong><?= (int) $configureOutput['exit_code'] ?></strong></p>
                                        <pre class="ts-pre"><?= TuringSignSSL_Helper::h($configureOutput['output'] !== '' ? $configureOutput['output'] : '(no output)') ?></pre>
                                    <?php endif; ?>

                                    <p class="ts-footnote">
                                        CLI: <code>php -q modules/servers/TuringSign/configure.php</code>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="lu-col-md-6">
                            <div class="lu-widget">
                                <div class="lu-widget__header">
                                    <div class="lu-widget__top lu-top">
                                        <div class="lu-top__title">
                                            <i class="lu-zmdi lu-zmdi-refresh"></i> Synchronize products
                                        </div>
                                    </div>
                                </div>
                                <div class="lu-widget__body ts-widget-pad">
                                    <p>
                                        Queue a catalog synchronization. It runs on the next WHMCS cron using the
                                        selected product's TuringSign API credentials.
                                    </p>
                                    <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=tools">
                                        <?= $csrfInput ?>
                                        <input type="hidden" name="ts_action" value="queue_sync">
                                        <select name="product_id" class="lu-form-control" required>
                                            <option value="">Select a TuringSign product...</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= (int) $product->id ?>">
                                                    <?= TuringSignSSL_Helper::h($product->name) ?> (#<?= (int) $product->id ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="lu-btn lu-btn--sm lu-btn--primary ts-mt">
                                            <span class="lu-btn__text">Queue synchronization</span>
                                        </button>
                                    </form>
                                    <p class="ts-footnote">
                                        CLI: <code>php -q modules/servers/TuringSign/syncProducts.php [apiUrl] [applicationId] [secretKey]</code>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lu-widget">
                        <div class="lu-widget__header">
                            <div class="lu-widget__top lu-top">
                                <div class="lu-top__title">
                                    <i class="lu-zmdi lu-zmdi-time"></i> Sync queue
                                </div>
                                <div class="lu-top__toolbar">
                                    <form method="post" action="<?= TuringSignSSL_Helper::h($moduleUrl) ?>&tab=tools" class="ts-inline">
                                        <?= $csrfInput ?>
                                        <input type="hidden" name="ts_action" value="run_queue">
                                        <button type="submit" class="lu-btn lu-btn--sm lu-btn--default">
                                            <span class="lu-btn__text">Process queue now</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="lu-widget__body">
                            <div class="lu-t-c datatableLoader">
                                <div class="dataTables_wrapper no-footer">
                                    <div class="ts-table-scroll">
                                    <table class="lu-table lu-table--mob-collapsible dataTable no-footer" width="100%">
                                        <thead>
                                        <tr>
                                            <th><span class="lu-table__text">ID</span></th>
                                            <th><span class="lu-table__text">Product</span></th>
                                            <th><span class="lu-table__text">Status</span></th>
                                            <th><span class="lu-table__text">Created</span></th>
                                            <th><span class="lu-table__text">Completed</span></th>
                                            <th><span class="lu-table__text">Message</span></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($queueJobs)): ?>
                                            <tr><td colspan="6" class="ts-empty">No sync jobs yet.</td></tr>
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
                                                        <span class="lu-label lu-label--sm lu-label--<?= TuringSignSSL_Helper::h($jobClass) ?>">
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
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?= TuringSignSSL_Helper::h($assetBase) ?>/assets/js/dashboard.js"></script>
