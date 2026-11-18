<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_transaction_id',
        'verified_by',
        'status',
        'confirmed_amount',
        'confirmed_reference',
        'notes',
        'rejection_reason',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}