<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'statement_number',
        'bank_account_id',
        'period_start',
        'period_end',
        'currency',
        'opening_balance',
        'closing_balance',
        'file_name',
        'file_path',
        'file_format',
        'total_rows',
        'matched_rows',
        'unmatched_rows',
        'status',
        'imported_by',
        'imported_at',
        'reconciled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'total_rows' => 'integer',
            'matched_rows' => 'integer',
            'unmatched_rows' => 'integer',
            'imported_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
