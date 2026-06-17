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

    public function draftInvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Draft);
    }

    public function unpaidInvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Unpaid);
    }

    public function paidInvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Paid);
    }

    public function canceledInvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Canceled);
    }

    public function overdueInvoices(): MorphMany
    {
        return $this->invoices()->where(function ($query) {
            $query->where('status', InvoiceStatus::Overdue)
                ->orWhere(function ($q) {
                    $q->where('status', InvoiceStatus::Unpaid)
                        ->where('due_at', '<', now())
                        ->whereNotNull('due_at');
                });
        });
    }

    public function refundedinvoices(): MorphMany
    {
        return $this->invoices()->where('status', InvoiceStatus::Refunded);
    }

    public function totalInvoiceBalance(): float
    {
        return (float) $this->unpaidInvoices()->sum('total');
    }

    public function totalPaidAmount(): float
    {
        return (float) $this->paidInvoices()->sum('total');
    }
}
