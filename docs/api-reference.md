# API Reference

Complete method signatures for all classes in `salehye/invoicing`.

---

## Facades

### `Invoicing` (Facade → `InvoiceManager`)

```php
use Salehye\Invoicing\Facades\Invoicing;

Invoicing::create(array $attributes): Invoice
Invoicing::addLine(Invoice $invoice, array $item): InvoiceLine
Invoicing::markAsIssued(Invoice $invoice): Invoice
Invoicing::markAsPaid(Invoice $invoice): Invoice
Invoicing::cancel(Invoice $invoice): Invoice
Invoicing::refund(Invoice $invoice): Invoice
Invoicing::recalculateTotals(Invoice $invoice): Invoice
```

---

## Services

### `InvoiceManager`

```php
namespace Salehye\Invoicing\Services;

class InvoiceManager
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly TotalCalculator $totalCalculator,
    )

    public function create(array $attributes): Invoice
    public function addLine(Invoice $invoice, array $item): InvoiceLine
    public function markAsIssued(Invoice $invoice): Invoice
    public function markAsPaid(Invoice $invoice): Invoice
    public function cancel(Invoice $invoice): Invoice
    public function refund(Invoice $invoice): Invoice
    public function recalculateTotals(Invoice $invoice): Invoice
}
```

**`create()` attributes:**

| Key             | Type     | Required | Description                                          |
| --------------- | -------- | -------- | ---------------------------------------------------- |
| `billable`      | Model    | No       | Polymorphic billable entity (nullable)               |
| `title`         | string   | Yes      | Invoice title                                        |
| `currency`      | string   | No       | Default from config                                  |
| `due_at`        | DateTime | No       | Due date                                             |
| `user_id`       | int      | No       | User who created                                     |
| `tenant_id`     | string   | No       | Tenant identifier                                    |
| `items`         | array    | Yes      | Line items array                                     |
| `discount`      | float    | No       | Discount amount (default: 0)                         |
| `discount_type` | string   | No\*     | `percentage` or `fixed` (\*required if discount > 0) |
| `tax`           | float    | No       | Tax rate % (default from config)                     |
| `metadata`      | array    | No       | Extra JSON data                                      |
| `description`   | string   | No       | Invoice description                                  |

**`addLine()` item attributes:**

| Key           | Type   | Default | Description       |
| ------------- | ------ | ------- | ----------------- |
| `description` | string | `''`    | Line description  |
| `quantity`    | int    | `1`     | Quantity          |
| `unit_price`  | float  | `0`     | Unit price        |
| `discount`    | float  | `0`     | Per-line discount |
| `tax`         | float  | `0`     | Per-line tax      |
| `metadata`    | array  | `null`  | Extra data        |

**Exceptions:**

- `InvoiceStatusTransitionException` — on invalid status transition

---

### `PaymentProcessor`

```php
namespace Salehye\Invoicing\Services;

class PaymentProcessor
{
    public function __construct(
        private readonly GatewayManager $gatewayManager,
    )

    public function getGateway(?string $name = null): PaymentGateway
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl, ?string $gateway = null): array
    public function recordPayment(Invoice $invoice, string $gateway, float $amount, ?string $transactionId = null, ?array $gatewayResponse = null, ?int $userId = null): Payment
    public function initiateBankTransfer(Invoice $invoice, ?string $proofFile = null, ?string $proofNotes = null, ?int $userId = null): Payment
    public function verify(Payment $payment, ?int $verifiedBy = null): Payment
    public function reject(Payment $payment, ?string $reason = null): Payment
    public function markAsSuccess(Payment $payment): Payment
    public function markAsFailed(Payment $payment): Payment
    public function handleWebhook(array $payload, ?string $gateway = null): ?WebhookEvent
    public function refund(Invoice $invoice, ?float $amount = null, ?string $gateway = null): bool
}
```

**Exceptions:**

- `InvalidPaymentAmountException` — amount ≤ 0 or exceeds remaining balance
- `PaymentVerificationException` — verify/reject on non-awaiting_verification payment
- `GatewayNotFoundException` — unregistered gateway
- `InvalidArgumentException` — bank_transfer gateway is not BankTransferGateway instance

---

### `GatewayManager`

```php
namespace Salehye\Invoicing\Services;

class GatewayManager
{
    public function __construct(private readonly Application $app)

    public function register(string $name, string $class): self
    public function has(string $name): bool
    public function names(): array
    public function gateway(?string $name = null): PaymentGateway
}
```

