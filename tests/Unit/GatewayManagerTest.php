<?php

namespace Salehye\Invoicing\Tests\Unit;

use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\WebhookEvent;
use Salehye\Invoicing\Exceptions\GatewayNotFoundException;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Services\GatewayManager;
use Salehye\Invoicing\Services\PaymentProcessor;
use Salehye\Invoicing\Tests\TestCase;

class GatewayManagerTest extends TestCase
{
    public function test_it_registers_built_in_gateways(): void
    {
        $manager = app(GatewayManager::class);

        $this->assertTrue($manager->has('local'));
        $this->assertTrue($manager->has('stripe'));
        $this->assertTrue($manager->has('bank_transfer'));
        $this->assertCount(3, $manager->names());
    }

    public function test_it_resolves_default_gateway(): void
    {
        $gateway = app(GatewayManager::class)->gateway();

        $this->assertInstanceOf(PaymentGateway::class, $gateway);
        $this->assertInstanceOf(\Salehye\Invoicing\Gateways\LocalGateway::class, $gateway);
    }

    public function test_it_resolves_specific_gateway(): void
    {
        $gateway = app(GatewayManager::class)->gateway('stripe');

        $this->assertInstanceOf(\Salehye\Invoicing\Gateways\StripeGateway::class, $gateway);
    }

    public function test_it_throws_for_unregistered_gateway(): void
    {
        $this->expectException(GatewayNotFoundException::class);
        $this->expectExceptionMessage("Payment gateway 'unknown' is not registered");

        app(GatewayManager::class)->gateway('unknown');
    }

    public function test_it_registers_custom_gateway_at_runtime(): void
    {
        $manager = app(GatewayManager::class);

        $manager->register('paypal', CustomTestGateway::class);

        $this->assertTrue($manager->has('paypal'));

        $gateway = $manager->gateway('paypal');
        $this->assertInstanceOf(CustomTestGateway::class, $gateway);
    }

    public function test_payment_processor_uses_default_gateway(): void
    {
        $processor = app(PaymentProcessor::class);

        $gateway = $processor->getGateway();
        $this->assertInstanceOf(\Salehye\Invoicing\Gateways\LocalGateway::class, $gateway);
    }

    public function test_payment_processor_can_use_specific_gateway(): void
    {
        $manager = app(GatewayManager::class);
        $manager->register('paypal', CustomTestGateway::class);

        $processor = app(PaymentProcessor::class);

        $gateway = $processor->getGateway('paypal');
        $this->assertInstanceOf(CustomTestGateway::class, $gateway);
    }

    public function test_payment_processor_create_checkout_with_specific_gateway(): void
    {
        $manager = app(GatewayManager::class);
        $manager->register('paypal', CustomTestGateway::class);

        $processor = app(PaymentProcessor::class);

        $customer = \Salehye\Invoicing\Tests\Models\Customer::create(['name' => 'Test']);
        $invoice = app(\Salehye\Invoicing\Services\InvoiceManager::class)->create([
            'billable' => $customer,
            'title' => 'Gateway Test',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $result = $processor->createCheckout($invoice, '/success', '/cancel', 'paypal');

        $this->assertEquals('paypal_checkout_url', $result['checkout_url']);
    }
}

// Custom gateway for testing
class CustomTestGateway implements PaymentGateway
{
    public function createCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        return [
            'checkout_url' => 'paypal_checkout_url',
            'invoice_id' => $invoice->id,
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
