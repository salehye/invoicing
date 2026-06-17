<?php

namespace Salehye\Invoicing\Services;

use Illuminate\Contracts\Foundation\Application;
use Salehye\Invoicing\Contracts\PaymentGateway;
use Salehye\Invoicing\Exceptions\GatewayNotFoundException;

class GatewayManager
{
    private array $gateways = [];

    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function register(string $name, string $class): self
    {
        $this->gateways[$name] = $class;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->gateways[$name]);
    }

    public function names(): array
    {
        return array_keys($this->gateways);
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= config('invoicing.default_gateway', 'local');

        if (!$this->has($name)) {
            throw new GatewayNotFoundException($name, $this->names());
        }

        return $this->app->make($this->gateways[$name]);
    }
}