**Exceptions:**

- `GatewayNotFoundException` — when `$name` is not registered

---

### `InvoiceNumberGenerator`

```php
namespace Salehye\Invoicing\Services;

class InvoiceNumberGenerator
{
    public function generate(): string
}
```

Generates unique invoice numbers based on config format. Bounded retry (max 10 attempts) on collision.

**Exceptions:**

- `RuntimeException` — unable to generate unique number after 10 attempts

---

### `TotalCalculator`

```php
namespace Salehye\Invoicing\Services;

class TotalCalculator
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
        private readonly DiscountCalculator $discountCalculator,
    )

    public function applyTotals(
        Invoice $invoice,
        float $discount = 0,
        ?DiscountType $discountType = null,
        float $taxRate = 0,
    ): Invoice
}
```

---

## Models

### `Invoice`

```php
namespace Salehye\Invoicing\Models;

class Invoice extends Model
{
    // Fillable fields
    protected $fillable = [
        'billable_type', 'billable_id', 'user_id', 'tenant_id',
        'number', 'title', 'description', 'currency',
        'subtotal', 'discount', 'discount_type', 'tax', 'total',
        'status', 'issued_at', 'due_at', 'paid_at', 'metadata',
    ];

    // Casts
    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_type' => DiscountType::class,
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'status' => InvoiceStatus::class,
    ];

    // Relationships
    public function billable(): MorphTo
    public function user(): BelongsTo
    public function lines(): HasMany      // → InvoiceLine
    public function payments(): HasMany   // → Payment

    // Helpers
    public function isDraft(): bool
    public function isUnpaid(): bool
    public function isPaid(): bool
    public function isCanceled(): bool
    public function isRefunded(): bool
    public function isOverdue(): bool      // Overdue status OR Unpaid + past due_at
    public function totalPaid(): float     // sum of successful payments
    public function remainingBalance(): float // max(0, total - totalPaid)

    // Table
    public function getTable(): string    // config('invoicing.table_names.invoices', 'invoices')
}
```

Uses `SoftDeletes`.

---

### `InvoiceLine`

```php
namespace Salehye\Invoicing\Models;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id', 'description', 'quantity', 'unit_price',
        'discount', 'tax', 'total', 'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function invoice(): BelongsTo  // → Invoice

    // Table
    public function getTable(): string    // config('invoicing.table_names.invoice_lines', 'invoice_lines')
}
```

---

### `Payment`

```php
namespace Salehye\Invoicing\Models;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id', 'user_id', 'gateway', 'transaction_id',
        'amount', 'currency', 'status', 'gateway_response',
        'proof_file', 'proof_notes', 'verified_at', 'verified_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'status' => PaymentStatus::class,
        'verified_at' => 'datetime',
    ];

    // Relationships
    public function invoice(): BelongsTo   // → Invoice
    public function user(): BelongsTo      // → config user_model
    public function verifier(): BelongsTo  // → config user_model via verified_by

    // Helpers
    public function isPending(): bool
    public function isAwaitingVerification(): bool
    public function isSuccess(): bool
    public function isFailed(): bool
    public function isRefunded(): bool
    public function needsVerification(): bool  // delegates to PaymentStatus enum

    // Table
    public function getTable(): string     // config('invoicing.table_names.payments', 'payments')
}
```

---

## Enums

### `InvoiceStatus`

```php
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case Overdue = 'overdue';

    public function canTransitionTo(InvoiceStatus $target): bool
    public function label(): string
}
```

**Transition rules:**

| From     | To                      |
| -------- | ----------------------- |
| Draft    | Unpaid, Canceled        |
| Unpaid   | Paid, Canceled, Overdue |
| Overdue  | Paid, Canceled          |
| Paid     | Refunded                |
| Canceled | _(none)_                |
| Refunded | _(none)_                |

---

### `PaymentStatus`

```php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingVerification = 'awaiting_verification';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    public function needsVerification(): bool  // true only for AwaitingVerification
}
```

---

### `DiscountType`

```php
enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
}
```

---

## Exceptions

```php
namespace Salehye\Invoicing\Exceptions;

class InvoiceStatusTransitionException extends \RuntimeException
{
    public function __construct(InvoiceStatus $from, InvoiceStatus $to)
}

class PaymentVerificationException extends \RuntimeException
{
    public function __construct(PaymentStatus $status)
}

class GatewayNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $name, array $available)
}

class InvalidPaymentAmountException extends \InvalidArgumentException
{
    public function __construct(float $amount, float $max = 0)
    // amount ≤ 0: "Payment amount must be greater than 0, got {amount}"
    // amount > max: "Payment amount {amount} exceeds remaining balance {max}"
}
```

