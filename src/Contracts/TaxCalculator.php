<?php

namespace Salehye\Invoicing\Contracts;

interface TaxCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float;
}
