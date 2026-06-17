<?php

namespace Salehye\Invoicing\Exceptions;

use Salehye\Invoicing\Enums\PaymentStatus;

class PaymentStatusTransitionException extends \RuntimeException
{
    public function __construct(PaymentStatus $from, PaymentStatus $to)
    {
        parent::__construct("Cannot transition payment from {$from->value} to {$to->value}.");
    }
}
