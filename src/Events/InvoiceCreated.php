<?php

namespace Salehye\Invoicing\Events;

use Salehye\Invoicing\Models\Invoice;

class InvoiceCreated
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {
    }
}
