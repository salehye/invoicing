# Usage Examples

Real-world scenarios for `salehye/invoicing`.

---

## 1. E-Commerce Order Invoice

Create an invoice for an online order with VAT:

```php
use Salehye\Invoicing\Facades\Invoicing;

$order = Order::find(1);

$invoice = Invoicing::create([
    'billable'  => $order->customer,
    'title'     => "Order #{$order->id}",
    'currency'  => 'SAR',
    'due_at'    => now()->addDays(7),
    'user_id'   => $order->customer->id,
    'items'     => $order->products->map(fn ($product) => [
        'description' => $product->name,
        'quantity'    => $product->qty,
        'unit_price'  => $product->price,
    ])->toArray(),
    'tax'       => 15,  // Saudi VAT
]);

Invoicing::markAsIssued($invoice);

// Customer pays via Stripe
use Salehye\Invoicing\Services\PaymentProcessor;

$processor = app(PaymentProcessor::class);
$checkout = $processor->createCheckout($invoice, '/payment/success', '/payment/cancel', 'stripe');

// Redirect customer to $checkout['checkout_url']
```

---

## 2. Subscription Billing (Recurring)

Create monthly invoices for a subscription plan — standalone, no subscription package dependency:

```php
class BillingService
{
    public function generateMonthlyInvoice(Subscription $subscription): Invoice
    {
        return Invoicing::create([
            'billable'  => $subscription->customer,
            'title'     => "{$subscription->plan->name} — " . now()->format('F Y'),
            'currency'  => 'SAR',
            'due_at'    => now()->addDays(14),
            'items'     => [
                [
                    'description' => $subscription->plan->name,
                    'quantity'    => 1,
                    'unit_price'  => $subscription->plan->monthly_price,
                ],
            ],
            'discount'       => $subscription->promo ? $subscription->promo->percentage : 0,
            'discount_type'  => 'percentage',
            'tax'            => 15,
            'metadata'       => [
                'subscription_id' => $subscription->id,
                'billing_period'  => now()->format('Y-m'),
            ],
        ]);
    }
}

// In a scheduled command
class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-monthly';

    public function handle()
    {
        Subscription::active()->each(fn ($sub) =>
            app(BillingService::class)->generateMonthlyInvoice($sub)
        );
    }
}
```

---

## 3. Bank Transfer with Manual Verification

Saudi business accepting bank transfers with manual receipt verification:

```php
use Salehye\Invoicing\Services\PaymentProcessor;

// 1. Customer views bank details
$invoice = Invoicing::create([...]);
Invoicing::markAsIssued($invoice);

$processor = app(PaymentProcessor::class);
$checkout = $processor->createCheckout($invoice, '/success', '/cancel', 'bank_transfer');

// Show customer:
// Bank: Al Rajhi Bank
// IBAN: SA0380000000608010167519
// Reference: INV-2025-0001

// 2. Customer uploads receipt
$payment = $processor->initiateBankTransfer(
    $invoice,
    'uploads/receipts/transfer_2025_07_15.pdf',
    'Transferred 1,035 SAR via Al Rajhi mobile app'
);

// 3. Admin reviews in dashboard
if ($this->isValidReceipt($payment->proof_file)) {
    $processor->verify($payment, auth()->id());  // success, invoice auto-paid
} else {
    $processor->reject($payment, 'Receipt is unclear — please re-upload');
}
```

---

## 4. Multi-Tenant SaaS

Track invoices per tenant:

```php
$invoice = Invoicing::create([
    'billable'  => $tenant->customer,
    'tenant_id' => $tenant->id,
    'title'     => "Monthly Plan — {$tenant->name}",
    'currency'  => 'USD',
    'items'     => [
        ['description' => 'Pro Plan', 'quantity' => 1, 'unit_price' => 99],
    ],
]);

// Query invoices for a tenant
$invoices = Invoice::where('tenant_id', $tenant->id)->get();

// Unpaid invoices for tenant
$unpaid = Invoice::where('tenant_id', $tenant->id)
    ->where('status', InvoiceStatus::Unpaid)
    ->get();
```

---

## 5. Partial Payments (Installments)

Customer paying in installments:

```php
use Salehye\Invoicing\Services\PaymentProcessor;

$invoice = Invoicing::create([
    'billable' => $customer,
    'title'    => 'Large Project — Phase 1',
    'currency' => 'SAR',
    'items'    => [
        ['description' => 'Full project', 'quantity' => 1, 'unit_price' => 50000],
    ],
    'tax' => 15,
]);
Invoicing::markAsIssued($invoice);
// Total = 57,500 SAR

$processor = app(PaymentProcessor::class);

// First installment: 20,000 SAR
$payment1 = $processor->recordPayment($invoice, 'bank_transfer', 20000, userId: $customer->id);
$processor->markAsSuccess($payment1);
// remainingBalance = 37,500

// Second installment: 37,500 SAR (pays off remaining)
$payment2 = $processor->recordPayment($invoice, 'bank_transfer', 37500, userId: $customer->id);
$processor->markAsSuccess($payment2);
// remainingBalance = 0 → invoice auto-paid!
```

---

## 6. Custom Gateway (PayPal)

```php
use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\WebhookEvent;
use Salehye\Invoicing\Models\Invoice;

class PayPalGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        $paypal = new \PayPal\Api\Payment();
        $paypal->setIntent('sale');
        // ... PayPal SDK integration
        return [
            'checkout_url' => $paypal->getApprovalLink(),
            'payment_id'   => $paypal->getId(),
            'invoice_id'   => $invoice->id,
        ];
    }

    public function handleWebhook(array $payload): ?WebhookEvent
    {
        return new PayPalWebhookEvent($payload);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $payment = \PayPal\Api\Payment::get($transactionId, $this->apiContext);
        return strtolower($payment->getState());
    }

    public function refund(Invoice $invoice, ?float $amount = null): bool
    {
        // PayPal refund logic
        return true;
    }
}

// Register
app(GatewayManager::class)->register('paypal', PayPalGateway::class);
```

