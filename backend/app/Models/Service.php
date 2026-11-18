<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_code',
        'name',
        'category',
        'description',
        'default_unit',
        'default_unit_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
