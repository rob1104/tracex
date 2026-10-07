<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@evidencias.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'cuentas@evidencias.local'],
            [
                'name' => 'Gestor de Cuentas',
                'password' => Hash::make('password'),
                'role' => 'cuentas',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@evidencias.local'],
            [
                'name' => 'Usuario Ejemplo',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => true,
            ]
        );
    }
}
