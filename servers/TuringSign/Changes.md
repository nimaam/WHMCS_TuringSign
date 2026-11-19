# TuringSign WHMCS Module — Changes

## 1.0.7.5 — 2026-11-19

### Fixed — TuringSign SSL dashboard page error

The dashboard SSL tab could fail with a PHP `TypeError` because the helper
called `array_map()` on Laravel `Collection` objects returned by the query
builder:

```
array_map(): Argument #2 ($array) must be of type array,
Illuminate\Support\Collection given
```

This affected the first page load of **Addons → TuringSign SSL** (the default
SSL tab).

**Fix:** `getOrders()`, `getProducts()` and `getQueueJobs()` now return plain
arrays (`->get()->all()`), so the cache refresh, view data and template loops
receive the expected types. The addon's PHP/JS syntax and the three tab
templates were re-verified.

## 1.0.7.4 — 2026-11-19

### Added — TuringSign SSL addon dashboard

Added a new WHMCS addon at `modules/addons/TuringSignSSL`, available under
**Addons → TuringSign SSL**.

The dashboard uses the TuringSign logo and has three tabs:

#### 1. SSL Certificates

Lists every SSL order whose `tblsslorders.module` is `TuringSign` with:

- certificate domain / common name
- client name (company name when set, otherwise first + last name)
- product name
- valid from / valid to
- status (TuringSign API status where available, otherwise the local order status)
- actions: **Revoke**, **Cancel**, **Renew**

Details:

- **Revoke** calls the TuringSign API `revokeCertificate()` and marks the local
  order `Revoked`.
- **Cancel** sets the service status and the local SSL order status to
  `Cancelled`. The certificate itself is not revoked; it remains valid until
  expiry.
- **Renew** uses the module's own `Actions\Renew` class: the order becomes
  `Awaiting Renew` and the customer receives the configuration email.
- Valid from/to and the API status are cached in
  `mod_turingsign_ssl_cache` for 15 minutes and refreshed on page load (up to
  15 orders) and by cron (up to 20 per run).

#### 2. Reports

Period selector:

- Daily (today)
- Weekly (last 7 days)
- Monthly (last 30 days)
- From / To (custom range)

The report includes:

- total orders and total amount
- amount and order count per TuringSign product
- SSL status breakdown
- a doughnut (circle) chart for SSL statuses
- a doughnut (circle) chart for amount by product

Data is based on `tblhosting.regdate` for the selected period, joined to the
TuringSign SSL order and its cached API status.

#### 3. Tools

- **Run configuration** — runs
  `php -q modules/servers/TuringSign/configure.php` and shows the exit code and
  output. This creates or updates the module's email templates and client
  custom fields.
- **Synchronize products** — loads the WHMCS products whose server module is
  `TuringSign`, lets you select one, and queues a catalog synchronization. The
  job is stored in `mod_turingsign_ssl_sync_jobs` and runs on the next WHMCS
  cron (`AfterCronJob`), using the selected product's API URL, Application ID
  and Secret Key.
- The **Sync queue** table shows the last 25 jobs with status, timestamps and
  error message.

### Database

The addon activation creates:

- `mod_turingsign_ssl_sync_jobs` — queued product synchronizations
- `mod_turingsign_ssl_cache` — cached API status and certificate validity

### Security

- Every POST action requires the WHMCS admin CSRF token
  (`generate_token()` / `check_token('WHMCS.admin.default')`).
- All IDs are cast to integers; all output is HTML-escaped.
- Revoke/cancel/renew only operate on rows whose module is `TuringSign`.
- The configuration tool executes a fixed path with `escapeshellarg()` and is
  available only inside the admin addon.
- Actions are written to the WHMCS activity log.

### Activation

Install the files, then activate **Addons → TuringSign SSL** once. Activation
creates the two tables. No server-module reconfiguration is required.

### Files

```
addons/TuringSignSSL/TuringSignSSL.php
addons/TuringSignSSL/hooks.php
addons/TuringSignSSL/whmcs.json
addons/TuringSignSSL/logo.png
addons/TuringSignSSL/lib/Helper.php
addons/TuringSignSSL/lib/Cron.php
addons/TuringSignSSL/templates/dashboard.php
addons/TuringSignSSL/assets/css/dashboard.css
addons/TuringSignSSL/assets/js/dashboard.js
addons/TuringSignSSL/assets/js/chart.umd.min.js
```

## 1.0.7.3 — 2026-11-19

### Security fixes

#### S1 — Reflected XSS in the admin refund form via `userid` (Medium)

