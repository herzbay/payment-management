<?php

namespace Database\Seeders;

use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSettingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('role', function ($query) {
            $query->where('slug', 'super-admin');
        })->first();

        $settings = [
            // General Settings
            [
                'setting_key' => 'default_currency',
                'setting_group' => 'general',
                'setting_value' => 'IDR',
                'value_type' => 'string',
                'label' => 'Default Currency',
                'description' => 'Default currency used throughout the application.',
                'is_public' => true,
            ],
            [
                'setting_key' => 'timezone',
                'setting_group' => 'general',
                'setting_value' => 'Asia/Jakarta',
                'value_type' => 'string',
                'label' => 'Application Timezone',
                'description' => 'Default timezone for application date and time.',
                'is_public' => true,
            ],
            [
                'setting_key' => 'date_format',
                'setting_group' => 'general',
                'setting_value' => 'd/m/Y',
                'value_type' => 'string',
                'label' => 'Date Format',
                'description' => 'Default date display format.',
                'is_public' => true,
            ],
            [
                'setting_key' => 'decimal_places',
                'setting_group' => 'general',
                'setting_value' => 2,
                'value_type' => 'integer',
                'label' => 'Decimal Places',
                'description' => 'Number of decimal places for monetary values.',
                'is_public' => true,
            ],

            // Document Numbering
            [
                'setting_key' => 'quotation_number_prefix',
                'setting_group' => 'numbering',
                'setting_value' => 'QUO',
                'value_type' => 'string',
                'label' => 'Quotation Number Prefix',
                'description' => 'Prefix for quotation document numbers.',
                'is_public' => false,
            ],
            [
                'setting_key' => 'agreement_number_prefix',
                'setting_group' => 'numbering',
                'setting_value' => 'AGR',
                'value_type' => 'string',
                'label' => 'Agreement Number Prefix',
                'description' => 'Prefix for agreement document numbers.',
                'is_public' => false,
            ],
            [
                'setting_key' => 'invoice_number_prefix',
                'setting_group' => 'numbering',
                'setting_value' => 'INV',
                'value_type' => 'string',
                'label' => 'Invoice Number Prefix',
                'description' => 'Prefix for invoice document numbers.',
                'is_public' => false,
            ],
            [
                'setting_key' => 'payment_number_prefix',
                'setting_group' => 'numbering',
                'setting_value' => 'PAY',
                'value_type' => 'string',
                'label' => 'Payment Number Prefix',
                'description' => 'Prefix for payment transaction numbers.',
                'is_public' => false,
            ],
            [
                'setting_key' => 'receipt_number_prefix',
                'setting_group' => 'numbering',
                'setting_value' => 'RCP',
                'value_type' => 'string',
                'label' => 'Receipt Number Prefix',
                'description' => 'Prefix for receipt document numbers.',
                'is_public' => false,
            ],
            [
                'setting_key' => 'refund_number_prefix',
                'setting_group' => 'numbering',
                'setting_value' => 'REF',
                'value_type' => 'string',
                'label' => 'Refund number Prefix',
                'description' => 'Prefix for refund transaction numbers.',
                'is_public' => false,
            ],
            [
                'setting_key' => 'adjustment_number_prefix',
                'setting_group' => 'numbering',
                'setting_value' => 'ADJ',
                'value_type' => 'string',
                'label' => 'Adjustment Number Prefix',
                'description' => 'Prefix for adjustment transaction numbers.',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            PaymentSetting::updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                [
                    ...$setting,
                    'is_active' => true,
                    'updated_by' => $admin?->id,
                ]
            );
        }
    }
}
