<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'approver_id',
        'approval_level',
        'status',
        'comments',
        'assigned_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'approval_level' => 'integer',
            'assigned_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
