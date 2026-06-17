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
        // Sum line totals (which include line-level tax/discount)
        $subtotal = $invoice->lines()->sum('total');

        // Apply invoice-level discount
        $discountAmount = $discountType === DiscountType::Percentage
            ? $subtotal * ($discount / 100)
            : $discount;

        $afterDiscount = $subtotal - $discountAmount;

        // Apply invoice-level tax on the amount AFTER discount
        // Note: line-level tax is already baked into each line's total,
        // so this tax applies only on the pre-tax subtotal of lines.
        // If no line-level tax is used, this is the sole tax calculation.
        $taxAmount = $afterDiscount * ($taxRate / 100);
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
