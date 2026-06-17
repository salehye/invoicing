<?php

namespace Salehye\Invoicing\Exceptions;

class InvalidPaymentAmountException extends \InvalidArgumentException
{
    public function __construct(float $amount, float $max = 0)
    {
        $message = $amount <= 0
            ? "Payment amount must be greater than 0, got {$amount}."
            : "Payment amount {$amount} exceeds remaining balance {$max}.";

        parent::__construct($message);
    }
}
