<?php

namespace Salehye\Invoicing\Services;

use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\WebhookEvent;
use Salehye\Invoicing\Exceptions\InvalidPaymentAmountException;
use Salehye\Invoicing\Exceptions\PaymentVerificationException;
use Salehye\Invoicing\Gateways\BankTransferGateway;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Models\Payment;
use Salehye\Invoicing\Enums\PaymentStatus;
use Salehye\Invoicing\Events\PaymentFailed;
use Salehye\Invoicing\Events\PaymentSucceeded;
use Salehye\Invoicing\Events\PaymentVerified;

class PaymentProcessor
{
    public function __construct(
        private readonly GatewayManager $gatewayManager,
    ) {
    }

    public function getGateway(?string $name = null): PaymentGateway
    {
        return $this->gatewayManager->gateway($name);
    }

    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl, ?string $gateway = null): array
    {
        return $this->getGateway($gateway)->createCheckout($invoice, $returnUrl, $cancelUrl);
    }

    public function recordPayment(Invoice $invoice, string $gateway, float $amount, ?string $transactionId = null, ?array $gatewayResponse = null, ?int $userId = null): Payment
    {
        if ($amount <= 0) {
            throw new InvalidPaymentAmountException($amount);
        }

        if ($amount > $invoice->remainingBalance()) {
            throw new InvalidPaymentAmountException($amount, $invoice->remainingBalance());
        }

        return $invoice->payments()->create([
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Pending,
            'user_id' => $userId,
            'gateway_response' => $gatewayResponse,
        ]);
    }

    /**
     * Initiate a bank transfer payment awaiting manual verification.
     */
    public function initiateBankTransfer(Invoice $invoice, ?string $proofFile = null, ?string $proofNotes = null, ?int $userId = null): Payment
    {
        $gateway = $this->gatewayManager->gateway('bank_transfer');

        if (!$gateway instanceof BankTransferGateway) {
            throw new \InvalidArgumentException("The 'bank_transfer' gateway must be an instance of BankTransferGateway.");
        }

        return $gateway->initiatePayment($invoice, $proofFile, $proofNotes, $userId);
    }

    /**
     * Admin verifies a payment — marks as success.
     */
    public function verify(Payment $payment, ?int $verifiedBy = null): Payment
    {
        if (!$payment->isAwaitingVerification()) {
            throw new PaymentVerificationException($payment->status);
        }

        $payment->update([
            'status' => PaymentStatus::Success,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        event(new PaymentVerified($payment));
        event(new PaymentSucceeded($payment));

        $invoice = $payment->invoice;
        if ($invoice->remainingBalance() <= 0 && $invoice->isUnpaid()) {
            app(InvoiceManager::class)->markAsPaid($invoice);
        }

        return $payment;
    }

    /**
     * Admin rejects a payment awaiting verification — marks as failed.
     */
    public function reject(Payment $payment, ?string $reason = null): Payment
    {
        if (!$payment->isAwaitingVerification()) {
            throw new PaymentVerificationException($payment->status);
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'proof_notes' => $reason ?? $payment->proof_notes,
        ]);

        event(new PaymentFailed($payment));

        return $payment;
    }

    public function markAsSuccess(Payment $payment): Payment
    {
        $payment->update(['status' => PaymentStatus::Success]);

        event(new PaymentSucceeded($payment));

        $invoice = $payment->invoice;
        if ($invoice->remainingBalance() <= 0 && $invoice->isUnpaid()) {
            app(InvoiceManager::class)->markAsPaid($invoice);
        }

        return $payment;
    }

    public function markAsFailed(Payment $payment): Payment
    {
        $payment->update(['status' => PaymentStatus::Failed]);

        event(new PaymentFailed($payment));

        return $payment;
    }

    public function handleWebhook(array $payload, ?string $gateway = null): ?WebhookEvent
    {
        return $this->getGateway($gateway)->handleWebhook($payload);
    }

    public function refund(Invoice $invoice, ?float $amount = null, ?string $gateway = null): bool
    {
        return $this->getGateway($gateway)->refund($invoice, $amount);
    }
}
