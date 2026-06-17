<?php

namespace Salehye\Invoicing\Events;

use Salehye\Invoicing\Models\Invoice;

class InvoiceCanceled
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {
    }
}
