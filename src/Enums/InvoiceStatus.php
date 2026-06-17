<?php

namespace Salehye\Invoicing\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case Overdue = 'overdue';

    public function canTransitionTo(InvoiceStatus $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Unpaid, self::Canceled]),
            self::Unpaid => in_array($target, [self::Paid, self::Canceled, self::Overdue]),
            self::Overdue => in_array($target, [self::Paid, self::Canceled]),
            self::Paid => in_array($target, [self::Refunded]),
            self::Canceled => false,
            self::Refunded => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Unpaid => 'Unpaid',
            self::Paid => 'Paid',
            self::Canceled => 'Canceled',
            self::Refunded => 'Refunded',
            self::Overdue => 'Overdue',
        };
    }
}
