<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea la usuaria inicial con rol tesorera.
 * Si ya existe no la modifica. La contraseña se genera al azar y se muestra
 * una sola vez por consola: hay que cambiarla después del primer ingreso.
 */
class InitialUserSeeder extends Seeder
{
    public const EMAIL = 'karina.tesorera@devsign.ar';

    public function run(): void
    {
        if (User::withTrashed()->where('email', self::EMAIL)->exists()) {
            $this->command?->info('La usuaria inicial ya existe, no se modifica.');

            return;
        }

        $password = Str::password(16, symbols: false);

        $user = User::create([
            'name' => 'Karina Noble',
            'email' => self::EMAIL,
            'password' => $password,
        ]);

        $user->assignRole('tesorera');

        $this->command?->warn('Usuaria inicial: '.self::EMAIL.' | contraseña: '.$password);
    }
}
