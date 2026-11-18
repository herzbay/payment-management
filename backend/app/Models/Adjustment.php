<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_number',
        'invoice_id',
        'customer_id',
        'created_by',
        'applied_by',
        'adjustment_type',
        'direction',
        'currency',
        'amount',
        'reference_number',
        'adjustment_date',
        'status',
        'reason',
        'description',
        'rejection_reason',
        'notes',
        'approved_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'adjustment_date' => 'date',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AdjustmentApproval::class)
            ->orderBy('approval_level');
    }
}
