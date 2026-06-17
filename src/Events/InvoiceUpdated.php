<?php

namespace Salehye\Invoicing\Events;

use Salehye\Invoicing\Models\Invoice;

class InvoiceUpdated
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {
    }
}
