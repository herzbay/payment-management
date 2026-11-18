<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReceivable extends Model
{
    use HasFactory;

    protected $table = 'accounts_receivable';

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'currency',
        'invoice_amount',
        'paid_amount',
        'outstanding_amount',
        'due_date',
        'status',
        'last_payment_date',
        'last_reminder_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'due_date' => 'date',
            'last_payment_date' => 'date',
            'last_reminder_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}