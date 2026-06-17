<?php

namespace Salehye\Invoicing\Tests\Unit;

use Salehye\Invoicing\Enums\PaymentStatus;
use Salehye\Invoicing\Exceptions\PaymentVerificationException;
use Salehye\Invoicing\Gateways\BankTransferGateway;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Models\Payment;
use Salehye\Invoicing\Services\GatewayManager;
use Salehye\Invoicing\Services\InvoiceManager;
use Salehye\Invoicing\Services\PaymentProcessor;
use Salehye\Invoicing\Tests\Models\Customer;
use Salehye\Invoicing\Tests\TestCase;

class BankTransferTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['name' => 'Bank Customer']);
    }

    public function test_bank_transfer_gateway_is_registered(): void
    {
        $manager = app(GatewayManager::class);

        $this->assertTrue($manager->has('bank_transfer'));
        $this->assertInstanceOf(BankTransferGateway::class, $manager->gateway('bank_transfer'));
    }

    public function test_bank_transfer_checkout_returns_bank_details(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Bank Transfer Invoice',
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 500],
            ],
        ]);

        $processor = app(PaymentProcessor::class);
        $result = $processor->createCheckout($invoice, '/success', '/cancel', 'bank_transfer');

        $this->assertEquals('bank_transfer', $result['type']);
        $this->assertEquals($invoice->id, $result['invoice_id']);
        $this->assertEquals($invoice->number, $result['reference']);
        $this->assertArrayHasKey('bank_details', $result);
        $this->assertArrayHasKey('instructions', $result);
    }

    public function test_initiate_bank_transfer_creates_awaiting_verification_payment(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Bank Transfer',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->initiateBankTransfer($invoice, 'receipt.pdf', 'Paid via Al Rajhi Bank');

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals('bank_transfer', $payment->gateway);
        $this->assertEquals(PaymentStatus::AwaitingVerification, $payment->status);
        $this->assertEquals('receipt.pdf', $payment->proof_file);
        $this->assertEquals('Paid via Al Rajhi Bank', $payment->proof_notes);
        $this->assertEquals(100, (float) $payment->amount);
    }

    public function test_admin_verify_payment(): void
    {
        $admin = Customer::create(['name' => 'Admin User']);

        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Verify Test',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 200],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->initiateBankTransfer($invoice);

        $verified = $processor->verify($payment, $admin->id);

        $this->assertEquals(PaymentStatus::Success, $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertEquals($admin->id, $verified->verified_by);
    }

    public function test_verify_auto_marks_invoice_as_paid(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Full Pay Test',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 300],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->initiateBankTransfer($invoice);
        $processor->verify($payment);

        $this->assertTrue($invoice->fresh()->isPaid());
    }

    public function test_admin_reject_payment(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Reject Test',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->initiateBankTransfer($invoice);

        $rejected = $processor->reject($payment, 'Invalid receipt');

        $this->assertEquals(PaymentStatus::Failed, $rejected->status);
        $this->assertEquals('Invalid receipt', $rejected->proof_notes);
    }

    public function test_cannot_verify_non_awaiting_payment(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Invalid Verify',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->recordPayment($invoice, 'manual', 100);

        $this->expectException(PaymentVerificationException::class);
        $processor->verify($payment);
    }

    public function test_cannot_reject_non_awaiting_payment(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Invalid Reject',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->recordPayment($invoice, 'manual', 100);

        $this->expectException(PaymentVerificationException::class);
        $processor->reject($payment);
    }

    public function test_payment_needs_verification_helper(): void
    {
        $this->assertTrue(PaymentStatus::AwaitingVerification->needsVerification());
        $this->assertFalse(PaymentStatus::Pending->needsVerification());
        $this->assertFalse(PaymentStatus::Success->needsVerification());
    }

    public function test_payment_model_awaiting_verification_helper(): void
    {
        $invoice = app(InvoiceManager::class)->create([
            'billable' => $this->customer,
            'title' => 'Helper Test',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        app(InvoiceManager::class)->markAsIssued($invoice);

        $processor = app(PaymentProcessor::class);
        $payment = $processor->initiateBankTransfer($invoice);

        $this->assertTrue($payment->isAwaitingVerification());
        $this->assertTrue($payment->needsVerification());
    }
}
