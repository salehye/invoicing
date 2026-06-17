<?php

namespace Salehye\Invoicing\Services;

use Salehye\Invoicing\Contracts\TaxCalculator;

class DefaultTaxCalculator implements TaxCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float
    {
        $rate = $metadata['tax_rate'] ?? config('invoicing.default_tax_rate', 0);

        return round($subtotal * ($rate / 100), 2);
    }
}
