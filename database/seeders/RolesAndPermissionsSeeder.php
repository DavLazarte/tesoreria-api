<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos y los roles iniciales (tesorera y contador).
 * Es idempotente: se puede correr varias veces sin duplicar datos.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'movements.view',
        'movements.create',
        'movements.void',
        'funds.view',
        'funds.manage',
        'entities.view',
        'entities.manage',
        'reports.view',
        'reports.export',
        'users.manage',
        'audit.view',
    ];

    /**
     * @var list<string>
     */
    public const CONTADOR_PERMISSIONS = [
        'movements.view',
        'funds.view',
        'entities.view',
        'audit.view',
        'reports.view',
        'reports.export',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Con WithoutModelEvents spatie no limpia su caché solo al crear permisos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('tesorera', 'web')->syncPermissions(self::PERMISSIONS);
        Role::findOrCreate('contador', 'web')->syncPermissions(self::CONTADOR_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
