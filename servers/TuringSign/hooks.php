<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use WHMCS\Database\Capsule;
use WHMCS\Service\Ssl;

add_hook('ClientAreaPage', 5, function () {

    if(empty($_GET['ts-action']) || $_GET['ts-action'] !== 'stepThree')
    {
        return [];
    }

    $serviceId = (int) ($_GET['id'] ?? 0);

    if($serviceId <= 0)
    {
        return [];
    }
    $sslOrder = Ssl::with('service', 'service.product', 'client')->where('userid', '=', Auth::client()->id)->where('serviceid', '=', $serviceId)->first();

    if(!$sslOrder || $sslOrder->module != 'TuringSign')
    {
        return [];
    }

    Menu::addContext('service', $sslOrder->service);
    Menu::addContext('orderStatus', $sslOrder->status);
    Menu::addContext('step', 3);

    return [
        'primarySidebar' => Menu::primarySidebar('sslCertificateOrderView'),
        'secondarySidebar' => Menu::secondarySidebar('sslCertificateOrderView'),
    ];
});

add_hook('ClientAreaPage', 5, function () {

    if(empty($_GET['ts-action']) || $_GET['ts-action'] !== 'stepTwo')
    {
        return [];
    }

    $serviceId = (int) ($_GET['id'] ?? 0);

    if($serviceId <= 0)
    {
        return [];
    }
    $sslOrder = Ssl::with('service', 'service.product', 'client')->where('userid', '=', Auth::client()->id)->where('serviceid', '=', $serviceId)->first();

    if(!$sslOrder || $sslOrder->module != 'TuringSign')
    {
        return [];
    }

    Menu::addContext('service', $sslOrder->service);
    Menu::addContext('orderStatus', $sslOrder->status);
    Menu::addContext('step', 2);

    return [
        'primarySidebar' => Menu::primarySidebar('sslCertificateOrderView'),
        'secondarySidebar' => Menu::secondarySidebar('sslCertificateOrderView'),
    ];
});

add_hook('ClientAreaPage', 5, function () {

    if(empty($_GET['ts-action']) || $_GET['ts-action'] !== 'stepOne')
    {
        return [];
    }

    $serviceId = (int) ($_GET['id'] ?? 0);

    if($serviceId <= 0)
    {
        return [];
    }
    $sslOrder = Ssl::with('service', 'service.product', 'client')->where('userid', '=', Auth::client()->id)->where('serviceid', '=', $serviceId)->first();

    if(!$sslOrder || $sslOrder->module != 'TuringSign')
    {
        return [];
    }

    Menu::addContext('service', $sslOrder->service);
    Menu::addContext('orderStatus', 'Awaiting Configuration');
    Menu::addContext('step', 1);

    return [
        'primarySidebar' => Menu::primarySidebar('sslCertificateOrderView'),
        'secondarySidebar' => Menu::secondarySidebar('sslCertificateOrderView'),
    ];
});

add_hook('ClientAreaPrimarySidebar', 1, function ($primarySidebar) {

    $action = $_GET['action'] ?? '';
    $serviceId = (int) ($_GET['id'] ?? 0);
    $tsAction = $_GET['ts-action'] ?? '';

    if($action != 'productdetails' || $serviceId <= 0 || in_array($tsAction, ['stepOne', 'stepTwo', 'stepThree'], true))
    {
        return;
    }

    $service = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
        ->where('tblhosting.id', '=', $serviceId)
        ->where('tblproducts.servertype', '=', 'TuringSign')
        ->first();

    if(!$service)
    {
        return;
    }

    $serviceDetailsOverview = $primarySidebar->getChild('Service Details Overview');

    if(!$serviceDetailsOverview)
    {
        return;
    }

    $serviceDetailsOverview->removeChild('Information');

    $serviceDetailsOverview->addChild(
        'Information', [
            'name' => 'Information',
            'label' => 'Information',
            'uri' => 'clientarea.php?action=productdetails&id=' . $serviceId,
            'order' => 1
        ]
    );
});

