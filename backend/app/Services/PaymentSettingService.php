<?php

namespace App\Services;

use App\Models\PaymentSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSettingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentSetting::query();

        $query
            ->when($filters['setting_group'] ?? null, function ($q, $group) {
                $q->where('setting_group', $group);
            })
            ->when(array_key_exists('is_active', $filters), function ($q) use ($filters) {
                $q->where('is_active', $filters['is_active']);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('setting_key', 'ilike', "%{$search}%")
                        ->orWhere('label', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('setting_group', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('setting_group')
            ->orderBy('setting_key');

        return $query->paginate($this->perPage($filters));
    }

    public function getByKey(string $key): PaymentSetting
    {
        return PaymentSetting::query()
            ->where('setting_key', $key)
            ->firstOrFail();
    }

    public function updateValue(string $key, mixed $value): PaymentSetting
    {
        return DB::transaction(function () use ($key, $value) {
            $setting = PaymentSetting::query()
                ->where('setting_key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedValue = $this->normalizeValue(
                $value,
                $setting->value_type
            );

            $oldValue = [
                'setting_value' => $setting->setting_value,
            ];

            $setting->setting_value = $normalizedValue;
            $setting->updated_by = Auth::id();
            $setting->save();

            $this->auditLogService->log(
                event: 'updated',
                module: 'payment_settings',
                description: "Pengaturan pembayaran {$setting->setting_key} diperbarui.",
                auditable: $setting,
                oldValues: $oldValue,
                newValues: [
                    'setting_value' => $setting->fresh()->setting_value,
                ],
                properties: [
                    'setting_key' => $setting->setting_key,
                    'setting_group' => $setting->setting_group,
                    'value_type' => $setting->value_type,
                ],
            );

            return $setting->fresh();
        });
    }

    public function updateStatus(string $key, bool $isActive): PaymentSetting
    {
        return DB::transaction(function () use ($key, $isActive) {
            $setting = PaymentSetting::query()
                ->where('setting_key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            if ((bool) $setting->is_active === $isActive) {
                return $setting;
            }

            $oldValues = [
                'is_active' => (bool) $setting->is_active,
            ];

            $setting->is_active = $isActive;
            $setting->updated_by = Auth::id();
            $setting->save();

            $this->auditLogService->log(
                event: 'status_updated',
                module: 'payment_settings',
                description: "Status pengaturan {$setting->setting_key} diubah menjadi "
                    . ($isActive ? 'aktif' : 'nonaktif') . '.',
                auditable: $setting,
                oldValues: $oldValues,
                newValues: [
                    'is_active' => (bool) $setting->is_active,
                ],
                properties: [
                    'setting_key' => $setting->setting_key,
                    'setting_group' => $setting->setting_group,
                ],
            );

            return $setting->fresh();
        });
    }

    private function normalizeValue(mixed $value, string $valueType): mixed
    {
        return match (strtolower($valueType)) {
            'string' => $this->normalizeString($value),
            'integer', 'int' => $this->normalizeInteger($value),
            'number', 'numeric', 'float', 'decimal' => $this->normalizeNumber($value),
            'boolean', 'bool' => $this->normalizeBoolean($value),
            'array', 'json', 'object' => $this->normalizeJsonValue($value),
            default => throw ValidationException::withMessages([
                'setting_value' => "Tipe nilai pengaturan '{$valueType}' tidak didukung.",
            ]),
        };
    }

    private function normalizeString(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            throw ValidationException::withMessages([
                'setting_value' => 'Nilai pengaturan harus berupa teks.',
            ]);
        }

        return trim((string) $value);
    }

    private function normalizeInteger(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages([
                'setting_value' => 'Nilai pengaturan harus berupa bilangan bulat.',
            ]);
        }

        return (int) $value;
    }

    private function normalizeNumber(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw ValidationException::withMessages([
                'setting_value' => 'Nilai pengaturan harus berupa angka.',
            ]);
        }

        return (float) $value;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'setting_value' => 'Nilai pengaturan harus berupa true atau false.',
            ]);
        }

        return $normalized;
    }

    private function normalizeJsonValue(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw ValidationException::withMessages([
                    'setting_value' => 'Nilai pengaturan harus berupa JSON array atau object yang valid.',
                ]);
            }

            return $decoded;
        }

        if (!is_array($value)) {
            throw ValidationException::withMessages([
                'setting_value' => 'Nilai pengaturan harus berupa array atau object.',
            ]);
        }

        return $value;
    }

    private function perPage(array $filters): int
    {
        return min(max((int) ($filters['per_page'] ?? 15), 1), 100);
    }
}
