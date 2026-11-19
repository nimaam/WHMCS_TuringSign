<p align="left">
  <a href="https://turingsign.com">
    <img src="servers/TuringSign/logo.svg" alt="TuringSign Logo" width="300">
  </a>
</p>

# TuringSign WHMCS Module

## Overview

The **TuringSign** WHMCS module integrates WHMCS with the TuringSign SSL/TLS
platform. It automates ordering, configuration, domain validation, issuance,
renewal and revocation of TuringSign certificates, and exposes the certificate
lifecycle in the WHMCS client area and admin area.

Starting with **1.0.7.4**, the package also includes the **TuringSign SSL**
admin dashboard addon (`Addons → TuringSign SSL`).

## Features

### Server module (`modules/servers/TuringSign`)

- TuringSign product catalog synchronization
- Multi-step certificate configuration (CSR, subscriber/contact, domain
  validation)
- DNS TXT, DNS CNAME, email and HTTP-file domain validation
- Automatic order placement with the TuringSign API
- Certificate download, installation and resend
- Reissue and revoke actions
- Renewal workflow and configuration email
- WHMCS core SSL entry points (`_SSLStepOne`, `_SSLStepTwo`, `_SSLStepThree`,
  `_SSLConfig`) redirect to the module's own wizard
- Custom client-area actions and sidebar integration

### Dashboard addon (`modules/addons/TuringSignSSL`)

Available under **Addons → TuringSign SSL**, with three tabs:

1. **SSL Certificates** — list of TuringSign SSL orders with domain, client,
   product, valid from, valid to, status and the actions **Revoke**, **Cancel**
   and **Renew**.
2. **Reports** — daily, weekly, monthly and custom from/to periods; order and
   amount totals per product; SSL status breakdown; doughnut (circle) charts
   for product amounts and SSL statuses.
3. **Tools** — run `configure.php` from the dashboard and queue a product
   catalog synchronization for the next WHMCS cron. The product is selected
   from the WHMCS products that use the TuringSign module, so the API URL,
   Application ID and Secret Key do not have to be entered manually.

The dashboard uses the same **OX-WHMCS / Layers** theme as the OpenXchange
dashboard (`lu-widget`, `lu-table`, `lu-btn`, `lu-label`, OX report cards and
pills). The header uses `logo.svg`, downloaded from:

```
https://turingsign.com/wp-content/uploads/2022/12/logosecured.svg
```

## Requirements

- **WHMCS**: 8.0 or later (tested on 8.13.x)
- **PHP**: 8.0 or higher
- **PHP extensions**: cURL, OpenSSL, JSON
- **Dependencies**: `phpseclib/phpseclib` ^3.0 (bundled in `vendor/`)
- **TuringSign Partner Account** with API URL, Application ID and Secret Key

## Installation

1. Upload the `modules` directory to the WHMCS root. It creates:
   - `modules/servers/TuringSign`
   - `modules/addons/TuringSignSSL`
2. In WHMCS admin, go to **System Settings → Addon Modules** (or **Addons**)
   and activate **TuringSign SSL**. Activation creates:
   - `mod_turingsign_ssl_sync_jobs`
   - `mod_turingsign_ssl_cache`
3. Configure the TuringSign server products with the API URL, Application ID
   and Secret Key in the product's module settings.
4. Open **Addons → TuringSign SSL** to use the dashboard.

If OPcache is configured with `validate_timestamps=0`, restart PHP-FPM after
uploading the files.

## Dashboard

### SSL Certificates

- Lists every `tblsslorders` row whose module is `TuringSign`.
- Shows the certificate domain/common name, client, product, validity dates and
  status.
- **Revoke** calls the TuringSign API and marks the local order `Revoked`.
- **Cancel** marks the service and SSL order `Cancelled`; the certificate stays
  valid until expiry.
- **Renew** runs the module's renewal flow: the order becomes `Awaiting Renew`
  and the customer receives the configuration email.
- Validity dates and API statuses are cached for 15 minutes and refreshed on
  page load and by cron.

### Reports

- Periods: **Daily** (today), **Weekly** (last 7 days), **Monthly** (last 30
  days), **From / To** (custom).
- Totals: orders, amount, products.
- Product table: orders and amount per TuringSign product.
- Status table and doughnut chart: SSL status distribution.
- Doughnut chart: amount by product.

### Tools

- **Run configuration** executes:

  ```bash
  php -q modules/servers/TuringSign/configure.php
  ```

  It creates/updates the module email templates and client custom fields and
  shows the exit code and output.

- **Synchronize products** queues a catalog synchronization for the next
  WHMCS cron. Select any WHMCS product that uses the TuringSign module; the job
  uses that product's API credentials. The CLI equivalent is:

  ```bash
  php -q modules/servers/TuringSign/syncProducts.php [apiUrl] [applicationId] [secretKey]
  ```

