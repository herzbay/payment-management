<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_number',
        'payment_transaction_id',
        'customer_id',
        'requested_by',
        'processed_by',
        'refund_date',
        'currency',
        'refund_amount',
        'refund_method',
        'destination_account',
        'refund_reference',
        'status',
        'reason',
        'rejection_reason',
        'notes',
        'approved_at',
        'processed_at',
        'completed_at',
        'proof_path',
    ];

    protected function casts(): array
    {
        return [
            'refund_date' => 'date',
            'refund_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    public function bankStatementLines()
    {
        return $this->hasMany(
            \App\Models\BankStatementLine::class,
            'refund_id'
        );
    }
}
