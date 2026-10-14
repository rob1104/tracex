<?php

namespace Database\Seeders;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use App\Services\FacebookProfileIdentifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class AlbertoProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Imports all Facebook accounts and email accounts belonging to Alberto from Cuentas.xlsx,
     * creates/updates the collaborator user profile, and links all profiles to him.
     */
    public function run(): void
    {
        $admin = User::firstWhere('email', 'admin@evidencias.local')
            ?? User::firstWhere('role', 'admin')
            ?? User::factory()->create(['name' => 'Administrador', 'email' => 'admin@evidencias.local', 'role' => 'admin']);

        // System user profiles for Alberto
        $albertoDelRio = User::updateOrCreate(
            ['email' => 'alberto.delrio@evidencias.local'],
            [
                'name' => 'Alberto Del Rio',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => true,
            ]
        );

        $alberto = User::updateOrCreate(
            ['email' => 'alberto@evidencias.local'],
            [
                'name' => 'Alberto',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => true,
            ]
        );

        $jsonPath = __DIR__.'/data/alberto_cuentas.json';
        if (! File::exists($jsonPath)) {
            $this->command?->error("Data file not found at: {$jsonPath}");

            return;
        }

        $accounts = json_decode(File::get($jsonPath), true);
        if (! is_array($accounts)) {
            $this->command?->error('Invalid accounts JSON data.');

            return;
        }

        $emailCount = 0;
        $profileCount = 0;

        foreach ($accounts as $item) {
            $email = mb_strtolower(trim((string) ($item['email'] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                $name = 'Alberto Profile';
            }

            $pwdEmail = trim((string) ($item['pwd_email'] ?? ''));
            $pwdFb = trim((string) ($item['pwd_fb'] ?? ''));
            $password = $pwdEmail !== '' ? $pwdEmail : ($pwdFb !== '' ? $pwdFb : 'secret123');
            $fbPassword = $pwdFb !== '' ? $pwdFb : ($pwdEmail !== '' ? $pwdEmail : 'fbpassword123');

            $link = trim((string) ($item['link'] ?? ''));
            $fbIdentifier = $link !== '' ? FacebookProfileIdentifier::normalize($link) : null;

            // 1. EmailAccount
            $emailAccount = EmailAccount::updateOrCreate(
                ['email' => $email],
                [
                    'alias' => "Correo {$name}",
                    'password' => $password,
                    'status' => 'active',
                    'created_by' => $admin->id,
                ]
            );
            $emailCount++;

            // 2. Profile
            $profile = Profile::updateOrCreate(
                [
                    'email_account_id' => $emailAccount->id,
                    'social_network' => 'facebook',
                ],
                [
                    'name' => $name,
                    'password' => $fbPassword,
                    'facebook_identifier' => $fbIdentifier,
                    'status' => 'active',
                    'created_by' => $admin->id,
                ]
            );
            $profileCount++;

            // 3. Link collaborator to profile
            $profile->users()->syncWithoutDetaching([$albertoDelRio->id, $alberto->id]);
        }

        $this->command?->info("Seeded {$emailCount} email accounts and {$profileCount} Facebook profiles for Alberto.");
    }
}
