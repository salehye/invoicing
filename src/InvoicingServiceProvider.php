<?php

namespace Salehye\Invoicing;

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\ServiceProvider;
use Salehye\Invoicing\Commands\MarkOverdueInvoices;
use Salehye\Invoicing\Contracts\DiscountCalculator;
use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Contracts\TaxCalculator;
use Salehye\Invoicing\Gateways\BankTransferGateway;
use Salehye\Invoicing\Gateways\LocalGateway;
use Salehye\Invoicing\Gateways\StripeGateway;
use Salehye\Invoicing\Middleware\EnsureInvoicePaid;
use Salehye\Invoicing\Services\DefaultDiscountCalculator;
use Salehye\Invoicing\Services\DefaultTaxCalculator;
use Salehye\Invoicing\Services\GatewayManager;
use Salehye\Invoicing\Services\InvoiceManager;
use Salehye\Invoicing\Services\PaymentProcessor;

class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/invoicing.php',
            'invoicing'
        );

        $this->app->singleton(TaxCalculator::class, DefaultTaxCalculator::class);
        $this->app->singleton(DiscountCalculator::class, DefaultDiscountCalculator::class);
        $this->app->singleton(InvoiceManager::class);
        $this->app->singleton(PaymentProcessor::class);

        // GatewayManager: central registry for all payment gateways
        $this->app->singleton(GatewayManager::class, function ($app) {
            $manager = new GatewayManager($app);

            // Register built-in gateways
            $manager->register('local', LocalGateway::class);
            $manager->register('stripe', StripeGateway::class);
            $manager->register('bank_transfer', BankTransferGateway::class);

            // Register any custom gateways from config
            foreach (config('invoicing.gateways', []) as $name => $config) {
                if (isset($config['driver']) && !$manager->has($name)) {
                    $manager->register($name, $config['driver']);
                }
            }

            return $manager;
        });

        // Default PaymentGateway resolves to the default_gateway from config
        $this->app->bind(PaymentGateway::class, function ($app) {
            return $app->make(GatewayManager::class)->gateway();
        });
    }

    public function boot(): void
    {
        // Register middleware alias (works in Laravel 11+ including 13)
        $this->registerMiddlewareAlias();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/invoicing.php' => config_path('invoicing.php'),
            ], 'invoicing-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'invoicing-migrations');

            $this->commands([
                MarkOverdueInvoices::class,
            ]);
        }
    }

    private function registerMiddlewareAlias(): void
    {
        if ($this->app->has(Middleware::class)) {
            $this->app->make(Middleware::class)->alias([
                'invoice.paid' => EnsureInvoicePaid::class,
            ]);
        }
    }
}
