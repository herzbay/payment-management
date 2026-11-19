<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AuditTrailService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select([
                'audit_logs.id',
                'audit_logs.user_id',
                'users.name as user_name',
                'users.email as user_email',
                'audit_logs.event',
                'audit_logs.module',
                'audit_logs.description',
                'audit_logs.auditable_type',
                'audit_logs.auditable_id',
                'audit_logs.old_values',
                'audit_logs.new_values',
                'audit_logs.ip_address',
                'audit_logs.user_agent',
                'audit_logs.url',
                'audit_logs.http_method',
                'audit_logs.properties',
                'audit_logs.created_at',
            ]);

        $query
            ->when($filters['user_id'] ?? null, function ($q, $userId) {
                $q->where('audit_logs.user_id', $userId);
            })
            ->when($filters['module'] ?? null, function ($q, $module) {
                $q->where('audit_logs.module', $module);
            })
            ->when($filters['event'] ?? null, function ($q, $event) {
                $q->where('audit_logs.event', $event);
            })
            ->when($filters['auditable_type'] ?? null, function ($q, $type) {
                $q->where('audit_logs.auditable_type', $type);
            })
            ->when($filters['auditable_id'] ?? null, function ($q, $id) {
                $q->where('audit_logs.auditable_id', $id);
            })
            ->when($filters['from'] ?? null, function ($q, $from) {
                $q->whereDate('audit_logs.created_at', '>=', $from);
            })
            ->when($filters['to'] ?? null, function ($q, $to) {
                $q->whereDate('audit_logs.created_at', '<=', $to);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('audit_logs.description', 'ilike', "%{$search}%")
                        ->orWhere('audit_logs.module', 'ilike', "%{$search}%")
                        ->orWhere('audit_logs.event', 'ilike', "%{$search}%")
                        ->orWhere('users.name', 'ilike', "%{$search}%")
                        ->orWhere('users.email', 'ilike', "%{$search}%");
                });
            });

        return $query
            ->orderByDesc('audit_logs.created_at')
            ->orderByDesc('audit_logs.id')
            ->paginate($this->perPage($filters));
    }

    public function getById(int $id): object
    {
        $log = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select([
                'audit_logs.*',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->where('audit_logs.id', $id)
            ->first();

        abort_if(
            $log === null,
            404,
            'Riwayat aktivitas tidak ditemukan.'
        );

        return $log;
    }

    private function perPage(array $filters): int
    {
        return min(max((int) ($filters['per_page'] ?? 15), 1), 100);
    }
}
