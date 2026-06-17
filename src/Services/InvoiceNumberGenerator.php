<?php

namespace Salehye\Invoicing\Services;

use Salehye\Invoicing\Models\Invoice;

class InvoiceNumberGenerator
{
    public function generate(): string
    {
        $format = config('invoicing.invoice_number_format', '{prefix}-{year}-{sequence}');
        $prefix = config('invoicing.invoice_number_prefix', 'INV');
        $seqLength = config('invoicing.invoice_number_sequence_length', 4);

        $year = now()->year;
        $month = now()->month;

        $lastInvoice = Invoice::where('number', 'like', "{$prefix}-{$year}-*")
            ->orderByDesc('id')
            ->first();

        $sequence = $lastInvoice
            ? (int) substr($lastInvoice->number, -(int) $seqLength) + 1
            : 1;

        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $paddedSequence = str_pad((string) $sequence, $seqLength, '0', STR_PAD_LEFT);

            $number = str_replace(
                ['{prefix}', '{year}', '{month}', '{sequence}'],
                [$prefix, $year, $month, $paddedSequence],
                $format,
            );

            if (!Invoice::where('number', $number)->exists()) {
                return $number;
            }

            $sequence++;
        }

        throw new \RuntimeException("Unable to generate a unique invoice number after {$maxAttempts} attempts.");
    }
}
