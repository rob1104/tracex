<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\EvidenceType;
use App\Models\Evidence;
use App\Models\EvidenceImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StressTestSeeder extends Seeder
{
    public function run()
    {
        $this->command->info("Creando 10 usuarios...");
        $users = User::factory()->count(10)->create();
        $userIds = $users->pluck('id')->toArray();
        $evidenceTypes = EvidenceType::pluck('id')->toArray();
        $socialNetworks = ['Facebook', 'Instagram', 'X (Twitter)', 'TikTok', 'LinkedIn', 'YouTube'];
        
        $evidencesCount = 10000;
        $chunkSize = 1000;
        
        $this->command->info("Insertando {$evidencesCount} evidencias en bloques de {$chunkSize}...");

        for ($i = 0; $i < $evidencesCount; $i += $chunkSize) {
            $evidences = [];
            
            for ($j = 0; $j < $chunkSize; $j++) {
                $evidences[] = [
                    'user_id' => $userIds[array_rand($userIds)],
                    'evidence_type_id' => !empty($evidenceTypes) ? $evidenceTypes[array_rand($evidenceTypes)] : null,
                    'social_network' => $socialNetworks[array_rand($socialNetworks)],
                    'comment' => 'Evidencia generada para pruebas de estrés. ID Aleatorio: ' . Str::random(8),
                    'created_at' => Carbon::now()->subDays(rand(1, 365))->subHours(rand(1, 24))->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ];
            }
            
            Evidence::insert($evidences);
            $this->command->info("Progreso: " . ($i + $chunkSize) . " evidencias...");
        }

        $this->command->info('Generando capturas de pantalla simuladas (1 por cada evidencia)...');
        
        // Chunking through all evidences to add 1 fake image to each
        Evidence::chunk(1000, function ($evidencesChunk) {
            $images = [];
            $now = Carbon::now()->toDateTimeString();
            
            foreach ($evidencesChunk as $evidence) {
                $images[] = [
                    'evidence_id' => $evidence->id,
                    'screenshot_path' => 'screenshots/fake-stress-image.jpg',
                    'screenshot_hash' => Str::random(32),
                    'screenshot_mime' => 'image/jpeg',
                    'screenshot_size' => 1024,
                    'is_suspect' => rand(1, 100) > 95, // 5% chance of being suspect
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            EvidenceImage::insert($images);
        });

        $this->command->info('¡Seeder de prueba de estrés finalizado con éxito!');
    }
}
