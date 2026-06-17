<?php

namespace Salehye\Invoicing\Middleware;

use Closure;
use Illuminate\Http\Request;
use Salehye\Invoicing\Enums\InvoiceStatus;
use Salehye\Invoicing\Models\Invoice;

class EnsureInvoicePaid
{
    public function handle(Request $request, Closure $next, string $invoiceParameter = 'invoice')
    {
        $invoiceId = $request->route($invoiceParameter);

        if (! $invoiceId) {
            abort(400, 'Invoice identifier not found in request.');
        }

        $invoice = Invoice::findOrFail($invoiceId);

        if (! $invoice->isPaid()) {
            abort(403, 'Invoice must be paid before accessing this resource.');
        }

        return $next($request);
    }
}
