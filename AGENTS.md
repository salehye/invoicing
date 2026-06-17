# AGENTS.md

## Package

`salehye/invoicing` — standalone Laravel invoicing library (PHP 8.2+, Laravel 11/12/13).
Namespace: `Salehye\Invoicing\` (PSR-4 from `src/`). Tests: `Salehye\Invoicing\Tests\` (from `tests/`).

## Commands

```bash
composer install          # install deps
composer test             # runs vendor/bin/phpunit
vendor/bin/phpunit        # run all tests
vendor/bin/phpunit tests/Unit/InvoiceManagerTest.php  # single file
vendor/bin/phpunit --filter testMethodName             # single test
```

No lint, typecheck, or codegen scripts. Tests are the only verification step.

## Test Infrastructure

- **Framework**: Orchestra Testbench (`tests/TestCase.php` extends it) — no full Laravel app needed.
- **Migrations**: package migrations in `database/migrations/` (3 files: invoices, invoice_lines, payments). Test-specific migrations in `tests/migrations/` (adds `customers` table for polymorphic billable).
- **PHPUnit config**: `phpunit.xml` — single test suite `Package` covering `tests/`.
- **Test model**: `tests/Models/Customer.php` is used as the user model in tests.

## Architecture

Entry points and data flow:

- **ServiceProvider** (`src/InvoicingServiceProvider.php`): registers singletons, gateways, middleware alias (`invoice.paid`), artisan command, publishable config & migrations.
- **Facade** `Invoicing` → resolves to `InvoiceManager`.
- **InvoiceManager**: invoice CRUD, status transitions, line item management, totals recalculation.
- **PaymentProcessor**: payment recording, verification, webhook handling, refunds. Depends on `GatewayManager` and `InvoiceManager`.
- **GatewayManager**: registry of payment gateways. Built-in: `local`, `stripe`, `bank_transfer`. Custom gateways via config or runtime `register()`.
- **TotalCalculator**: computes subtotal/discount/tax/total. Used by `InvoiceManager`.
- **InvoiceNumberGenerator**: collision-safe unique number generation.

Models: `Invoice`, `InvoiceLine`, `Payment` — all use `$fillable` (no `$guarded = []`).
Enums: `InvoiceStatus`, `PaymentStatus`, `DiscountType` — all backed enums with `canTransitionTo()`.
Events: `InvoiceCreated`, `InvoiceUpdated`, `InvoicePaid`, `InvoiceCanceled`, `InvoiceRefunded`, `PaymentSucceeded`, `PaymentFailed`, `PaymentVerified` — all use `public readonly` properties.
Exceptions: 5 domain exceptions (`InvoiceStatusTransitionException`, `PaymentStatusTransitionException`, `PaymentVerificationException`, `GatewayNotFoundException`, `InvalidPaymentAmountException`).

## Key Conventions

- `discount_type` is **required** when `discount > 0` — throws `InvalidArgumentException` otherwise.
- FKs use `restrictOnDelete` (no cascade) to preserve audit trail.
- `user_id` and `verified_by` are `foreignId` without `constrained()` — target user table is configurable.
- Invoice status lifecycle: `draft → unpaid → paid → refunded` / `canceled` / `overdue`.
- Payment status lifecycle: `pending → success/failed`, `awaiting_verification → success/failed`, `success → refunded`.
- Middleware `invoice.paid` is auto-registered by ServiceProvider (no manual registration needed).
- Config publish tag: `invoicing-config`, migrations tag: `invoicing-migrations`.

## Scope

This is a library package, not a standalone app. It lives inside a monorepo at `packages/billing-core` with a sibling `packages/subscription`. Do not create Laravel app scaffolding here.
