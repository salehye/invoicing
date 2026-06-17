<?php

namespace Salehye\Invoicing\Services;

use Salehye\Invoicing\Contracts\DiscountCalculator;
use Salehye\Invoicing\Contracts\TaxCalculator;
use Salehye\Invoicing\Enums\DiscountType;
use Salehye\Invoicing\Models\Invoice;

class TotalCalculator
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
        private readonly DiscountCalculator $discountCalculator,
    ) {
    }

    public function applyTotals(
        Invoice $invoice,
        float $discount = 0,
        ?DiscountType $discountType = null,
        float $taxRate = 0,
    ): Invoice {
        $subtotal = $invoice->lines()->sum('total');

        $discountMetadata = [
            'discount_amount' => $discount,
            'discount_type' => $discountType?->value ?? 'fixed',
        ];
        $discountAmount = $this->discountCalculator->calculate($subtotal, $discountMetadata);

        $afterDiscount = $subtotal - $discountAmount;

        $taxMetadata = [
            'tax_rate' => $taxRate,
        ];
        $taxAmount = $this->taxCalculator->calculate($afterDiscount, $taxMetadata);

        $total = $afterDiscount + $taxAmount;

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'discount' => round($discountAmount, 2),
            'discount_type' => $discountType,
            'tax' => round($taxAmount, 2),
            'total' => round($total, 2),
        ]);

        return $invoice;
    }
}
