<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'service_id',
        'created_by',
        'subject',
        'description',
        'issue_date',
        'valid_until',
        'currency',
        'subtotal',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'total_amount',
        'status',
        'sent_at',
        'approved_at',
        'rejected_at',
        'internal_notes',
        'customer_notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(QuotationTerm::class)->orderBy('sort_order');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(QuotationApproval::class)
            ->orderBy('approval_level');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(QuotationStatusHistory::class)
            ->orderBy('changed_at');
    }

    public function agreement(): HasOne
    {
        return $this->hasOne(Agreement::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
