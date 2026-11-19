<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'customer_id',
        'payment_date',
        'amount',
        'currency',
        'payment_method',
        'payment_channel',
        'bank_name',
        'account_name',
        'payment_reference',
        'payer_name',
        'proof_path',
        'proof_original_name',
        'status',
        'verified_at',
        'recorded_by',
        'notes',
    ];

    public function bankStatementLines()
    {
        return $this->hasMany(
            \App\Models\BankStatementLine::class,
            'payment_transaction_id'
        );
    }

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}