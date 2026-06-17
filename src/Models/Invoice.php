<?php

namespace Salehye\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Salehye\Invoicing\Enums\DiscountType;
use Salehye\Invoicing\Enums\InvoiceStatus;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'billable_type',
        'billable_id',
        'user_id',
        'tenant_id',
        'number',
        'title',
        'description',
        'currency',
        'subtotal',
        'discount',
        'discount_type',
        'tax',
        'total',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_type' => DiscountType::class,
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'status' => InvoiceStatus::class,
    ];

    public function getTable(): string
    {
        return config('invoicing.table_names.invoices', 'invoices');
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('invoicing.user_model', \App\Models\User::class));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isUnpaid(): bool
    {
        return $this->status === InvoiceStatus::Unpaid;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function isCanceled(): bool
    {
        return $this->status === InvoiceStatus::Canceled;
    }

    public function isRefunded(): bool
    {
        return $this->status === InvoiceStatus::Refunded;
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Overdue
            || ($this->isUnpaid() && $this->due_at !== null && $this->due_at->isPast());
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()
            ->where('status', 'success')
            ->sum('amount');
    }

    public function remainingBalance(): float
    {
        return max(0, (float) $this->total - $this->totalPaid());
    }

    public function isFullyPaid(): bool
    {
        return $this->remainingBalance() <= 0;
    }

    public function hasLines(): bool
    {
        return $this->lines()->count() > 0;
    }

    public function lineCount(): int
    {
        return $this->lines()->count();
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStatus($query, InvoiceStatus $status)
    {
        return $query->where('status', $status);
    }
}
