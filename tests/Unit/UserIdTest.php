<?php

namespace Salehye\Invoicing\Tests\Unit;

use Salehye\Invoicing\Enums\PaymentStatus;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Models\Payment;
use Salehye\Invoicing\Services\InvoiceManager;
use Salehye\Invoicing\Services\PaymentProcessor;
use Salehye\Invoicing\Tests\Models\Customer;
use Salehye\Invoicing\Tests\TestCase;

class UserIdTest extends TestCase
{
    private Customer $customer;
    private Customer $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['name' => 'Billable Customer']);
        $this->user = Customer::create(['name' => 'Operating User']);
    }

    public function test_invoice_can_be_created_with_user_id(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'Invoice with User',
            'user_id'  => $this->user->id,
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertEquals($this->user->id, $invoice->user_id);
    }

    public function test_invoice_user_id_is_nullable(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'No User',
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertNull($invoice->user_id);
    }

    public function test_invoice_user_relationship(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'User Relation',
            'user_id'  => $this->user->id,
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $this->assertInstanceOf(Customer::class, $invoice->user);
        $this->assertEquals($this->user->id, $invoice->user->id);
        $this->assertEquals('Operating User', $invoice->user->name);
    }

    public function test_payment_can_be_recorded_with_user_id(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'Payment User',
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 200],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->recordPayment(
            invoice: $invoice,
            gateway: 'manual',
            amount: 200,
            transactionId: 'TXN-001',
            userId: $this->user->id,
        );

        $this->assertEquals($this->user->id, $payment->user_id);
    }

    public function test_payment_user_id_is_nullable(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'No Payment User',
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->recordPayment(
            invoice: $invoice,
            gateway: 'manual',
            amount: 100,
        );

        $this->assertNull($payment->user_id);
    }

    public function test_payment_user_relationship(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'Payment Relation',
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 300],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->recordPayment(
            invoice: $invoice,
            gateway: 'manual',
            amount: 300,
            userId: $this->user->id,
        );

        $this->assertInstanceOf(Customer::class, $payment->user);
        $this->assertEquals($this->user->id, $payment->user->id);
    }

    public function test_bank_transfer_inherits_user_id_from_invoice(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'Bank Transfer User',
            'user_id'  => $this->user->id,
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 500],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->initiateBankTransfer($invoice);

        $this->assertEquals($this->user->id, $payment->user_id);
    }

    public function test_bank_transfer_with_explicit_user_id(): void
    {
        $otherUser = Customer::create(['name' => 'Other User']);
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title'    => 'Explicit Bank User',
            'user_id'  => $this->user->id,
            'items'    => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->initiateBankTransfer($invoice, null, null, $otherUser->id);

        $this->assertEquals($otherUser->id, $payment->user_id);
    }
}
