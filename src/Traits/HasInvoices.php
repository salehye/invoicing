<?php

namespace Salehye\Invoicing\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Salehye\Invoicing\Enums\InvoiceStatus;
use Salehye\Invoicing\Models\Invoice;

trait HasInvoices
{
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    public function unpaidInvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Unpaid);
    }

    public function paidInvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Paid);
    }

    public function overdueInvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Overdue);
    }

    public function totalInvoiceBalance(): float
    {
        return (float) $this->unpaidInvoices()->sum('total');
    }
}
