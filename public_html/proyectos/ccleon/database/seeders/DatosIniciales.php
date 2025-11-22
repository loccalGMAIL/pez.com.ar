<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatosIniciales extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insertar proveedores
        DB::table('proveedores')->insert([
            [
                'nombreProveedor' => 'Gonzalo Ghelfa',
                'razonSocialProveedor' => 'Pontevedra',
                'direccionProveedor' => 'Uruguay',
                'cuitProveedor' => '123456789',
                'estadoProveedor' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombreProveedor' => 'emcesa',
                'razonSocialProveedor' => 'Munich',
                'direccionProveedor' => 'Paraguay',
                'cuitProveedor' => '1122334455',
                'estadoProveedor' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insertar usuarios
        DB::table('users')->insert([
            [
                'name' => 'Raul Tribo',
                'email' => 'raul@gmail.com',
                'password' => Hash::make('Raul12345'),
                'rol' => 'admin',
                'activo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Claudio',
                'email' => 'c@c.com',
                'password' => Hash::make('12345'),
                'rol' => 'usuario',
                'activo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Inicializar los contadores de camiones para cada proveedor
        DB::table('camiones')->insert([
            [
                'contador' => 1,
                'proveedores_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'contador' => 1,
                'proveedores_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}