---

## 7. Event-Driven Notifications

```php
// EventServiceProvider
protected $listen = [
    \Salehye\Invoicing\Events\InvoiceCreated::class => [
        \App\Listeners\SendNewInvoiceNotification::class,
    ],
    \Salehye\Invoicing\Events\InvoicePaid::class => [
        \App\Listeners\SendPaymentConfirmation::class,
        \App\Listeners\UpdateAccountingSystem::class,
    ],
    \Salehye\Invoicing\Events\PaymentVerified::class => [
        \App\Listeners\NotifyBankTransferVerified::class,
    ],
    \Salehye\Invoicing\Events\InvoiceRefunded::class => [
        \App\Listeners\ProcessRefund::class,
    ],
];

// Listener
class SendPaymentConfirmation
{
    public function handle(\Salehye\Invoicing\Events\InvoicePaid $event): void
    {
        $invoice = $event->invoice;  // readonly — immutable
        Mail::to($invoice->billable)->send(new InvoicePaidMail($invoice));
    }
}
```

---

## 8. Overdue Invoice Management

```php
// Schedule in routes/console.php (Laravel 11+) or Console/Kernel.php
$schedule->command('invoicing:mark-overdue')->dailyAt('08:00');

// Notify overdue customers
class NotifyOverdueInvoices extends Command
{
    protected $signature = 'invoicing:notify-overdue';

    public function handle()
    {
        Invoice::where('status', InvoiceStatus::Overdue)
            ->each(function ($invoice) {
                Mail::to($invoice->billable)->send(new OverdueInvoiceMail($invoice));
            });
    }
}

$schedule->command('invoicing:notify-overdue')->dailyAt('09:00');
```

---

## 9. Middleware: Paid-Only Downloads

The `invoice.paid` middleware alias is **auto-registered** by the package — just use it directly:

```php
// No registration needed — the package auto-registers the alias
Route::get('/invoices/{invoice}/download', [DownloadController::class, 'download'])
    ->middleware('invoice.paid:invoice');

// Unpaid → 403: "Invoice must be paid before accessing this resource."
```

For manual override, add in `bootstrap/app.php`:

```php
$app->routeMiddleware([
    'invoice.paid' => \Salehye\Invoicing\Middleware\EnsureInvoicePaid::class,
]);
```

---

## 10. Custom Tax Calculator (Saudi VAT Threshold)

```php
use Salehye\Invoicing\Contracts\TaxCalculator;

class SaudiThresholdVatCalculator implements TaxCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float
    {
        $rate = $subtotal >= 1000 ? 15 : 5;  // Saudi threshold VAT
        return round($subtotal * ($rate / 100), 2);
    }
}

// Register in AppServiceProvider
app()->singleton(TaxCalculator::class, SaudiThresholdVatCalculator::class);
```

---

## 11. Standalone Invoice (No Billable)

Walk-in customers, cash sales, or non-model invoices:

```php
$invoice = Invoicing::create([
    'title'    => 'Cash Sale — Walk-in Customer',
    'currency' => 'SAR',
    'items'    => [
        ['description' => 'Coffee Table', 'quantity' => 1, 'unit_price' => 350],
        ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 50],
    ],
    'tax' => 15,
]);

Invoicing::markAsIssued($invoice);

// $invoice->billable_type = null
// $invoice->billable_id = null
// $invoice->billable = null
```

---

## 12. Invoice with Coupon Discount

```php
$coupon = Coupon::where('code', 'SAVE50')->first();

$invoice = Invoicing::create([
    'billable'       => $customer,
    'title'          => 'Order with Coupon',
    'items'          => [
        ['description' => 'Product X', 'quantity' => 1, 'unit_price' => 200],
    ],
    'discount'       => $coupon->amount,     // 50 SAR fixed
    'discount_type'  => 'fixed',
    'tax'            => 15,
    'metadata'       => ['coupon_code' => 'SAVE50'],
]);

// Subtotal = 200, Discount = -50 → 150
// Tax (15% of 150) = 22.5
// Total = 172.5
```

---

## 13. Handling Webhooks (Stripe)

```php
use Salehye\Invoicing\Services\PaymentProcessor;

// In a controller
class WebhookController extends Controller
{
    public function stripe(Request $request)
    {
        $payload = $request->all();
        $processor = app(PaymentProcessor::class);

        $webhookEvent = $processor->handleWebhook($payload, 'stripe');

        if ($webhookEvent) {
            $invoice = Invoice::find($webhookEvent->getInvoiceId());
            $status = $webhookEvent->getStatus();

            if ($status === 'success') {
                $processor->recordPayment(
                    $invoice,
                    'stripe',
                    $invoice->total,
                    $webhookEvent->getTransactionId(),
                    $webhookEvent->getRawPayload(),
                );
                $processor->markAsSuccess($payment);
            }
        }

        return response()->json(['received' => true]);
    }
}
```

---

## 14. Querying Invoices on a Billable Model

```php
$customer = Customer::find(1);

// All invoices
$customer->invoices()->get();

// Unpaid invoices
$customer->unpaidInvoices()->get();

// Paid invoices
$customer->paidInvoices()->get();

// Overdue invoices
$customer->overdueInvoices()->get();

// Total outstanding balance
$balance = $customer->totalInvoiceBalance();

// With filtering
$recent = $customer->invoices()
    ->where('created_at', '>=', now()->subMonth())
    ->orderByDesc('created_at')
    ->get();
```
