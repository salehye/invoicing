<?php

namespace Salehye\Invoicing\Contracts;

use Salehye\Invoicing\Models\Invoice;

interface PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array;

    public function handleWebhook(array $payload): ?WebhookEvent;

    public function getPaymentStatus(string $transactionId): string;

    public function refund(Invoice $invoice, ?float $amount = null): bool;
}
