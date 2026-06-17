<?php

namespace Salehye\Invoicing\Gateways;

use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\WebhookEvent;
use Salehye\Invoicing\Models\Invoice;

class LocalGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        $autoSucceed = config('invoicing.gateways.local.auto_succeed', true);

        return [
            'checkout_url' => null,
            'auto_succeed' => $autoSucceed,
            'invoice_id'   => $invoice->id,
            'amount'       => $invoice->total,
            'currency'     => $invoice->currency,
        ];
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