---

## Events

All events use `public readonly` constructor promotion:

```php
namespace Salehye\Invoicing\Events;

class InvoiceCreated
{
    public function __construct(public readonly Invoice $invoice)
}

class InvoiceUpdated
{
    public function __construct(public readonly Invoice $invoice)
}

class InvoicePaid
{
    public function __construct(public readonly Invoice $invoice)
}

class InvoiceCanceled
{
    public function __construct(public readonly Invoice $invoice)
}

class InvoiceRefunded
{
    public function __construct(public readonly Invoice $invoice)
}

class PaymentSucceeded
{
    public function __construct(public readonly Payment $payment)
}

class PaymentFailed
{
    public function __construct(public readonly Payment $payment)
}

class PaymentVerified
{
    public function __construct(public readonly Payment $payment)
}
```

---

## Contracts

### `PaymentGateway`

```php
interface PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array;
    public function handleWebhook(array $payload): ?WebhookEvent;
    public function getPaymentStatus(string $transactionId): string;
    public function refund(Invoice $invoice, ?float $amount = null): bool;
}
```

### `WebhookEvent`

```php
interface WebhookEvent
{
    public function getInvoiceId(): int;
    public function getStatus(): string;
    public function getTransactionId(): ?string;
    public function getRawPayload(): array;
}
```

### `TaxCalculator`

```php
interface TaxCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float;
}
```

### `DiscountCalculator`

```php
interface DiscountCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float;
}
```

---

## Trait

### `HasInvoices`

```php
namespace Salehye\Invoicing\Traits;

trait HasInvoices
{
    public function invoices(): MorphMany
    public function unpaidInvoices(): MorphMany
    public function paidInvoices(): MorphMany
    public function overdueInvoices(): MorphMany
    public function totalInvoiceBalance(): float
}
```

---

## Middleware

### `EnsureInvoicePaid`

The `invoice.paid` middleware alias is **auto-registered** by `InvoicingServiceProvider` (compatible with Laravel 11, 12, and 13). No manual registration needed.

```php
namespace Salehye\Invoicing\Middleware;

class EnsureInvoicePaid
{
    public function handle(Request $request, Closure $next, string $invoiceParameter = 'invoice'): mixed
}
```

Extracts route parameter, finds invoice, aborts 403 if not paid. Aborts 400 if parameter not found.

**Usage:**

```php
Route::get('/downloads/{invoice}', [DownloadController::class, 'download'])
    ->middleware('invoice.paid:invoice');
```

---

## Artisan Command

### `invoicing:mark-overdue`

```bash
php artisan invoicing:mark-overdue
```

Finds all `Unpaid` invoices where `due_at < now() - overdue_threshold_days` and transitions them to `Overdue` status (via `canTransitionTo` check).

Output: `"Marked {count} invoices as overdue."` or `"No overdue invoices found."`

---

## Gateways (Built-in)

### `LocalGateway`

```php
class LocalGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    // Returns: ['checkout_url' => null, 'auto_succeed' => bool, 'invoice_id', 'amount', 'currency']

    public function handleWebhook(array $payload): ?WebhookEvent  // returns null
    public function getPaymentStatus(string $transactionId): string  // returns 'success'
    public function refund(Invoice $invoice, ?float $amount = null): bool  // returns true
}
```

### `StripeGateway`

```php
class StripeGateway implements PaymentGateway
{
    // All methods throw RuntimeException if \Stripe\Stripe class doesn't exist
    // Otherwise: placeholder implementations (requires stripe/stripe-php for full functionality)
}
```

### `BankTransferGateway`

```php
class BankTransferGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    // Returns: ['type' => 'bank_transfer', 'invoice_id', 'amount', 'currency',
    //           'bank_details' => [...], 'reference' => invoice.number, 'instructions']

    public function initiatePayment(Invoice $invoice, ?string $proofFile, ?string $proofNotes, ?int $userId): Payment
    // Creates Payment with status = AwaitingVerification

    public function handleWebhook(array $payload): ?WebhookEvent  // returns null
    public function getPaymentStatus(string $transactionId): string  // returns 'awaiting_verification'
    public function refund(Invoice $invoice, ?float $amount = null): bool  // returns true
}
```
