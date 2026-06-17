<?php

namespace Salehye\Invoicing\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingVerification = 'awaiting_verification';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingVerification => 'Awaiting Verification',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }

    public function needsVerification(): bool
    {
        return $this === self::AwaitingVerification;
    }
}
