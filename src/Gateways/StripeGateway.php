<?php

namespace Salehye\Invoicing\Gateways;

use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\WebhookEvent;
use Salehye\Invoicing\Models\Invoice;

class StripeGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        $this->ensureStripeSdk();

        $apiKey = config('invoicing.gateways.stripe.api_key');

        // Stripe Checkout Session creation would go here
        // Full implementation requires stripe/stripe-php to be installed
        return [
            'checkout_url' => null,
            'session_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
            'currency' => strtolower($invoice->currency),
        ];
    }

    public function handleWebhook(array $payload): ?WebhookEvent
    {
        $this->ensureStripeSdk();

        // Stripe webhook handling would go here
        return null;
    }

    public function getPaymentStatus(string $transactionId): string
    {
        $this->ensureStripeSdk();

        // Stripe PaymentIntent status check would go here
        return 'pending';
    }

    public function refund(Invoice $invoice, ?float $amount = null): bool
    {
        $this->ensureStripeSdk();

        // Stripe Refund creation would go here
        return false;
    }

    private function ensureStripeSdk(): void
    {
        if (!class_exists(\Stripe\Stripe::class)) {
            throw new \RuntimeException(
                'The stripe/stripe-php package is required to use the Stripe gateway. Install it with: composer require stripe/stripe-php'
            );
        }
    }
}
