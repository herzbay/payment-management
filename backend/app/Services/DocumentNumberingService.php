<?php

namespace App\Services;

use App\Models\PaymentSetting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DocumentNumberingService
{
    /**
     * Available document types and their setting keys.
     *
     * @var array<string, string>
     */
    private const DOCUMENT_TYPES = [
        'quotation' => 'quotation_number_prefix',
        'agreement' => 'agreement_number_prefix',
        'invoice' => 'invoice_number_prefix',
        'payment' => 'payment_number_prefix',
        'receipt' => 'receipt_number_prefix',
        'refund' => 'refund_number_prefix',
        'adjustment' => 'adjustment_number_prefix',
    ];

    /**
     * Generate a unique document number.
     */
    public function generate(string $documentType): string
    {
        if (!array_key_exists($documentType, self::DOCUMENT_TYPES)) {
            throw new InvalidArgumentException(
                "Unsupported document type: {$documentType}"
            );
        }

        return DB::transaction(function () use ($documentType) {
            $settingKey = self::DOCUMENT_TYPES[$documentType];

            $setting = PaymentSetting::query()
                ->where('setting_key', $settingKey)
                ->where('is_active', true)
                ->first();

            if (!$setting || empty($setting->setting_value)) {
                throw new RuntimeException(
                    "Document prefix setting '{$settingKey}' is missing or inactive."
                );
            }

            $prefix = trim((string) $setting->setting_value);

            $period = now()->format('Y');

            $now = now();

            DB::table('document_sequences')->insertOrIgnore([
                'document_type' => $documentType,
                'period' => $period,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DB::table('document_sequences')
                ->where('document_type', $documentType)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new RuntimeException(
                    'Unable to initialize document numbering sequence.'
                );
            }

            $nextNumber = $sequence->last_number + 1;

            DB::table('document_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => $now,
                ]);

            $formattedNumber = str_pad(
                (string) $nextNumber,
                6,
                '0',
                STR_PAD_LEFT
            );

            return "{$prefix}/{$period}/{$formattedNumber}";
        });
    }
}