The admin refund form built its action from the raw `userid` GET parameter:

```php
<form ... action="clientsservices.php?userid={$_GET['userid']}&productselect={$serviceId}">
```

and the same value was used in the redirects after a refund. A crafted
`userid` containing quotes/markup could be reflected into the admin page.

**Fix:** `userid` is now cast to an integer once (`$userId = (int) ($_GET['userid'] ?? 0)`) and only that integer is used in the form action and redirects. `serviceId` was already an integer. Transaction option values (`transid`, date, amount) are HTML-escaped before being written to the select.

#### S2 — Missing CSRF token on the admin Refund action (Medium)

The refund POST (`tsRefund=true`) was processed in `AdminAreaFooterOutput`
without a token check, so a third-party page could submit a forged refund
request for a logged-in admin.

**Fix:**

- The form now contains `generate_token()` (WHMCS CSRF hidden input).
- The handler calls `check_token('WHMCS.admin.default')` before doing any work.
- `transactionId`, `amount` and `refundType` are validated/cast before they reach `refundInvoicePayment()`; `sendEmail` and `reverse` use `!empty()`.

#### S3 — Raw `$_GET['id']` in the client sidebar URI (Low)

The sidebar hook used the raw GET value both in the database lookup and in the
generated URI:

```php
->where('tblhosting.id', '=', $_GET['id'])
...
'uri' => 'clientarea.php?action=productdetails&id=' . $_GET['id'],
```

A crafted `id` could match a real service through MySQL type coercion and be
reflected into the `href` of the "Information" item (client-area XSS), and
`$_GET['ts-action']` was read without a default.

**Fix:**

- `id` is cast to an integer (`$serviceId = (int) ($_GET['id'] ?? 0)`); the
  hook exits when it is `0`.
- `action` and `ts-action` use `?? ''` and the step values are checked with
  `in_array(..., true)`.
- The same integer cast and guard are applied to the three `ClientAreaPage`
  step hooks.

### Notes

- No database migration is required.
- The changes are contained in `hooks.php`; `Changes.md`, `whmcs.json` and
  `composer.json` are updated to 1.0.7.3.

## 1.0.7.2 — 2026-11-19

### Summary

Fixed the "Oops!" page (HTTP 500) on the WHMCS service page
(`clientarea.php?action=productdetails&id=<serviceid>`) after an SSL order has
been configured. The certificate order itself was healthy — it was in
`Configuration Submitted`, waiting for domain validation — but the module's
overview page crashed while rendering it.

### Finding — product details crashes once `remoteid` exists

Before Step 1 is submitted, the SSL order has no `remoteid`, so the overview
(`Actions/ClientArea.php::index()`) skips the TuringSign API and the page
renders fine.

After Step 1, the order gets a `remoteid` and status
`Configuration Submitted`. `index()` then calls
`TlsManagerApi::getOrder($remoteid)` and read the response as if it were
complete:

```php
$certificateStatus = strtoupper($resultGetOrder['order_status']);
$subjectAlternativeNames = implode(', ', $resultGetOrder['order_certificate_info']['sans']['dns']);
...
if (in_array('*.'.$info['domain_name'], $resultGetOrder['order_certificate_info']['sans']['dns']))
```

For an order that has not been issued yet, `order_certificate_info` (and in
particular `sans.dns`) can be missing or `null`. `implode(', ', null)` is a
PHP `TypeError` in PHP 8, and `in_array(..., null)` is another one. The module
wrappers only caught `Exception`, not `Error`/`TypeError`, so WHMCS showed the
generic "Oops!" page.

The UltaHost SSL page
(`index.php?m=ultahost_improvements&page=ssl&cert=5256`) was not affected
because it reads the order through its own code path; only the WHMCS service
page crashed.

### Fix

- `Actions/ClientArea.php::index()`:
  - wraps the `getOrder()` call in `try/catch (\Throwable)`;
  - treats non-array responses as "no data";
  - defaults `order_certificate_info`, `sans`, `dns`, `domain_validation_info`
    and every field to safe values;
  - only calls `implode()` / `in_array()` when the value is an array;
  - skips validation entries that are not arrays;
  - guards `$_GET['ts-action']` and the `customfields['domain']` access.
- `Actions/CustomActions.php`: the same null-safe handling for the order status
  used by the Actions menu.
- `TuringSign.php`: `TuringSign_ClientArea()` and
  `TuringSign_CustomActions()` now catch `\Throwable` as a final safety net, so
  a module error can never take down the whole service page with an Oops page.

### Verification

