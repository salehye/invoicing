# salehye/invoicing

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue?style=flat-square)](https://php.net)
[![Laravel 11|12|13](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red?style=flat-square)](https://laravel.com)
[![Tests](https://img.shields.io/badge/Tests-56%20pass%2C%20121%20assertions-green?style=flat-square)]()
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow?style=flat-square)](LICENSE)

A **standalone Laravel invoicing package** with multi-gateway payment support — create invoices, manage line items with taxes/discounts, and process payments through Stripe, bank transfer (with manual admin verification), local testing, or any custom gateway.

**Zero dependency on subscription billing packages** — use independently or alongside any subscription system.

---

## ✨ Features

- 🧾 Invoice creation for any billable entity (polymorphic) or standalone (no billable required)
- 📦 Line items with quantity, pricing, per-line discount & tax
- 💰 Percentage & fixed discounts via `DiscountType` backed enum
- 📊 Configurable tax calculation (VAT, sales tax, etc.)
- 🔢 Unique invoice number generation with collision-safe retry
- 🔄 Status lifecycle: `draft → unpaid → paid → refunded` / `canceled` / `overdue`
- ⏰ Overdue detection + `invoicing:mark-overdue` artisan command
- 💳 Multi-gateway payments (Stripe, Local, Bank Transfer with manual verification)
- 🔐 Payment amount validation (no negative or excessive amounts)
- ✅ Automatic invoice marking as paid when fully paid
- 📡 Laravel events with `readonly` immutable properties
- 🛡️ Custom exception hierarchy for domain-specific errors
- 🚪 Middleware to restrict routes by invoice payment status
- 🔗 `HasInvoices` trait for any Eloquent model
- 👤 User ID tracking on invoices & payments
- 🏢 Tenant ID support (multi-tenant)
- 🗑️ Soft deletes on invoices (audit trail)
- 🔒 `$fillable` mass-assignment security
- 🛡️ `restrictOnDelete` on FKs (no cascade deletes — preserves audit trail)
- 📝 Metadata JSON for extra data
- 🧩 Customizable table names, currency, and user model
- 🎯 Custom tax/discount calculator contracts

---

## 📦 Installation

```bash
composer require salehye/invoicing
```

### Publish Config & Migrations

```bash
php artisan vendor:publish --tag=invoicing-config
php artisan vendor:publish --tag=invoicing-migrations
php artisan migrate
```

### Environment Variables

```env
INVOICING_CURRENCY=SAR
INVOICING_GATEWAY=local

# Stripe (optional — requires stripe/stripe-php)
STRIPE_API_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Bank Transfer (optional)
BANK_NAME=Al Rajhi Bank
BANK_ACCOUNT_NAME=My Company
BANK_ACCOUNT_NUMBER=1234567890
BANK_IBAN=SA0380000000608010167519
BANK_SWIFT_CODE=RJHISARI
BANK_TRANSFER_INSTRUCTIONS=Transfer the amount and upload proof of payment.
```

---

## ⚙️ Configuration

Full config file at `config/invoicing.php`:

| Key                              | Default                      | Description                               |
| -------------------------------- | ---------------------------- | ----------------------------------------- |
| `currency`                       | `USD`                        | Default currency per invoice              |
| `invoice_number_format`          | `{prefix}-{year}-{sequence}` | Invoice number template                   |
| `invoice_number_prefix`          | `INV`                        | Invoice number prefix                     |
| `invoice_number_sequence_length` | `4`                          | Sequence digit count                      |
| `default_gateway`                | `local`                      | Default payment gateway                   |
| `gateways.*`                     | —                            | Per-gateway config (see Gateways section) |
| `default_tax_rate`               | `0`                          | Default tax % (0 = no tax)                |
| `overdue_threshold_days`         | `0`                          | Days past `due_at` before overdue         |
| `table_names.invoices`           | `invoices`                   | Invoices table name                       |
| `table_names.invoice_lines`      | `invoice_lines`              | Invoice lines table name                  |
| `table_names.payments`           | `payments`                   | Payments table name                       |
| `user_model`                     | `App\Models\User`            | User model for relationships              |

---

## 🚀 Quick Start

### Step 1: Make a Model Billable

```php
use Salehye\Invoicing\Traits\HasInvoices;

class Customer extends Model
{
    use HasInvoices;
}
```

This adds: `invoices()`, `unpaidInvoices()`, `paidInvoices()`, `overdueInvoices()`, `totalInvoiceBalance()`

### Step 2: Create an Invoice

```php
use Salehye\Invoicing\Facades\Invoicing;

$invoice = Invoicing::create([
    'billable'  => $customer,
    'title'     => 'Order #123',
    'currency'  => 'SAR',
    'due_at'    => now()->addDays(14),
    'user_id'   => auth()->id(),       // optional: track who created it
    'items'     => [
        ['description' => 'Product A', 'quantity' => 2, 'unit_price' => 150],
        ['description' => 'Product B', 'quantity' => 1, 'unit_price' => 300],
    ],
]);

// $invoice->subtotal = 600 (150*2 + 300)
// $invoice->total   = 600
// $invoice->status  = Draft
// $invoice->number  = "INV-2025-0001"
```

### Step 3: Issue the Invoice

```php
Invoicing::markAsIssued($invoice);
// draft → unpaid, issued_at is set
```

### Step 4: Process Payment

```php
use Salehye\Invoicing\Services\PaymentProcessor;

$processor = app(PaymentProcessor::class);

// Record a payment
$payment = $processor->recordPayment($invoice, 'manual', 600.00);
$processor->markAsSuccess($payment);

// Invoice automatically marked as paid!
$invoice->isPaid(); // true
```

---

## 📋 Invoices

### Creating Invoices

```php
// With billable entity
$invoice = Invoicing::create([
    'billable'  => $customer,
    'title'     => 'Service Invoice',
    'currency'  => 'SAR',
    'due_at'    => now()->addDays(30),
    'items'     => [
        ['description' => 'Web Development', 'quantity' => 1, 'unit_price' => 5000],
        ['description' => 'Hosting (12 months)', 'quantity' => 1, 'unit_price' => 1200],
    ],
]);

// Standalone (no billable)
$invoice = Invoicing::create([
    'title'     => 'Walk-in Sale',
    'currency'  => 'USD',
    'items'     => [
        ['description' => 'Coffee', 'quantity' => 3, 'unit_price' => 5],
    ],
]);

// With tenant ID (multi-tenant)
$invoice = Invoicing::create([
    'billable'  => $customer,
    'title'     => 'Tenant Invoice',
    'tenant_id' => 'tenant-123',
    'items'     => [...],
]);
```

### Invoice with Discount & Tax

```php
// Percentage discount (10% off)
$invoice = Invoicing::create([
    'billable'       => $customer,
    'title'          => 'Discounted Service',
    'items'          => [
        ['description' => 'Consultation', 'quantity' => 1, 'unit_price' => 1000],
    ],
    'discount'       => 10,
    'discount_type'  => 'percentage',  // DiscountType enum: 'percentage' or 'fixed'
    'tax'            => 15,             // 15% VAT
]);

// Calculation:
// Subtotal = 1000
// Discount (10%) = -100 → After discount = 900
// Tax (15% of 900) = +135
// Total = 1035

// Fixed discount (50 SAR off)
$invoice = Invoicing::create([
    'billable'       => $customer,
    'title'          => 'Fixed Discount',
    'items'          => [...],
    'discount'       => 50,
    'discount_type'  => 'fixed',
    'tax'            => 15,
]);

// Calculation:
// Subtotal = 1000, Discount = -50 → 950
// Tax (15% of 950) = +142.50
// Total = 1092.50
```

> ⚠️ **`discount_type` is required** when `discount > 0`. No silent default — the package throws an error if you provide a discount without specifying its type.

### Invoice with Metadata

```php
$invoice = Invoicing::create([
    'billable'  => $customer,
    'title'     => 'Order Invoice',
    'items'     => [...],
    'metadata'  => [
        'order_id'     => $order->id,
        'source'       => 'web_checkout',
        'coupon_code'  => 'SAVE20',
    ],
]);

// Access later
$invoice->metadata['order_id'];
```

### Line Items with Per-Line Tax & Discount

```php
$invoice = Invoicing::create([
    'billable'  => $customer,
    'title'     => 'Mixed Invoice',
    'items'     => [
        [
            'description' => 'Premium Service',
            'quantity'    => 1,
            'unit_price'  => 500,
            'discount'    => 50,       // per-line discount
            'tax'         => 75,       // per-line tax
        ],
    ],
]);
// Line total = (500*1) - 50 + 75 = 525
```

### Adding Lines After Creation

```php
Invoicing::addLine($invoice, [
    'description' => 'Late Fee',
    'quantity'    => 1,
    'unit_price'  => 50,
]);

// Recalculate totals after adding/removing lines
Invoicing::recalculateTotals($invoice);
```

### Invoice Number Format

Customize in config:

```php
'invoice_number_format' => '{prefix}-{year}-{sequence}',
'invoice_number_prefix' => 'INV',
'invoice_number_sequence_length' => 4,
```

Available placeholders: `{prefix}`, `{year}`, `{month}`, `{sequence}`

Examples: `INV-2025-0001`, `INV-2025-06-0001`

The generator automatically handles collisions with a bounded retry loop (max 10 attempts).

---

## 🔄 Invoice Lifecycle

### Status Transitions

```
draft ──► unpaid ──► paid ──► refunded
 │         │    │
 └─► canceled  └─► overdue ──► paid / canceled
```

### Operations

```php
// Issue: draft → unpaid
Invoicing::markAsIssued($invoice);

// Pay: unpaid → paid
Invoicing::markAsPaid($invoice);

// Cancel: draft/unpaid → canceled
Invoicing::cancel($invoice);

// Refund: paid → refunded
Invoicing::refund($invoice);
```

Invalid transitions throw `InvoiceStatusTransitionException`.

### Overdue Status

```php
// Check if overdue (Unpaid + past due_at, or Overdue status)
$invoice->isOverdue(); // bool

// Mark as overdue (via command)
php artisan invoicing:mark-overdue

// Overdue invoices can transition to: Paid or Canceled
$invoice->status->canTransitionTo(InvoiceStatus::Paid);    // true
$invoice->status->canTransitionTo(InvoiceStatus::Canceled); // true
```

Configure grace period:

```php
'overdue_threshold_days' => 0,  // 0 = immediately overdue after due_at
'overdue_threshold_days' => 3,  // 3 days grace period
```

Schedule the command in `routes/console.php` (Laravel 11+) or `Console/Kernel.php`:

```php
$schedule->command('invoicing:mark-overdue')->dailyAt('08:00');
```

### Status Helpers

```php
$invoice->isDraft();      // status === Draft
$invoice->isUnpaid();     // status === Unpaid
$invoice->isPaid();       // status === Paid
$invoice->isCanceled();   // status === Canceled
$invoice->isRefunded();   // status === Refunded
$invoice->isOverdue();    // status === Overdue OR (Unpaid + past due_at)
```

### Financial Helpers

```php
$invoice->totalPaid();         // sum of successful payments
$invoice->remainingBalance();   // total - totalPaid (min 0)
$invoice->isFullyPaid();        // true if remainingBalance <= 0
$invoice->hasLines();           // true if invoice has line items
$invoice->lineCount();          // number of line items
$invoice->lines;                // HasMany relationship
$invoice->payments;             // HasMany relationship
$invoice->billable;             // MorphTo relationship (nullable)
$invoice->user;                 // BelongsTo User (nullable)
```

### Query Scopes

```php
Invoice::forTenant('tenant-1')->get();      // filter by tenant
Invoice::forUser(1)->get();                  // filter by user
Invoice::status(InvoiceStatus::Paid)->get(); // filter by status
```

---

## 💳 Payments

### Record a Manual Payment

```php
$processor = app(PaymentProcessor::class);

$payment = $processor->recordPayment(
    invoice:        $invoice,
    gateway:        'manual',
    amount:         500.00,
    transactionId:  'TXN-001',
    userId:         auth()->id(),  // optional
);

// Amount validation: must be > 0 and ≤ remainingBalance()
// Throws InvalidPaymentAmountException on violation
```

### Mark Payment as Success/Failed

```php
$processor->markAsSuccess($payment);
// Fires PaymentSucceeded event
// Auto-marks invoice as paid if fully paid

$processor->markAsFailed($payment);
// Fires PaymentFailed event
```

### Create Checkout Session

```php
$checkout = $processor->createCheckout(
    invoice:   $invoice,
    returnUrl: 'https://example.com/success',
    cancelUrl: 'https://example.com/cancel',
    gateway:   'stripe',  // optional, defaults to config
);
```

### Handle Webhooks

```php
$webhookEvent = $processor->handleWebhook($payload, 'stripe');
```

### Refund

```php
$processor->refund($invoice, null, 'stripe');
// Returns bool
```

### Payment Helpers

```php
$payment->isPending();
$payment->isAwaitingVerification();
$payment->isSuccess();
$payment->isFailed();
$payment->isRefunded();
$payment->needsVerification();
$payment->invoice;      // BelongsTo Invoice
$payment->user;         // BelongsTo User (nullable)
$payment->verifier;     // BelongsTo User via verified_by (nullable)
```

---

## 🏦 Payment Gateways

### Built-in Gateways

| Gateway                 | Status         | Description                     |
| ----------------------- | -------------- | ------------------------------- |
| **LocalGateway**        | ✅ Ready       | Auto-succeeds for local/testing |
| **StripeGateway**       | 🏗 Placeholder | Requires `stripe/stripe-php`    |
| **BankTransferGateway** | ✅ Ready       | Manual admin verification       |

> ⚠️ **Stripe:** throws `RuntimeException` if `stripe/stripe-php` is not installed. Install with `composer require stripe/stripe-php`.

### Switch Default Gateway

```php
// config/invoicing.php
'default_gateway' => 'stripe',
```

Or via env: `INVOICING_GATEWAY=stripe`

### Use a Specific Gateway per Payment

```php
$processor->createCheckout($invoice, '/success', '/cancel', 'stripe');
$processor->refund($invoice, null, 'stripe');
```

### Add Custom Gateway — Via Config

```php
'gateways' => [
    'paypal' => [
        'driver' => \App\Gateways\PayPalGateway::class,
        'api_key' => env('PAYPAL_API_KEY'),
    ],
],
```

### Add Custom Gateway — Via Runtime

```php
use Salehye\Invoicing\Services\GatewayManager;

app(GatewayManager::class)->register('paypal', \App\Gateways\PayPalGateway::class);
```

### Custom Gateway Implementation

```php
use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\WebhookEvent;
use Salehye\Invoicing\Models\Invoice;

class PayPalGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        return ['checkout_url' => 'https://paypal.com/pay/...'];
    }

    public function handleWebhook(array $payload): ?WebhookEvent
    {
        return null;
    }

    public function getPaymentStatus(string $transactionId): string
    {
        return 'success';
    }

    public function refund(Invoice $invoice, ?float $amount = null): bool
    {
        return true;
    }
}
```

### Gateway Manager API

```php
$manager = app(GatewayManager::class);

$manager->names();      // ['local', 'stripe', 'bank_transfer', ...]
$manager->has('paypal'); // bool
$manager->gateway();     // default gateway instance
$manager->gateway('stripe'); // specific gateway instance

// Unregistered → throws GatewayNotFoundException
$manager->gateway('unknown');
```

---

## 🏦 Bank Transfer (Manual Verification)

### Flow

```
Customer initiates → Uploads proof → Payment: awaiting_verification
                                           ↓
                        Admin reviews → verify() → Success → Invoice paid
                                           ↓
                        Admin reviews → reject() → Failed
```

### 1. Customer Initiates

```php
$processor = app(PaymentProcessor::class);

// Show bank details to customer
$checkout = $processor->createCheckout($invoice, '/success', '/cancel', 'bank_transfer');
// Returns: type, invoice_id, amount, currency, bank_details, reference, instructions

// Customer uploads proof
$payment = $processor->initiateBankTransfer($invoice, 'receipt.pdf', 'Paid via Al Rajhi');
// Status: awaiting_verification
```

### 2. Admin Verifies or Rejects

```php
// Verify — invoice auto-marked as paid if fully paid
$processor->verify($payment, auth()->id());
// verified_by = user ID (int), verified_at = now()

// Reject — with reason
$processor->reject($payment, 'Receipt is unclear');
// proof_notes updated with reason

// Wrong status → throws PaymentVerificationException
```

### 3. Bank Details Config

```php
'gateways' => [
    'bank_transfer' => [
        'bank_details' => [
            'bank_name'      => 'Al Rajhi Bank',
            'account_name'   => 'My Company',
            'account_number' => '1234567890',
            'iban'           => 'SA0380000000608010167519',
            'swift_code'     => 'RJHISARI',
        ],
        'instructions' => 'Transfer the amount and upload proof of payment.',
    ],
],
```

### Payment Status Flow

```
pending → success / failed
awaiting_verification → success (verify) / failed (reject)
success → refunded
```

### PaymentStatus Transitions

```php
PaymentStatus::Pending->canTransitionTo(PaymentStatus::Success);    // true
PaymentStatus::Pending->canTransitionTo(PaymentStatus::Failed);     // true
PaymentStatus::Success->canTransitionTo(PaymentStatus::Refunded);   // true
PaymentStatus::Failed->canTransitionTo(PaymentStatus::Success);     // false
```

Invalid transitions throw `PaymentStatusTransitionException`.

---

## 🛡️ Exceptions

| Exception                          | Thrown When                                        |
| ---------------------------------- | -------------------------------------------------- |
| `InvoiceStatusTransitionException` | Invalid invoice status transition                  |
| `PaymentStatusTransitionException` | Invalid payment status transition                  |
| `PaymentVerificationException`     | verify/reject on non-awaiting_verification payment |
| `GatewayNotFoundException`         | Unregistered gateway requested                     |
| `InvalidPaymentAmountException`    | Amount ≤ 0 or exceeds remaining balance            |

All extend standard PHP exceptions (`RuntimeException` / `InvalidArgumentException`) so they integrate naturally with Laravel's error handling.

---

## 📡 Events

All events use `public readonly` properties (immutable after construction):

| Event              | Property   | When                         |
| ------------------ | ---------- | ---------------------------- |
| `InvoiceCreated`   | `$invoice` | Invoice created              |
| `InvoiceUpdated`   | `$invoice` | Issued / totals recalculated |
| `InvoicePaid`      | `$invoice` | Marked as paid               |
| `InvoiceCanceled`  | `$invoice` | Canceled                     |
| `InvoiceRefunded`  | `$invoice` | Refunded                     |
| `PaymentSucceeded` | `$payment` | Payment succeeded            |
| `PaymentFailed`    | `$payment` | Payment failed               |
| `PaymentVerified`  | `$payment` | Admin verified bank transfer |

### Listening

```php
// App\Providers\EventServiceProvider
protected $listen = [
    \Salehye\Invoicing\Events\InvoicePaid::class => [
        \App\Listeners\SendInvoicePaidNotification::class,
    ],
    \Salehye\Invoicing\Events\PaymentVerified::class => [
        \App\Listeners\NotifyCustomerPaymentVerified::class,
    ],
];
```

### Example Listener

```php
class SendInvoicePaidNotification
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;  // readonly — cannot be modified
        Mail::to($invoice->billable)->send(new InvoicePaidMail($invoice));
    }
}
```

---

## 🚪 Middleware

### `EnsureInvoicePaid`

The `invoice.paid` middleware alias is **auto-registered** by the package's ServiceProvider (works in Laravel 11, 12, and 13). No manual registration needed.

Restrict route access by invoice payment status:

```php
// Just use it directly — no manual registration required
Route::get('/downloads/{invoice}', [DownloadController::class, 'download'])
    ->middleware('invoice.paid:invoice');

// The parameter name is configurable: 'invoice.paid:invoice_id'
```

If you prefer manual registration, you can also add it in `bootstrap/app.php`:

```php
// bootstrap/app.php (Laravel 11+)
$app->routeMiddleware([
    'invoice.paid' => \Salehye\Invoicing\Middleware\EnsureInvoicePaid::class,
]);
```

> **Note:** The auto-registration is preferred and works out of the box. Manual registration is only needed if you want to override the alias or have a conflicting alias name.

---

## 🔗 HasInvoices Trait

```php
class Customer extends Model
{
    use HasInvoices;
}

// Available methods
$customer->invoices();            // MorphMany — all invoices
$customer->draftInvoices();       // MorphMany — status = Draft
$customer->unpaidInvoices();      // MorphMany — status = Unpaid
$customer->paidInvoices();        // MorphMany — status = Paid
$customer->canceledInvoices();    // MorphMany — status = Canceled
$customer->overdueInvoices();     // MorphMany — Overdue OR unpaid + past due_at
$customer->refundedinvoices();    // MorphMany — status = Refunded
$customer->totalInvoiceBalance(); // float — sum of unpaid totals
$customer->totalPaidAmount();     // float — sum of paid totals
```

All methods return `MorphMany` with proper return type declarations.

---

## 👤 User ID Tracking

Track who created/owns invoices and payments (independent from `billable`):

```php
// Invoice with user
$invoice = Invoicing::create([
    'billable' => $customer,
    'title'    => 'Order Invoice',
    'user_id'  => auth()->id(),
    'items'    => [...],
]);

$invoice->user; // BelongsTo → configured User model

// Payment with user
$payment = $processor->recordPayment($invoice, 'stripe', 100, userId: auth()->id());
$payment->user; // BelongsTo → configured User model

// Bank transfer inherits user_id from invoice
$payment = $processor->initiateBankTransfer($invoice);
// payment.user_id = invoice.user_id

// Override with explicit user
$payment = $processor->initiateBankTransfer($invoice, null, null, $otherUserId);
```

### Custom User Model

```php
// config/invoicing.php
'user_model' => \App\Models\Admin::class,
```

---

## 🧮 Custom Calculators

### Tax Calculator

```php
use Salehye\Invoicing\Contracts\TaxCalculator;

class SaudiVatCalculator implements TaxCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float
    {
        return round($subtotal * 0.15, 2);
    }
}

// Register in a service provider
app()->singleton(TaxCalculator::class, SaudiVatCalculator::class);
```

### Discount Calculator

```php
use Salehye\Invoicing\Contracts\DiscountCalculator;

class CouponDiscountCalculator implements DiscountCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float
    {
        $coupon = $metadata['coupon'] ?? null;
        return $coupon ? $coupon->applyTo($subtotal) : 0;
    }
}

app()->singleton(DiscountCalculator::class, CouponDiscountCalculator::class);
```

---

## 🗃️ Database Schema

### `invoices`

| Column          | Type          | Notes                                       |
| --------------- | ------------- | ------------------------------------------- |
| `id`            | bigint        | PK                                          |
| `billable_type` | string        | Polymorphic (nullable)                      |
| `billable_id`   | bigint        | Polymorphic (nullable)                      |
| `user_id`       | bigint        | Nullable, indexed (app adds FK)             |
| `tenant_id`     | string        | Nullable, indexed                           |
| `number`        | string        | Unique                                      |
| `title`         | string        | Required                                    |
| `description`   | text          | Nullable                                    |
| `currency`      | string(3)     | Default: USD                                |
| `subtotal`      | decimal(12,2) | Sum of line totals                          |
| `discount`      | decimal(12,2) | Discount amount                             |
| `discount_type` | enum          | `percentage` / `fixed` (DiscountType cast)  |
| `tax`           | decimal(12,2) | Tax amount                                  |
| `total`         | decimal(12,2) | Final total                                 |
| `status`        | enum          | draft/unpaid/paid/canceled/refunded/overdue |
| `issued_at`     | timestamp     | Nullable                                    |
| `due_at`        | timestamp     | Nullable                                    |
| `paid_at`       | timestamp     | Nullable                                    |
| `metadata`      | json          | Nullable                                    |
| `created_at`    | timestamp     |                                             |
| `updated_at`    | timestamp     |                                             |
| `deleted_at`    | timestamp     | Soft delete                                 |

### `invoice_lines`

| Column        | Type          | Notes                               |
| ------------- | ------------- | ----------------------------------- |
| `id`          | bigint        | PK                                  |
| `invoice_id`  | bigint        | FK→invoices (restrictOnDelete)      |
| `description` | string        | Required                            |
| `quantity`    | integer       | Default: 1                          |
| `unit_price`  | decimal(12,2) |                                     |
| `discount`    | decimal(12,2) | Per-line discount                   |
| `tax`         | decimal(12,2) | Per-line tax                        |
| `total`       | decimal(12,2) | (unit_price × qty) − discount + tax |
| `metadata`    | json          | Nullable                            |

### `payments`

| Column             | Type          | Notes                                                 |
| ------------------ | ------------- | ----------------------------------------------------- |
| `id`               | bigint        | PK                                                    |
| `invoice_id`       | bigint        | FK→invoices (restrictOnDelete)                        |
| `user_id`          | bigint        | Nullable, indexed (app adds FK)                       |
| `gateway`          | string        | e.g. manual, stripe, bank_transfer                    |
| `transaction_id`   | string        | Nullable                                              |
| `amount`           | decimal(12,2) | Must be > 0 and ≤ remaining balance                   |
| `currency`         | string(3)     |                                                       |
| `status`           | enum          | pending/awaiting_verification/success/failed/refunded |
| `gateway_response` | json          | Nullable                                              |
| `proof_file`       | string        | Nullable — bank transfer receipt                      |
| `proof_notes`      | string        | Nullable — customer/admin notes                       |
| `verified_at`      | timestamp     | Nullable — admin verification time                    |
| `verified_by`      | bigint        | Nullable, indexed — admin user ID (app adds FK)       |
| `created_at`       | timestamp     |                                                       |
| `updated_at`       | timestamp     |                                                       |

> **FK Constraints:** `user_id` and `verified_by` are `foreignId` (unsignedBigInteger) columns with indexes but without `constrained()` because the target user table is configurable. The consuming application should add FK constraints in their own migrations. `invoice_id` uses `restrictOnDelete` to preserve the financial audit trail.

---

## 🧪 Testing

```bash
composer install
vendor/bin/phpunit
```

**56 tests, 121 assertions** covering:

- ✅ Invoice creation with items & totals
- ✅ Percentage & fixed discount + tax calculations
- ✅ DiscountType enum casting
- ✅ Unique invoice number generation
- ✅ Status lifecycle (draft → unpaid → paid → refunded → canceled → overdue)
- ✅ Invalid transitions → InvoiceStatusTransitionException
- ✅ Polymorphic billable relationships
- ✅ Standalone invoices (no billable)
- ✅ Line items
- ✅ Overdue detection
- ✅ Gateway registration & resolution
- ✅ Custom gateway runtime registration
- ✅ GatewayNotFoundException
- ✅ Bank transfer: initiate, verify, reject
- ✅ PaymentVerificationException
- ✅ InvalidPaymentAmountException
- ✅ User ID on invoices and payments
- ✅ HasInvoices trait
- ✅ discount_type validation (required when discount > 0)
- ✅ PaymentStatus::canTransitionTo() transitions
- ✅ PaymentStatusTransitionException on invalid transitions
- ✅ Invoice::isFullyPaid(), hasLines(), lineCount()
- ✅ Invoice scopes: forTenant(), forUser(), status()
- ✅ HasInvoices: draftInvoices(), canceledInvoices(), refundedinvoices(), totalPaidAmount()
- ✅ Overdue scope includes unpaid past due_at (not just Overdue status)

---

## 📚 Documentation

- [API Reference](docs/api-reference.md) — Complete method signatures
- [Usage Examples](docs/usage-examples.md) — Real-world scenarios

---

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for all changes.

---

## 📄 License

[MIT](LICENSE) — free to use in personal and commercial projects.

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -am 'Add my feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Create a Pull Request

Please ensure all tests pass before submitting:

```bash
vendor/bin/phpunit
```
