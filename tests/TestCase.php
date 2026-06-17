<?php

namespace Salehye\Invoicing\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Salehye\Invoicing\InvoicingServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [InvoicingServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('invoicing.currency', 'USD');
        $app['config']->set('invoicing.default_gateway', 'local');
        $app['config']->set('invoicing.gateways.local.auto_succeed', true);
        $app['config']->set('invoicing.user_model', \Salehye\Invoicing\Tests\Models\Customer::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }
}
