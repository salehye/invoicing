<?php

namespace Salehye\Invoicing\Exceptions;

class GatewayNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $name, array $available)
    {
        parent::__construct("Payment gateway '{$name}' is not registered. Available: " . implode(', ', $available));
    }
}
