<?php

namespace App\Services;

use App\Models\BankAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankAccountService
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    /**
     * Mengambil daftar rekening bank.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return BankAccount::query()
            ->when($filters['search'] ?? null, function (
                Builder $query,
                string $search
            ) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery
                        ->where('bank_name', 'ilike', "%{$search}%")
                        ->orWhere('account_name', 'ilike', "%{$search}%")
                        ->orWhere('account_number', 'ilike', "%{$search}%")
                        ->orWhere('account_code', 'ilike', "%{$search}%");
                });
            })
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $query) => $query->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->when(
                array_key_exists('is_default', $filters),
                fn (Builder $query) => $query->where(
                    'is_default',
                    filter_var($filters['is_default'], FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->when(
                $filters['currency'] ?? null,
                fn (Builder $query, string $currency) =>
                    $query->where('currency', strtoupper($currency))
            )
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Mengambil detail rekening bank.
     */
    public function getById(int $id): BankAccount
    {
        return BankAccount::query()
            ->withCount('statements')
            ->findOrFail($id);
    }

    /**
     * Membuat rekening bank baru.
     */
    public function create(array $data): BankAccount
    {
        return DB::transaction(function () use ($data) {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault && ! ($data['is_active'] ?? true)) {
                throw ValidationException::withMessages([
                    'is_default' => [
                        'Rekening nonaktif tidak dapat dijadikan rekening default.',
                    ],
                ]);
            }

            if ($isDefault) {
                $this->clearExistingDefault();
            }

            $account = BankAccount::create([
                'bank_name' => $data['bank_name'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'branch_name' => $data['branch_name'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'IDR'),
                'account_code' => $data['account_code'],
                'is_default' => $isDefault,
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogService->created(
                module: 'bank_reconciliation',
                auditable: $account,
                description: "Rekening bank {$account->account_code} dibuat.",
                properties: [
                    'bank_name' => $account->bank_name,
                    'account_number' => $account->account_number,
                    'currency' => $account->currency,
                    'is_default' => $account->is_default,
                ],
            );

            return $account->fresh();
        });
    }

    /**
     * Memperbarui informasi rekening bank.
     */
    public function update(int $id, array $data): BankAccount
    {
        return DB::transaction(function () use ($id, $data) {
            $account = BankAccount::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $oldValues = $account->only([
                'bank_name',
                'account_name',
                'account_number',
                'branch_name',
                'currency',
                'account_code',
                'is_default',
                'is_active',
                'notes',
            ]);

            $newIsActive = $data['is_active'] ?? $account->is_active;
            $newIsDefault = $data['is_default'] ?? $account->is_default;

            if ($newIsDefault && ! $newIsActive) {
                throw ValidationException::withMessages([
                    'is_default' => [
                        'Rekening nonaktif tidak dapat dijadikan rekening default.',
                    ],
                ]);
            }

            if ($newIsDefault) {
                $this->clearExistingDefault($account->id);
            }

            $account->update([
                'bank_name' => $data['bank_name'] ?? $account->bank_name,
                'account_name' => $data['account_name'] ?? $account->account_name,
                'account_number' => $data['account_number'] ?? $account->account_number,
                'branch_name' => $data['branch_name'] ?? $account->branch_name,
                'currency' => strtoupper(
                    $data['currency'] ?? $account->currency
                ),
                'account_code' => $data['account_code'] ?? $account->account_code,
                'is_default' => $newIsDefault,
                'is_active' => $newIsActive,
                'notes' => $data['notes'] ?? $account->notes,
            ]);

            if (! $account->is_active && $account->is_default) {
                $account->update(['is_default' => false]);
            }

            $this->auditLogService->updated(
                module: 'bank_reconciliation',
                auditable: $account,
                description: "Rekening bank {$account->account_code} diperbarui.",
                oldValues: $oldValues,
                newValues: $account->fresh()->only(array_keys($oldValues)),
            );

            return $account->fresh();
        });
    }

    /**
     * Mengaktifkan atau menonaktifkan rekening bank.
     */
    public function updateStatus(int $id, bool $isActive): BankAccount
    {
        return DB::transaction(function () use ($id, $isActive) {
            $account = BankAccount::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $oldValues = [
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
            ];

            if (! $isActive && $account->is_default) {
                $account->is_default = false;
            }

            $account->is_active = $isActive;
            $account->save();

            $this->auditLogService->updated(
                module: 'bank_reconciliation',
                auditable: $account,
                description: $isActive
                    ? "Rekening bank {$account->account_code} diaktifkan."
                    : "Rekening bank {$account->account_code} dinonaktifkan.",
                oldValues: $oldValues,
                newValues: [
                    'is_active' => $account->is_active,
                    'is_default' => $account->is_default,
                ],
            );

            return $account->fresh();
        });
    }

    /**
     * Menetapkan rekening sebagai rekening default.
     */
    public function setDefault(int $id): BankAccount
    {
        return DB::transaction(function () use ($id) {
            // Mengunci daftar rekening untuk mencegah dua rekening
            // ditetapkan sebagai default secara bersamaan.
            $accounts = BankAccount::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $account = $accounts->firstWhere('id', $id);

            if (! $account) {
                throw ValidationException::withMessages([
                    'bank_account' => ['Rekening bank tidak ditemukan.'],
                ]);
            }

            if (! $account->is_active) {
                throw ValidationException::withMessages([
                    'bank_account' => [
                        'Rekening nonaktif tidak dapat dijadikan rekening default.',
                    ],
                ]);
            }

            foreach ($accounts as $existingAccount) {
                $shouldBeDefault = (int) $existingAccount->id === $id;

                if ((bool) $existingAccount->is_default !== $shouldBeDefault) {
                    $existingAccount->update([
                        'is_default' => $shouldBeDefault,
                    ]);
                }
            }

            $this->auditLogService->updated(
                module: 'bank_reconciliation',
                auditable: $account,
                description: "Rekening bank {$account->account_code} ditetapkan sebagai rekening default.",
                oldValues: ['is_default' => false],
                newValues: ['is_default' => true],
            );

            return $account->fresh();
        });
    }

    /**
     * Menghapus penanda default dari rekening lainnya.
     */
    private function clearExistingDefault(?int $exceptId = null): void
    {
        $query = BankAccount::query()
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $accounts = $query
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($accounts as $account) {
            $account->update(['is_default' => false]);
        }
    }
}
