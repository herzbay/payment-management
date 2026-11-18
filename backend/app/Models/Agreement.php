<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'agreement_number',
        'quotation_id',
        'created_by',
        'title',
        'issue_date',
        'effective_date',
        'end_date',
        'status',
        'customer_signatory',
        'company_signatory',
        'sent_at',
        'customer_signed_at',
        'company_signed_at',
        'document_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'effective_date' => 'date',
            'end_date' => 'date',
            'sent_at' => 'datetime',
            'customer_signed_at' => 'datetime',
            'company_signed_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
