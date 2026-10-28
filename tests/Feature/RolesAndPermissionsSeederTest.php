<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InitialUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_tesorera_has_every_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            RolesAndPermissionsSeeder::PERMISSIONS,
            Role::findByName('tesorera', 'web')->permissions->pluck('name')->all(),
        );
    }

    public function test_contador_only_has_read_and_report_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['movements.view', 'funds.view', 'entities.view', 'audit.view', 'reports.view', 'reports.export'],
            Role::findByName('contador', 'web')->permissions->pluck('name')->all(),
        );
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(11, Permission::count());
        $this->assertSame(2, Role::count());
        $this->assertSame(1, User::where('email', InitialUserSeeder::EMAIL)->count());
    }

    public function test_initial_user_is_tesorera(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', InitialUserSeeder::EMAIL)->firstOrFail();

        $this->assertSame('Karina Noble', $user->name);
        $this->assertTrue($user->hasRole('tesorera'));
        $this->assertTrue($user->can('movements.void'));
    }
}
