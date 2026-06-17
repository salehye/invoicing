<?php

namespace Salehye\Invoicing\Tests\Unit;

use Salehye\Invoicing\Enums\DiscountType;
use Salehye\Invoicing\Enums\InvoiceStatus;
use Salehye\Invoicing\Exceptions\InvoiceStatusTransitionException;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Models\InvoiceLine;
use Salehye\Invoicing\Services\InvoiceManager;
use Salehye\Invoicing\Tests\Models\Customer;
use Salehye\Invoicing\Tests\TestCase;

class InvoiceManagerTest extends TestCase
{
    private function createCustomer(): Customer
    {
        return Customer::create(['name' => 'Test Customer']);
    }

    public function test_it_creates_invoice_with_items(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Test Invoice',
            'currency' => 'SAR',
            'items' => [
                ['description' => 'Product 1', 'quantity' => 2, 'unit_price' => 150],
                ['description' => 'Product 2', 'quantity' => 1, 'unit_price' => 300],
            ],
        ]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(InvoiceStatus::Draft, $invoice->status);
        $this->assertEquals('SAR', $invoice->currency);
        $this->assertEquals('Test Invoice', $invoice->title);
        $this->assertEquals(2, $invoice->lines()->count());

        // Subtotal = (150*2) + 300 = 600
        $this->assertEquals(600, (float) $invoice->subtotal);
    }

    public function test_it_creates_invoice_with_percentage_discount_and_tax(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Discount & Tax Invoice',
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000],
            ],
            'discount' => 10,
            'discount_type' => DiscountType::Percentage->value,
            'tax' => 15,
        ]);

        // Subtotal = 1000, Discount = 10% = 100, After discount = 900, Tax = 15% of 900 = 135, Total = 1035
        $this->assertEquals(1000, (float) $invoice->subtotal);
        $this->assertEquals(100, (float) $invoice->discount);
        $this->assertEquals(DiscountType::Percentage, $invoice->discount_type);
        $this->assertEquals(135, (float) $invoice->tax);
        $this->assertEquals(1035, (float) $invoice->total);
    }

    public function test_it_creates_invoice_with_fixed_discount(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Fixed Discount Invoice',
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000],
            ],
            'discount' => 50,
            'discount_type' => DiscountType::Fixed->value,
            'tax' => 15,
        ]);

        // Subtotal = 1000, Discount = 50 (fixed), After discount = 950, Tax = 15% of 950 = 142.5, Total = 1092.5
        $this->assertEquals(1000, (float) $invoice->subtotal);
        $this->assertEquals(50, (float) $invoice->discount);
        $this->assertEquals(DiscountType::Fixed, $invoice->discount_type);
        $this->assertEquals(142.5, (float) $invoice->tax);
        $this->assertEquals(1092.5, (float) $invoice->total);
    }

    public function test_it_generates_unique_invoice_number(): void
    {
        $customer = $this->createCustomer();

        $invoice1 = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Invoice 1',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice2 = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Invoice 2',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertNotEquals($invoice1->number, $invoice2->number);
        $this->assertStringStartsWith('INV', $invoice1->number);
    }

    public function test_it_marks_invoice_as_issued(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Issued Invoice',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertTrue($invoice->isDraft());

        app(InvoiceManager::class)->markAsIssued($invoice);

        $this->assertTrue($invoice->isUnpaid());
        $this->assertNotNull($invoice->issued_at);
    }

    public function test_it_marks_invoice_as_paid(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Paid Invoice',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        app(InvoiceManager::class)->markAsIssued($invoice);
        app(InvoiceManager::class)->markAsPaid($invoice);

        $this->assertTrue($invoice->isPaid());
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_it_cancels_draft_invoice(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Canceled Invoice',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        app(InvoiceManager::class)->cancel($invoice);

        $this->assertTrue($invoice->isCanceled());
    }

    public function test_it_refunds_paid_invoice(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Refunded Invoice',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        app(InvoiceManager::class)->markAsIssued($invoice);
        app(InvoiceManager::class)->markAsPaid($invoice);
        app(InvoiceManager::class)->refund($invoice);

        $this->assertTrue($invoice->isRefunded());
    }

    public function test_it_throws_when_invalid_status_transition(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Invalid Transition',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->expectException(InvoiceStatusTransitionException::class);
        app(InvoiceManager::class)->markAsPaid($invoice);
    }

    public function test_invoice_belongs_to_billable(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Billable Test',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertEquals($customer->id, $invoice->billable->id);
        $this->assertEquals(Customer::class, $invoice->billable_type);
    }

    public function test_invoice_has_lines(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Lines Test',
            'items' => [
                ['description' => 'Line 1', 'quantity' => 2, 'unit_price' => 50],
                ['description' => 'Line 2', 'quantity' => 3, 'unit_price' => 100],
            ],
        ]);

        $lines = $invoice->lines;
        $this->assertCount(2, $lines);
        $this->assertEquals('Line 1', $lines[0]->description);
        $this->assertEquals(2, $lines[0]->quantity);
    }

    public function test_invoice_is_overdue(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Overdue Test',
            'due_at' => now()->subDays(5),
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        app(InvoiceManager::class)->markAsIssued($invoice);

        $this->assertTrue($invoice->isOverdue());
    }

    public function test_invoice_can_be_marked_as_overdue_status(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Overdue Status Test',
            'due_at' => now()->subDays(5),
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        app(InvoiceManager::class)->markAsIssued($invoice);

        $this->assertTrue($invoice->status->canTransitionTo(InvoiceStatus::Overdue));

        $invoice->update(['status' => InvoiceStatus::Overdue]);

        $this->assertEquals(InvoiceStatus::Overdue, $invoice->status);
        $this->assertTrue($invoice->isOverdue());
    }

    public function test_it_creates_invoice_without_billable(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'title' => 'Standalone Invoice',
            'currency' => 'USD',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertNull($invoice->billable_type);
        $this->assertNull($invoice->billable_id);
        $this->assertNull($invoice->billable);
        $this->assertEquals('Standalone Invoice', $invoice->title);
    }

    public function test_it_creates_invoice_without_tenant_id(): void
    {
        $customer = $this->createCustomer();

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'No Tenant',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertNull($invoice->tenant_id);
    }
}
