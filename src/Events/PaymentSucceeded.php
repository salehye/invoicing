<?php

namespace Salehye\Invoicing\Events;

use Salehye\Invoicing\Models\Payment;

class PaymentSucceeded
{
    public function __construct(
        public readonly Payment $payment,
    ) {
    }
}
