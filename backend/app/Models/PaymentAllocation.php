<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_transaction_id',
        'invoice_id',
        'allocated_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiptItem(): HasOne
    {
        return $this->hasOne(ReceiptItem::class);
    }

    public function refundItem(): HasOne
    {
        return $this->hasOne(RefundItem::class);
    }
}