add_hook('AdminAreaFooterOutput', 1, function ($params) {

    $userId = (int) ($_GET['userid'] ?? 0);
    $refundStatus = $_GET['tsRefundStatus'] ?? '';
    $serviceId = null;

    if(preg_match("/window\.location='\\?userid=\d+&id=(\d+)&action=deladdon/", $params['jscode'], $m))
    {
        $serviceId = (int) $m[1];
    }

    if(!$serviceId)
    {
        return "";
    }

    $service = Capsule::table('tblhosting')
        ->where('id', '=', $serviceId)
        ->first();

    if(!$service)
    {
        return "";
    }

    $invoiceItem = Capsule::table('tblinvoiceitems')
        ->where('type', '=', 'Hosting')
        ->where('relid', '=', $serviceId)
        ->first();

    if(!$invoiceItem)
    {
        return "";
    }

    $product = Capsule::table('tblproducts')
        ->where('id', '=', $service->packageid)
        ->first();

    if(!$product)
    {
        return "";
    }

    $sslOrder = Capsule::table('tblsslorders')
        ->where('serviceid', '=', $serviceId)
        ->first();

    if(!$sslOrder || empty($sslOrder->remoteid))
    {
        return "";
    }

    $invoiceId = $invoiceItem->invoiceid;

    if(($_POST['tsRefund'] ?? '') === "true")
    {
        check_token('WHMCS.admin.default');

        $transactionId = (int) ($_POST['transactionId'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $refundType = (string) ($_POST['refundType'] ?? '');

        if($transactionId <= 0 || $amount <= 0 || $refundType === '')
        {
            header('Location: clientsservices.php?userid=' . $userId . '&productselect=' . $serviceId . '&tsRefundStatus=error2');
            exit();
        }

        try
        {
            $api = new ModulesGarden\TuringSign\Api\TlsManagerApi($product->configoption1, $product->configoption2, $product->configoption3);
            $api->refund($sslOrder->remoteid);
        }
        catch(Exception $e)
        {
            \logModuleCall('TuringSign', 'Refund Failed', 'Service ID: ' . $serviceId, $e->getMessage(), $e->getMessage());

            header('Location: clientsservices.php?userid=' . $userId . '&productselect=' . $serviceId . '&tsRefundStatus=error1');
            exit();
        }

        try
        {
            $result = refundInvoicePayment(
                $transactionId,
                $amount,
                $refundType == 'sendtogateway',
                $refundType == 'addascredit',
                !empty($_POST['sendEmail']),
                (string) ($_POST['refundTransactionId'] ?? ''),
                !empty($_POST['reverse'])
            );

            if($result != 'creditsuccess' && $result != 'manual')
            {
                throw new Exception($result);
            }

            header('Location: clientsservices.php?userid=' . $userId . '&productselect=' . $serviceId . '&tsRefundStatus=success');
            exit();
        }
        catch(Exception $e)
        {
            \logModuleCall('TuringSign', 'Refund Failed', 'Service ID: ' . $serviceId, $e->getMessage(), $e->getMessage());

            header('Location: clientsservices.php?userid=' . $userId . '&productselect=' . $serviceId . '&tsRefundStatus=error2');
            exit();
        }
    }

    $transactions = Capsule::table('tblaccounts')
        ->where('invoiceid', '=', $invoiceId)
        ->where('amountin', '>', 0)
        //->where('type', '=', 'gateway_funds_in')
        ->orderBy('date', 'ASC')
        ->orderBy('id', 'ASC')
        ->get();

    $transactionsOptions = "";

    foreach($transactions as $transaction)
    {
        $transactionId = (int) $transaction->id;
        $transactionAmount = htmlspecialchars((string) $transaction->amountin, ENT_QUOTES, 'UTF-8');
        $transactionDate = htmlspecialchars((string) $transaction->date, ENT_QUOTES, 'UTF-8');
        $transactionTransId = htmlspecialchars((string) $transaction->transid, ENT_QUOTES, 'UTF-8');

        $transactionsOptions .= <<<HTML
<option value="{$transactionId}" data-amount="{$transactionAmount}">{$transactionDate} | {$transactionTransId} | {$transactionAmount}</option>
HTML;

    }

    $alert = "";

    if($refundStatus == 'success')
    {
        $alert = <<<HTML
<div id="turingSignAlert" class="alert alert-success">
Refund completed successfully.
</div>
<script>
$(document).ready(function(){
    $('#turingSignAlert').insertBefore($('#frm1'));
});
</script>
HTML;

    }

    if($refundStatus == 'error1')
    {
        $alert = <<<HTML
<div id="turingSignAlert" class="alert alert-danger">
API refund request failed. See module logs for detailed error information.
</div>
<script>
$(document).ready(function(){
    $('#turingSignAlert').insertBefore($('#frm1'));
});
</script>
HTML;
    }

    if($refundStatus == 'error2')
    {
        $alert = <<<HTML
<div id="turingSignAlert" class="alert alert-danger">
Internal WHMCS refund execution failed. See module logs for detailed error information.
</div>
<script>
$(document).ready(function(){
    $('#turingSignAlert').insertBefore($('#frm1'));
});
</script>
HTML;
    }

    $tokenInput = generate_token();

    return <<<HTML
{$alert}
<div class="modal fade" id="refundModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="refundForm" action="clientsservices.php?userid={$userId}&productselect={$serviceId}">
                {$tokenInput}
                <div class="modal-header card-header">
                    <h4 class="modal-title">
                        Refund Certificate
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-left">
                    <input type="hidden" name="tsRefund" value="true">
                    <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
                        <tbody>
                            <tr>
                                <td width="20%" class="fieldlabel">Transactions</td>
                                <td class="fieldarea">
                                    <select id="transactionId" name="transactionId" class="form-control" style="width: 100%;">
                                        {$transactionsOptions}
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td width="20%" class="fieldlabel">Amount</td>
                                <td class="fieldarea">
                                    <div class="input-group input-300">
                                        <input type="text" name="amount" id="amount" class="form-control" placeholder="0.00">
                                        <span class="input-group-addon">Leave blank for full refund</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td width="20%" class="fieldlabel">Refund Type</td>
                                <td class="fieldarea">
                                    <select id="refundType" name="refundType" class="form-control">
                                        <option value="sendtogateway">
                                            Refund through Gateway (If supported by module)
                                        </option>
                                        <option>Manual Refund Processed Externally</option>
                                        <option value="addascredit">Add to Client's Credit Balance</option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="refundTransactionId">
                                <td width="20%" class="fieldlabel">Refund Transaction ID (Manual Refund)</td>
                                <td class="fieldarea">
                                    <input type="text" name="refundTransactionId" size="25" class="form-control">
                                </td>
                            </tr>
                            <tr>
                                <td width="20%" class="fieldlabel">Reverse Payment</td>
                                <td class="fieldarea">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="reverse" value="1">
                                        Undo automated actions triggered by this transaction - where possible.
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td width="20%" class="fieldlabel">Send Email</td>
                                <td class="fieldarea">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="sendEmail" checked="checked">
                                        Check to Send Confirmation Email
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Close
                    </button>
                    <button type="submit" class="btn btn-danger">
                        Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
$(document).ready(function () {
    function refundCertificateButton()
    {
        const button = $('#btnRefund_Certificate');
        button.removeAttr('onclick');
        
        button.on('click', function() {  
            $('#refundModal').modal('show');
        });
    }
    
    refundCertificateButton();
    
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if(node.id === "modcmdbtns") {
                    refundCertificateButton();
                }
            });
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    $('#refundForm').on('submit', function (e) {
       e.preventDefault();
       
       if($('#refundForm input#amount').val().trim() === ''){
            $('#refundForm input#amount').val($('#refundForm #transactionId option:selected').data('amount'));
       }
       
       $(this).off('submit').submit();
    });
});
</script>
HTML;

});