1. Open the service page
   `clientarea.php?action=productdetails&id=<serviceid>` for an order in
   `Configuration Submitted`.
   Expected: the page renders, shows the status, and no "Oops!" page.
2. Open the UltaHost SSL page for the same certificate.
   Expected: unchanged, shows `Configuration Submitted` and the validation
   instructions.
3. Submit Step 1 on a fresh order, then open the service page again.
   Expected: no 500 before or after the order gets a `remoteid`.

### Module logo

Added `logo.png` (TuringSign logo, 247 × 258 PNG) to the module root.
`whmcs.json` already references `logo.png`, so WHMCS now displays the module
logo in the server-module list.

## 1.0.7.1 — 2026-11-19

### Summary

Fixed the SSL order configuration entry points for services using the TuringSign
server module. Customers could not start the certificate configuration from the
SSL certificate page ("Set it up now") or from the service sidebar ("Configure
Now"):

- **Set it up now** opened WHMCS's core SSL wizard (`configuressl.php`), which
  crashed with HTTP 500 and the generic "Oops!" page.
- **Configure Now** was rendered as a JavaScript custom action with `href="#"`;
  depending on the active theme (observed on Lagom 2) the click did nothing.

Both now lead to the module's own, working configuration wizard
(`clientarea.php?action=productdetails&id=<serviceid>&ts-action=stepOne`).

### Findings

#### F1 — "Set it up now" returns the "Oops!" page (HTTP 500)

Reproduced on production WHMCS 8.13.7 with:

- Client: `1002`
- Service: `271682` (`TuringSign Basic DV`, domain `ultacp.com`)
- SSL order (`tblsslorders.id`): `5256`
- Button target:
  `configuressl.php?cert=8819159f9246232ed1299a7414448ab4`

`cert=5256` in the UltaHost SSL page is the `tblsslorders` row ID. The button
URL is built by hashing it: `md5('5256') = 8819159f9246232ed1299a7414448ab4`.

WHMCS resolves that hash back to the SSL order (module `TuringSign`) and then,
for step 1, unconditionally calls the module's core SSL wizard function:

```php
// configuressl.php (WHMCS 8.13)
if ($step == 1) {
    $result = call_user_func($module . "_SSLStepOne", $params);
}
```

The TuringSign module did not define `TuringSign_SSLStepOne()` (nor
`_SSLStepTwo`, `_SSLStepThree`, `_SSLConfig`). PHP therefore raised a fatal
"call to undefined function" error, WHMCS returned HTTP 500 and displayed:

> Oops! Something went wrong and we couldn't process your request.

Verification:

| URL | Result |
| --- | --- |
| `configuressl.php?cert=8819159f9246232ed1299a7414448ab4` | HTTP 500 (Oops page) |
| `clientarea.php?action=productdetails&id=271682&ts-action=stepOne` | HTTP 200, Step 1 form renders |

The module's own wizard was working all along; only the WHMCS core entry point
was broken.

#### F2 — "Configure Now" in the service sidebar does nothing

The item is generated by `TuringSign_CustomActions()` as the custom action
`TuringSign_Configure`. WHMCS renders it as:

```html
<a href="#" data-active="1"
   data-identifier="TuringSign_Configure"
   data-serviceid="271682">Configure Now</a>
```

and expects the theme's JavaScript to POST to
`/clientarea/service/<id>/custom-action/TuringSign_Configure`, then follow the
`redirectTo` returned by the module (which is the correct Step 1 URL).

The Lagom 2 handlers (`whmcs-custom.min.js` and `scripts.min.js`) use
`jQuery(event.target)` instead of the clicked anchor (and call `.closest()` on
the boolean returned by `customActionAjaxCall()`), so clicks on the icon or the
label can be swallowed and the AJAX call never runs. The server-side action
itself is correct.

#### F3 — Duplicate "Action required" email with a missing link

When the service was created, two "Action required: configure your SSL
certificate order" emails were sent:

- `13:32:59` — contains the correct configuration link
  (`...&ts-action=stepOne`) and is sent by the module.
- `13:33:00` — same text, but the link is empty. This is WHMCS's automatic
  product welcome email for the same template; it is sent without the module's
  `{$sslConfigurationLink}` custom variable.

Only one template has that subject (`TuringSign Send Configuration Link`,
template ID 723), so both copies use the same template. The module always
passes `sslConfigurationLink`; the automatic welcome email does not.

### Fixes

#### 1. `TuringSign.php`

Added the WHMCS core SSL wizard entry points. They hand the customer over to
the module's own multi-step wizard instead of letting WHMCS run its generic
CSR/approver-email flow:

- `TuringSign_SSLStepOne()`
- `TuringSign_SSLStepTwo()`
- `TuringSign_SSLStepThree()`
- `TuringSign_SSLConfig()`

All four redirect (HTTP 302) to:

```
clientarea.php?action=productdetails&id=<serviceid>&ts-action=stepOne
```

This fixes "Set it up now" from the SSL certificate page, the WHMCS core SSL
page and any other link that uses `getConfigurationUrl()`.

#### 2. `hooks.php`

Added a `ClientAreaFooterOutput` hook for the product details page. It takes
over the `#Primary_Sidebar-Service_Details_Actions-TuringSign_Configure` item
and navigates directly to the Step 1 URL, so the action no longer depends on
the theme's custom-action JavaScript. The hook only acts when that item is
present (active service with an `Awaiting Configuration` SSL order), so it has
no effect on other services or products.

#### 3. `templates/client/index.tpl`

Added a real "Configure Now" button to the module's service page when the order
is still awaiting configuration (`!$certificateStatus`). This gives customers a
working entry point even if a theme does not render or handle custom actions
correctly.

#### 4. `hooks.php` — configuration email merge field

Added an `EmailPreSend` hook for the `TuringSign Send Configuration Link`
template. When the email is sent without `sslConfigurationLink` (the automatic
product welcome copy), the hook builds the same link the module uses and adds
it as a merge field:

```
<SystemURL>/clientarea.php?action=productdetails&id=<serviceid>&ts-action=stepOne
```

The module's own email already carries the value, so it is left unchanged. The
hook only runs for services whose product uses the `TuringSign` server module.

This also covers the admin-side **Resend Welcome Email** action: it sends the
product's welcome template (the same `TuringSign Send Configuration Link`
template) through `sendMessage()`, so `EmailPreSend` fills the missing link
there as well. If the product's welcome email is set to **None**, WHMCS hides
the "Resend Welcome Email" button; in that case use **Send Message** and pick
the `TuringSign Send Configuration Link` template, which goes through the same
hook.

