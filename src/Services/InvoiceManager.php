<?php

namespace Salehye\Invoicing\Services;

use Illuminate\Support\Facades\DB;
use Salehye\Invoicing\Contracts\DiscountCalculator;
use Salehye\Invoicing\Contracts\TaxCalculator;
use Salehye\Invoicing\Enums\DiscountType;
use Salehye\Invoicing\Enums\InvoiceStatus;
use Salehye\Invoicing\Events\InvoiceCanceled;
use Salehye\Invoicing\Events\InvoiceCreated;
use Salehye\Invoicing\Events\InvoicePaid;
use Salehye\Invoicing\Events\InvoiceRefunded;
use Salehye\Invoicing\Events\InvoiceUpdated;
use Salehye\Invoicing\Exceptions\InvoiceStatusTransitionException;
use Salehye\Invoicing\Models\Invoice;
use Salehye\Invoicing\Models\InvoiceLine;

class InvoiceManager
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly TotalCalculator $totalCalculator,
    ) {
    }

    public function create(array $attributes): Invoice
    {
        return DB::transaction(function () use ($attributes) {
            $billable = $attributes['billable'] ?? null;
            $items = $attributes['items'] ?? [];
            $currency = $attributes['currency'] ?? config('invoicing.currency', 'USD');
            $dueAt = $attributes['due_at'] ?? null;
            $title = $attributes['title'] ?? '';

            $invoice = Invoice::create([
                'billable_type' => $billable?->getMorphClass(),
                'billable_id' => $billable?->getKey(),
                'user_id' => $attributes['user_id'] ?? null,
                'tenant_id' => $attributes['tenant_id'] ?? null,
                'number' => $this->numberGenerator->generate(),
                'title' => $title,
                'description' => $attributes['description'] ?? null,
                'currency' => $currency,
                'status' => InvoiceStatus::Draft,
                'due_at' => $dueAt,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            foreach ($items as $item) {
                $this->addLine($invoice, $item);
            }

            $discount = $attributes['discount'] ?? 0;
            $discountType = isset($attributes['discount_type'])
                ? DiscountType::from($attributes['discount_type'])
                : null;

            if ($discount > 0 && $discountType === null) {
                throw new \InvalidArgumentException('discount_type is required when discount is greater than 0.');
            }

            $tax = $attributes['tax'] ?? config('invoicing.default_tax_rate', 0);

            $this->totalCalculator->applyTotals(
                $invoice,
                discount: $discount,
                discountType: $discountType,
                taxRate: $tax,
            );

            event(new InvoiceCreated($invoice));

            return $invoice;
        });
    }

    public function addLine(Invoice $invoice, array $item): InvoiceLine
    {
        $quantity = $item['quantity'] ?? 1;
        $unitPrice = $item['unit_price'] ?? 0;
        $lineDiscount = $item['discount'] ?? 0;
        $lineTax = $item['tax'] ?? 0;

        $lineTotal = ($unitPrice * $quantity) - $lineDiscount + $lineTax;

        return $invoice->lines()->create([
            'description' => $item['description'] ?? '',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $lineDiscount,
            'tax' => $lineTax,
            'total' => $lineTotal,
            'metadata' => $item['metadata'] ?? null,
        ]);
    }

    public function markAsIssued(Invoice $invoice): Invoice
    {
        if (!$invoice->isDraft()) {
            throw new InvoiceStatusTransitionException($invoice->status, InvoiceStatus::Unpaid);
        }

        $invoice->update([
            'status' => InvoiceStatus::Unpaid,
            'issued_at' => now(),
        ]);

        event(new InvoiceUpdated($invoice));

        return $invoice;
    }

    public function markAsPaid(Invoice $invoice): Invoice
    {
        if (!$invoice->isUnpaid()) {
            throw new InvoiceStatusTransitionException($invoice->status, InvoiceStatus::Paid);
        }

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        event(new InvoicePaid($invoice));

        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (!$invoice->status->canTransitionTo(InvoiceStatus::Canceled)) {
            throw new InvoiceStatusTransitionException($invoice->status, InvoiceStatus::Canceled);
        }

        $invoice->update(['status' => InvoiceStatus::Canceled]);

        event(new InvoiceCanceled($invoice));

        return $invoice;
    }

    public function refund(Invoice $invoice): Invoice
    {
        if (!$invoice->isPaid()) {
            throw new InvoiceStatusTransitionException($invoice->status, InvoiceStatus::Refunded);
        }

        $invoice->update(['status' => InvoiceStatus::Refunded]);

        event(new InvoiceRefunded($invoice));

        return $invoice;
    }

    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $this->totalCalculator->applyTotals($invoice);

        event(new InvoiceUpdated($invoice));

        return $invoice;
    }
}
