<?php

namespace Salehye\Invoicing\Gateways;

use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\WebhookEvent;
use Salehye\Invoicing\Enums\PaymentStatus;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Models\Payment;

class BankTransferGateway implements PaymentGateway
{
    /**
     * Bank transfers don't create checkout sessions.
     * Instead, returns bank details for the customer to transfer to.
     */
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        return [
            'type' => 'bank_transfer',
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'bank_details' => config('invoicing.gateways.bank_transfer.bank_details', []),
            'reference' => $invoice->number,
            'instructions' => config('invoicing.gateways.bank_transfer.instructions', 'Transfer the amount to the bank account and upload proof of payment.'),
        ];
    }

    /**
     * Bank transfers require manual verification — no webhook processing.
     */
    public function handleWebhook(array $payload): ?WebhookEvent
    {
        return null;
    }

    /**
     * Bank transfer payments always need verification.
     */
    public function getPaymentStatus(string $transactionId): string
    {
        return PaymentStatus::AwaitingVerification->value;
    }

    /**
     * Refunds on bank transfers are manual — mark as refunded in admin panel.
     */
    public function refund(Invoice $invoice, ?float $amount = null): bool
    {
        return true;
    }

    /**
     * Create a bank transfer payment that awaits admin verification.
     */
    public function initiatePayment(Invoice $invoice, ?string $proofFile = null, ?string $proofNotes = null, ?int $userId = null): Payment
    {
        return $invoice->payments()->create([
            'gateway' => 'bank_transfer',
            'transaction_id' => null,
            'amount' => $invoice->remainingBalance(),
            'currency' => $invoice->currency,
            'status' => PaymentStatus::AwaitingVerification,
            'user_id' => $userId ?? $invoice->user_id,
            'proof_file' => $proofFile,
            'proof_notes' => $proofNotes,
            'gateway_response' => [
                'reference' => $invoice->number,
                'type' => 'bank_transfer',
            ],
        ]);
    }
}
