<?php

namespace Database\Seeders;

use App\Models\EvidenceType;
use Illuminate\Database\Seeder;

class EvidenceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Like / Reacción'],
            ['name' => 'Comentario'],
            ['name' => 'Creación de perfil'],
            ['name' => 'Otro'],
        ];

        foreach ($types as $type) {
            EvidenceType::create($type);
        }
    }
}
