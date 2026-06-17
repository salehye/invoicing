<?php

namespace Salehye\Invoicing\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingVerification = 'awaiting_verification';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function canTransitionTo(PaymentStatus $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Success, self::Failed]),
            self::AwaitingVerification => in_array($target, [self::Success, self::Failed]),
            self::Success => in_array($target, [self::Refunded]),
            self::Failed => false,
            self::Refunded => false,
        };
    }

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
