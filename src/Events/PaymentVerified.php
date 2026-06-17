<?php

namespace Salehye\Invoicing\Events;

use Salehye\Invoicing\Models\Payment;

class PaymentVerified
{
    public function __construct(
        public readonly Payment $payment,
    ) {
    }
}
