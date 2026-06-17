<?php

namespace Salehye\Invoicing\Contracts;

interface DiscountCalculator
{
    public function calculate(float $subtotal, ?array $metadata = null): float;
}
