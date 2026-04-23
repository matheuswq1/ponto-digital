<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage-companies',
            'manage-employees',
            'view-employees',
            'approve-edit-requests',
            'view-reports',
            'register-time-record',
            'view-own-records',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $admin->syncPermissions($permissions);

        $gestor = Role::firstOrCreate(['name' => 'gestor', 'guard_name' => 'sanctum']);
        $gestor->syncPermissions([
            'manage-employees',
            'view-employees',
            'approve-edit-requests',
            'view-reports',
            'register-time-record',
            'view-own-records',
        ]);

        $funcionario = Role::firstOrCreate(['name' => 'funcionario', 'guard_name' => 'sanctum']);
        $funcionario->syncPermissions([
            'register-time-record',
            'view-own-records',
        ]);
    }
}
