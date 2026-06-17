<?php

namespace Salehye\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Salehye\Invoicing\Enums\PaymentStatus;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'user_id',
        'gateway',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'gateway_response',
        'proof_file',
        'proof_notes',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'status' => PaymentStatus::class,
        'verified_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('invoicing.table_names.payments', 'payments');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('invoicing.user_model', \App\Models\User::class));
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(config('invoicing.user_model', \App\Models\User::class), 'verified_by');
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isAwaitingVerification(): bool
    {
        return $this->status === PaymentStatus::AwaitingVerification;
    }

    public function isSuccess(): bool
    {
        return $this->status === PaymentStatus::Success;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::Refunded;
    }

    public function needsVerification(): bool
    {
        return $this->status->needsVerification();
    }
}
