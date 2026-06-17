<?php

namespace Salehye\Invoicing\Events;

use Salehye\Invoicing\Models\Invoice;

class InvoiceRefunded
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {
    }
}
