<?php

namespace Salehye\Invoicing\Tests\Unit;

use InvalidArgumentException;
use Salehye\Invoicing\Enums\DiscountType;
use Salehye\Invoicing\Enums\InvoiceStatus;
use Salehye\Invoicing\Enums\PaymentStatus;
use Salehye\Invoicing\Exceptions\PaymentStatusTransitionException;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Services\InvoiceManager;
use Salehye\Invoicing\Services\PaymentProcessor;
use Salehye\Invoicing\Tests\Models\Customer;
use Salehye\Invoicing\Tests\TestCase;

class ImprovementsTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['name' => 'Test Customer']);
    }

    public function test_it_throws_when_discount_without_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('discount_type is required');

        app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Missing Discount Type',
            'discount' => 10,
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
    }

    public function test_it_allows_zero_discount_without_type(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'No Discount',
            'discount' => 0,
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertEquals(0, (float) $invoice->discount);
        $this->assertNull($invoice->discount_type);
    }

    public function test_payment_status_can_transition_to(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Success));
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Failed));
        $this->assertFalse(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Refunded));

        $this->assertTrue(PaymentStatus::AwaitingVerification->canTransitionTo(PaymentStatus::Success));
        $this->assertTrue(PaymentStatus::AwaitingVerification->canTransitionTo(PaymentStatus::Failed));

        $this->assertTrue(PaymentStatus::Success->canTransitionTo(PaymentStatus::Refunded));
        $this->assertFalse(PaymentStatus::Success->canTransitionTo(PaymentStatus::Failed));

        $this->assertFalse(PaymentStatus::Failed->canTransitionTo(PaymentStatus::Success));
        $this->assertFalse(PaymentStatus::Refunded->canTransitionTo(PaymentStatus::Success));
    }

    public function test_it_throws_on_invalid_payment_status_transition(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Failed Payment',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->recordPayment($invoice, 'manual', 100);
        $processor->markAsFailed($payment);

        $this->expectException(PaymentStatusTransitionException::class);
        $processor->markAsSuccess($payment);
    }

    public function test_it_throws_when_marking_failed_payment_as_failed(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Double Fail',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->recordPayment($invoice, 'manual', 100);
        $processor->markAsFailed($payment);

        $this->expectException(PaymentStatusTransitionException::class);
        $processor->markAsFailed($payment);
    }

    public function test_invoice_is_fully_paid(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Fully Paid',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $this->assertFalse($invoice->isFullyPaid());

        $processor = app(PaymentProcessor::class);
        $processor->recordPayment($invoice, 'manual', 100);
        $processor->markAsSuccess($invoice->payments()->first());

        $this->assertTrue($invoice->fresh()->isFullyPaid());
    }

    public function test_invoice_has_lines(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Lines Check',
            'items' => [
                ['description' => 'Item 1', 'quantity' => 1, 'unit_price' => 100],
                ['description' => 'Item 2', 'quantity' => 2, 'unit_price' => 50],
            ],
        ]);

        $this->assertTrue($invoice->hasLines());
        $this->assertEquals(2, $invoice->lineCount());
    }

    public function test_invoice_has_no_lines(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Empty',
            'items' => [],
        ]);

        $this->assertFalse($invoice->hasLines());
        $this->assertEquals(0, $invoice->lineCount());
    }

    public function test_invoice_scope_for_tenant(): void
    {
        app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Tenant Invoice',
            'tenant_id' => 'tenant-1',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Other Tenant',
            'tenant_id' => 'tenant-2',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertEquals(1, Invoice::forTenant('tenant-1')->count());
    }

    public function test_invoice_scope_for_user(): void
    {
        app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'User Invoice',
            'user_id' => 1,
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Other User',
            'user_id' => 2,
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertEquals(1, Invoice::forUser(1)->count());
    }

    public function test_invoice_scope_status(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Draft',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertEquals(1, Invoice::status(InvoiceStatus::Draft)->count());
        $this->assertEquals(0, Invoice::status(InvoiceStatus::Paid)->count());
    }

    public function test_has_invoices_draft_scope(): void
    {
        app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Draft',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice2 = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Issued',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice2);

        $this->assertEquals(1, $this->customer->draftInvoices()->count());
    }

    public function test_has_invoices_canceled_scope(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Canceled',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->cancel($invoice);

        $this->assertEquals(1, $this->customer->canceledInvoices()->count());
    }

    public function test_has_invoices_overdue_includes_unpaid_past_due(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Past Due',
            'due_at' => now()->subDays(5),
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $this->assertEquals(1, $this->customer->overdueInvoices()->count());
    }

    public function test_has_invoices_total_paid_amount(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Paid',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 200],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);
        app(InvoiceManager::class)->markAsPaid($invoice);

        $this->assertEquals(200, $this->customer->totalPaidAmount());
    }
}
