<?php

namespace Salehye\Invoicing\Exceptions;

use Salehye\Invoicing\Enums\InvoiceStatus;
use Salehye\Invoicing\Enums\PaymentStatus;

class InvoiceStatusTransitionException extends \RuntimeException
{
    public function __construct(InvoiceStatus $from, InvoiceStatus $to)
    {
        parent::__construct("Cannot transition invoice from {$from->value} to {$to->value}.");
    }
}
