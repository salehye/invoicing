<?php

namespace Salehye\Invoicing\Services;

use Salehye\Invoicing\Contracts\DiscountCalculator;

class DefaultDiscountCalculator implements DiscountCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float
    {
        $amount = $metadata['discount_amount'] ?? 0;
        $type = $metadata['discount_type'] ?? 'fixed';

        if ($type === 'percentage') {
            return round($subtotal * ($amount / 100), 2);
        }

        return round($amount, 2);
    }
}
