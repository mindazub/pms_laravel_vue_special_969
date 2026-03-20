<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.members.manage',
            'customers.view',
            'customers.create',
            'customers.update',
            'projects.create',
            'projects.update',
            'notes.create',
            'notes.update',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::query()->firstOrCreate(['name' => User::ROLE_ADMIN, 'guard_name' => 'web']);
        $ceo = Role::query()->firstOrCreate(['name' => User::ROLE_CEO, 'guard_name' => 'web']);
        $manager = Role::query()->firstOrCreate(['name' => User::ROLE_MANAGER, 'guard_name' => 'web']);
        $hr = Role::query()->firstOrCreate(['name' => User::ROLE_HR, 'guard_name' => 'web']);
        $user = Role::query()->firstOrCreate(['name' => User::ROLE_USER, 'guard_name' => 'web']);

        $admin->syncPermissions($permissions);
        $ceo->syncPermissions($permissions);
        $manager->syncPermissions([
            'teams.view',
            'teams.members.manage',
            'customers.view',
            'projects.create',
            'projects.update',
            'notes.create',
            'notes.update',
        ]);
        $hr->syncPermissions([
            'teams.view',
            'customers.view',
        ]);
        $user->syncPermissions([
            'projects.create',
            'notes.create',
            'notes.update',
        ]);

        $this->seedDefaultUsers();
    }

    private function seedDefaultUsers(): void
    {
        $accounts = [
            [
                'name' => 'Admin User',
                'email' => 'admin@admin.com',
                'password' => 'admin000',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'CEO User',
                'email' => 'ceo@ceo.com',
                'password' => 'ceo000',
                'role' => User::ROLE_CEO,
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@manager.com',
                'password' => 'manager000',
                'role' => User::ROLE_MANAGER,
            ],
            [
                'name' => 'HR User',
                'email' => 'hr@hr.com',
                'password' => 'hr000',
                'role' => User::ROLE_HR,
            ],
            [
                'name' => 'Standard User',
                'email' => 'user@user.com',
                'password' => 'user000',
                'role' => User::ROLE_USER,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                ]
            );

            $user->syncRoles([$account['role']]);
        }
    }
}
