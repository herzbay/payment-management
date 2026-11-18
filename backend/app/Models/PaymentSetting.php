<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'setting_group',
        'setting_value',
        'value_type',
        'label',
        'description',
        'is_public',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
