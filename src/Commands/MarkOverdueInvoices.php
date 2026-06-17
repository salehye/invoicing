<?php

namespace Salehye\Invoicing\Commands;

use Illuminate\Console\Command;
use Salehye\Invoicing\Enums\InvoiceStatus;
use Salehye\Invoicing\Models\Invoice;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoicing:mark-overdue';

    protected $description = 'Mark unpaid invoices past their due date as overdue';

    public function handle(): int
    {
        $thresholdDays = config('invoicing.overdue_threshold_days', 0);
        $overdueDate = now()->subDays($thresholdDays);

        $invoices = Invoice::where('status', InvoiceStatus::Unpaid)
            ->where('due_at', '<', $overdueDate)
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No overdue invoices found.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($invoices as $invoice) {
            if ($invoice->status->canTransitionTo(InvoiceStatus::Overdue)) {
                $invoice->update(['status' => InvoiceStatus::Overdue]);
                $count++;
            }
        }

        $this->info("Marked {$count} invoices as overdue.");

        return self::SUCCESS;
    }
}