add_hook('ClientAreaPageProductsServices', 1, function ($params) {

    $services = $params['services'];

    foreach ($services as &$service)
    {
        if ($service['module'] != 'TuringSign')
        {
            continue;
        }

        $orderNumber = Capsule::table('tblorders')
            ->join('tblhosting', 'tblhosting.orderid', '=', 'tblorders.id')
            ->where('tblhosting.id', '=', $service['id'])
            ->first(['ordernum']);

        if ($orderNumber && !empty($orderNumber->ordernum))
        {
            $service['domain'] = 'TS-ORDER:' . $orderNumber->ordernum;
        }
    }

    return [
        'services' => $services,
    ];
});

add_hook('ClientAreaFooterOutput', 1, function ($params) {

    if ($params['filename'] != 'clientarea' || $params['action'] != 'services')
    {
        return "";
    }

    $lang = new \ModulesGarden\TuringSign\Helpers\Lang();

    $orderNumberLang = $lang->get('orderNumber');

    return <<<HTML
<style>
#tableServicesList a[href*="TS-ORDER:"]
{
    visibility: hidden;
}
</style>
<script>
$(document).ready(function () {
    $('#tableServicesList a[href*="TS-ORDER:"]').each(function () {
        let href = $(this).attr('href');
        let order = href.split('TS-ORDER:')[1];
        
        $(this).text('{$orderNumberLang}' + order).removeAttr('href').css('visibility', 'visible');
    });
});
</script>
HTML;

});