- The **Sync queue** table shows the last 25 jobs, their status and messages.

The addon processes up to 3 queued sync jobs and refreshes up to 20 stale
certificate statuses on each WHMCS cron run (`AfterCronJob`).

## Security issues found and fixed

### 1. Reflected XSS in the admin refund form via `userid` (Medium)

**Found:** the admin refund form and its redirects used the raw
`$_GET['userid']` value:

```php
<form ... action="clientsservices.php?userid={$_GET['userid']}&productselect={$serviceId}">
```

A crafted `userid` could break out of the HTML attribute when an administrator
opened the service page.

**Fixed:** `userid` is cast once to an integer
(`$userId = (int) ($_GET['userid'] ?? 0)`) and only that value is used in the
form action and redirects. Transaction values written to the select are
HTML-escaped.

### 2. Missing CSRF token on the admin Refund action (Medium)

**Found:** the refund POST (`tsRefund=true`) was processed without a token, so
a forged cross-site request could trigger a certificate refund and a WHMCS
invoice refund for a logged-in administrator.

**Fixed:** the refund form now includes `generate_token()` and the handler
calls `check_token('WHMCS.admin.default')` before doing any work.
`transactionId`, `amount` and `refundType` are validated/cast, and the
send-email/reverse flags use `!empty()`.

### 3. Raw `$_GET['id']` in the client sidebar URI (Low)

**Found:** the client sidebar hook used the raw `id` parameter in the database
lookup and in the generated URI. MySQL type coercion could match a real service
and the value was reflected into the sidebar `href`, allowing client-area XSS.
`$_GET['ts-action']` was also read without a default.

**Fixed:** `id` is cast to an integer and validated; `action` and `ts-action`
use safe defaults and a strict whitelist. The same integer guard was applied to
the Step 1/2/3 page hooks.

### Addon security

- All dashboard POST actions require the WHMCS admin CSRF token.
- IDs are cast to integers and all output is HTML-escaped.
- Revoke/cancel/renew only work on rows whose module is `TuringSign`.
- The configuration tool executes a fixed script path with `escapeshellarg()`.
- Dashboard actions are written to the WHMCS activity log.

## Changes

### 1.0.7.7

- Fixed the dashboard loading unstyled in the admin area: CSS, JavaScript and
  the logo now use an absolute `SystemURL`-based asset path instead of a path
  relative to the custom admin directory.
- Removed the `TuringSignSSL_sidebar()` callback that caused a literal `Array`
  in the admin sidebar.
- Set the addon metadata version to **1.0.7.7** to match the package.

### 1.0.7.6

- Restyled the dashboard SSL list and Reports tab with the same **OX-WHMCS /
  Layers** theme used by the OpenXchange dashboard (`lu-widget`, `lu-table`,
  `lu-btn`, `lu-label`, OX report cards and pills).
- Added the `logosecured.svg` TuringSign logo and use it in the dashboard
  header.
- Aligned the report layout with OX: period buttons, summary cards, product
  pie chart, product table with status columns, status pie chart and
  “Current totals” pills.
- Styled the Tools tab with the same widgets and tables.

### 1.0.7.5

- Fixed a `TypeError` on the first load of **Addons → TuringSign SSL** caused
  by `array_map()` being called on Laravel `Collection` objects. The dashboard
  queries now return plain arrays.

### 1.0.7.4

- Added the **TuringSign SSL** admin dashboard addon (SSL list, reports,
  tools).
- Added cached certificate status/validity data and a cron-driven product sync
  queue.
- Updated the README and module documentation.

### 1.0.7.3

- Fixed the admin `userid` reflected XSS.
- Added CSRF protection to the admin refund action.
- Fixed the client sidebar `id` reflection.
- Hardened the refund POST fields and transaction output.

### 1.0.7.2

- Fixed the service-page HTTP 500 after an SSL order was configured
  (`order_certificate_info`/`sans` can be missing while validation is pending).
- Added `\Throwable` guards around module rendering.
- Added the module logo.

### 1.0.7.1

- Added the missing `TuringSign_SSLStepOne/Two/Three/SSLConfig` functions so
  **Set it up now** no longer ends in the WHMCS "Oops!" page.
- Made the sidebar **Configure Now** action navigate directly to the module
  wizard.
- Added a working **Configure Now** button to the service page.
- Added the `EmailPreSend` fix for the product welcome email without a link.

See `servers/TuringSign/Changes.md` for the complete, detailed changelog.

## Documentation and support

- Installation and configuration guide:
  <https://turingsign.com/Installation-and-configuration-guide>
- TuringSign:
  <https://turingsign.com>
- Support: open a ticket from your WHMCS client area or contact TuringSign.
