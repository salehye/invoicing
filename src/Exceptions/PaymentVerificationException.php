<?php

namespace Salehye\Invoicing\Exceptions;

use Salehye\Invoicing\Enums\PaymentStatus;

class PaymentVerificationException extends \RuntimeException
{
    public function __construct(PaymentStatus $status)
    {
        parent::__construct("Cannot verify/reject payment with status: {$status->value}. Only awaiting_verification payments can be verified.");
    }
}