#### 5. Version metadata

- `whmcs.json`: added `"version": "1.0.7.1"`.
- `composer.json`: added `"version": "1.0.7.1"`.

### Files changed

```
TuringSign.php
hooks.php
templates/client/index.tpl
whmcs.json
composer.json
Changes.md
```

No database changes are required. Existing `tblsslorders` rows created by
earlier versions are handled by the new functions without re-provisioning.

### Deployment

1. Upload the archive to the WHMCS root and extract it there. The archive
   already contains the `modules/servers/TuringSign/` path, so the files land
   in the correct place:
   `modules/servers/TuringSign/TuringSign.php`,
   `modules/servers/TuringSign/hooks.php`,
   `modules/servers/TuringSign/templates/client/index.tpl`, etc.
2. If OPcache is configured with `validate_timestamps=0`, restart PHP-FPM so
   the new functions are loaded.
3. No module re-activation or product re-configuration is needed.

### Verification

1. Open the SSL certificate page and click **Set it up now**.
   Expected: redirect to
   `clientarea.php?action=productdetails&id=<serviceid>&ts-action=stepOne` and
   the Step 1 (CSR + certificate holder) form is shown, with no "Oops!" page.
2. On the service's Product Details page, click **Configure Now** in the
   Actions sidebar.
   Expected: the same Step 1 form.
3. Open the service's module tab.
   Expected: a "Configure Now" button that opens the same Step 1 form.
4. Create a test TuringSign order (or resend the template from **Utilities >
   System > Email** for an existing service).
   Expected: every copy of "Action required: configure your SSL certificate
   order" contains a working `...&ts-action=stepOne` link.

### Recommended follow-up

- **Duplicate email (optional):** if you want customers to receive only one
  copy, set the TuringSign product's automatic welcome email to **None**
  (Configuration > System Settings > Products/Services > TuringSign product >
  Details/Other tab > Welcome Email). The module's own email is still sent and
  already contains the link. With the hook above, both copies are correct if
  you prefer to keep the welcome email.
- **Vendor update:** report the missing `_SSLStepOne` compatibility layer to
  ModulesGarden. If the module is updated from the vendor, re-apply the
  functions (or confirm the vendor has added them) so the "Set it up now" page
  does not regress.
- **Theme JavaScript (optional):** Lagom 2's custom-action handler should use
  `jQuery(this)` / `jQuery(event.target).closest('a')` instead of
  `jQuery(event.target)`. The hook added here makes the TuringSign action work
  regardless, but other modules using custom actions are still affected.
