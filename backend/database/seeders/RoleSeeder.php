<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full access to all application modules and system settings.',
            ],
            [
                'name' => 'Finance Manager',
                'slug' => 'finance-manager',
                'description' => 'Manages financial operations, approvals, and financial reports.',
            ],
            [
                'name' => 'Finance Staff',
                'slug' => 'finance-staff',
                'description' => 'Handles invoices, payments, receipts, refunds, and accounts receivable.',
            ],
            [
                'name' => 'Sales Staff',
                'slug' => 'sales-staff',
                'description' => 'Manages customers, quotations, and agreements.',
            ],
            [
                'name' => 'Auditor',
                'slug' => 'auditor',
                'description' => 'Reviews financial records, transactions, and audit logs.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}