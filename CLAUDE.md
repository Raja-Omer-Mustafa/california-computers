# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a POS / inventory / accounting web app built on **UltimatePOS** (Laravel 9, PHP ^8.0, MySQL). It is a server-rendered Blade + jQuery app (AdminLTE, DataTables via yajra); there is no Node build — the compiled JS in `public/js/` is committed and edited directly.

## Commands

```bash
composer install
php artisan migrate:fresh && php artisan db:seed --force   # fresh database
php artisan serve

# If you hit "directory not found" errors for views/sessions/cache:
mkdir -p storage/framework/{views,sessions,cache}

# Backups (custom commands in app/Console/Commands)
php artisan db:backup-dropbox            # needs Dropbox app with files.content.write and a "database-backups" folder
php artisan db:backup-external [db]      # path configured in .env

# Tests (PHPUnit; suites are near-empty scaffolding)
php artisan test
php artisan test --filter=SomeTest
```

Code style: `.php_cs` (PSR-2, short arrays, alphabetized imports, no unused imports). php-cs-fixer is not a composer dependency — run it with a global install if needed.

Scheduled jobs (`app/Console/Kernel.php`) run only when `APP_ENV=live` (backups, `pos:generateSubscriptionInvoices`, `pos:updateRewardPoints`, `pos:autoSendPaymentReminder`). `pos:dummyBusiness` runs under `APP_ENV=demo` and **wipes all business data** — never run it against a real database.

## Architecture

**Multi-tenancy by `business_id`.** Every tenant-scoped table has a `business_id` column. Controllers read it from the session (`request()->session()->get('user.business_id')`) and must filter every query by it. `SetSessionData` middleware populates `user`, `business`, `currency`, and `financial_year` in the session on first authenticated request.

**Fat utils, thin-ish controllers.** Business logic lives in `app/Utils/` and is injected into controllers via constructor:
- `TransactionUtil` (~6.5k lines) — sells, purchases, returns, payments, stock mapping, invoice numbering, ledger/balance-due.
- `ProductUtil` — stock levels, variations, location stock (`VariationLocationDetails`).
- `Util` (base class) — number/date formatting (`num_uf`, `num_f`, `format_date`, `uf_date`), reference counts, notifications.
- `BusinessUtil`, `ModuleUtil`, `CashRegisterUtil`, `ContactUtil`, `NotificationUtil`, etc.

Search these before writing new logic; most domain operations already exist. Global helpers are in `app/Http/helpers.php` (autoloaded).

**Transactions table is polymorphic by `type`.** `App\Transaction` stores sells, purchases, returns, expenses, stock transfers/adjustments, opening stock, sales/purchase orders, etc., distinguished by `type` and `status`. Line items are in `transaction_sell_lines` / `purchase_lines`; `transaction_sell_lines_purchase_lines` maps sold quantity to purchase lots (FIFO/LIFO costing).

**Models live directly in `app/`** (e.g. `App\Product`, `App\Transaction`), not `app/Models`.

**Permissions** use spatie/laravel-permission with string abilities like `brand.view`, `sell.create`; controllers check `auth()->user()->can(...)` and abort 403. Roles are per-business (`Admin#{business_id}`).

**Routes** are almost all in `routes/web.php` inside the middleware group `['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`. `AdminSidebarMenu` builds the sidebar with nwidart/laravel-menus. `routes/install_r.php` handles the web installer.

**Modules (nwidart/laravel-modules).** `modules_statuses.json` lists UltimatePOS add-ons (Essentials, Accounting, Superadmin, Crm, Repair, Woocommerce, …), but the `Modules/` directory is not in this repo. Core code calls into modules via `ModuleUtil::getModuleData($fn)`, which invokes `Modules\<Name>\Http\Controllers\DataController::$fn()` on each installed module; always guard with `isModuleInstalled()` / `hasThePermissionInSubscription()`.

**Business settings are JSON blobs.** `business.pos_settings`, `common_settings`, `email_settings`, etc. are JSON columns, and keys may be missing on older businesses. Always use null-coalescing (`$pos_settings['key'] ?? default`) — "Undefined array key" errors from missing setting keys are a recurring bug class here.

**Frontend.** Views in `resources/views/<feature>/`, with page JS in `public/js/` (`pos.js`, `purchase.js`, `functions.js`, `common.js`). Translations are in `lang/<locale>/` (PHP) and `public/js/lang/<locale>.js` (JS).

## Git workflow

Branch from `main` as `hotfix/<name>` or `feature/<name>` and merge via PR.
