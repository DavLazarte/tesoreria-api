<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitialCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('entity_types')->count() === 0) {
            DB::table('entity_types')->insert([
                ['name' => 'Persona física', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Iglesia', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Comedor', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Donante', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Proveedor', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Otro', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (DB::table('funds')->where('kind', 'general')->count() === 0) {
            DB::table('funds')->insert([
                'name' => 'Caja general',
                'kind' => 'general',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('categories')->count() === 0) {
            DB::table('categories')->insert([
                ['name' => 'Donaciones', 'flow' => 'ingreso', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Depósitos', 'flow' => 'ingreso', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Otros ingresos', 'flow' => 'ingreso', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Ayudas y subsidios', 'flow' => 'egreso', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Servicios', 'flow' => 'egreso', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Gastos generales', 'flow' => 'egreso', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Otros egresos', 'flow' => 'egreso', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
