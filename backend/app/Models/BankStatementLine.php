<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_statement_id',
        'line_number',
        'transaction_date',
        'description',
        'transaction_type',
        'amount',
        'running_balance',
        'reference_number',
        'payment_transaction_id',
        'refund_id',
        'match_status',
        'matched_by',
        'matched_at',
        'match_notes',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'matched_at' => 'datetime',
        ];
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