add_hook('ShoppingCartValidateProductUpdate', 1, function ($params) {
    $certificates = 0;

    $configOptions = $params['configoption'];

    foreach ($configOptions as $key => $configOption)
    {
        $configOptionDb = Capsule::table('tblproductconfigoptions')
            ->where('id', '=', $key)
            ->where(function ($query) {
                $query->where('optionname', 'LIKE', 'standardDomains|%')
                    ->orWhere('optionname', 'LIKE', 'wildcardDomains|%');
            })
            ->first();

        if (!$configOptionDb)
        {
            continue;
        }

        $certificates += $configOption;
    }

    if ($certificates > 250)
    {
        $lang = new \ModulesGarden\TuringSign\Helpers\Lang();

        return [
            $lang->get('totalCertificatesLimitExceeded')
        ];
    }
});
/**
 * The sidebar "Configure Now" item is a module CustomAction rendered with
 * href="#" and handled by the active theme's JavaScript. Some themes (for
 * example Lagom 2) bind that handler to event.target instead of the <a>,
 * so a click on the icon or label does nothing. Take the item over and send
 * the customer straight to the module's own configuration wizard.
 */
add_hook('ClientAreaFooterOutput', 1, function ($params) {

    $filename = $params['filename'] ?? '';
    $action = $params['action'] ?? ($_GET['action'] ?? '');

    if ($filename != 'clientarea' || $action != 'productdetails')
    {
        return "";
    }

    $serviceId = (int) ($_GET['id'] ?? 0);

    if ($serviceId <= 0)
    {
        return "";
    }

    $configurationUrl = 'clientarea.php?action=productdetails&id=' . $serviceId . '&ts-action=stepOne';

    return <<<HTML
<script>
jQuery(document).ready(function ($) {
    var configureAction = $('#Primary_Sidebar-Service_Details_Actions-TuringSign_Configure');

    if (!configureAction.length) {
        return;
    }

    configureAction.off('click').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        window.location.href = '{$configurationUrl}';
    });
});
</script>
HTML;

});

/**
 * WHMCS sends the "TuringSign Send Configuration Link" template twice when a
 * service is created: once from the module (with the sslConfigurationLink
 * custom variable) and once as the product's automatic welcome email (without
 * it). The second copy therefore has an empty "secure link".
 *
 * Fill the merge field whenever it is missing or empty, so every copy of the
 * email contains a working configuration link. The module's own email already
 * carries the value and is left untouched.
 */
add_hook('EmailPreSend', 1, function ($vars) {

    if (($vars['messagename'] ?? '') !== 'TuringSign Send Configuration Link')
    {
        return [];
    }

    $mergeFields = $vars['mergefields'] ?? [];

    if (!empty($mergeFields['sslConfigurationLink']))
    {
        return [];
    }

    $serviceId = (int) ($vars['relid'] ?? 0);

    if ($serviceId <= 0)
    {
        $serviceId = (int) ($mergeFields['service_id'] ?? 0);
    }

    if ($serviceId <= 0)
    {
        return [];
    }

    $isTuringSign = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
        ->where('tblhosting.id', '=', $serviceId)
        ->where('tblproducts.servertype', '=', 'TuringSign')
        ->exists();

    if (!$isTuringSign)
    {
        return [];
    }

    $systemUrl = Capsule::table('tblconfiguration')
        ->where('setting', '=', 'SystemURL')
        ->value('value');

    return [
        'sslConfigurationLink' => rtrim((string) $systemUrl, '/')
            . '/clientarea.php?action=productdetails&id=' . $serviceId . '&ts-action=stepOne',
    ];
});
