<?php

namespace Database\Seeders;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GoogleDriveProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates users, email accounts, and monitored social profiles
     * extracted directly from the official Meta export folders in Google Drive.
     */
    public function run(): void
    {
        $admin = User::firstWhere('email', 'admin@evidencias.local')
            ?? User::firstWhere('role', 'admin')
            ?? User::factory()->create(['role' => 'admin']);

        $driveProfiles = [
            [
                'name' => 'Alberto Del Rio',
                'email' => 'ju89611@outlook.com',
                'login_email' => 'alberto.delrio@evidencias.local',
                'social_network' => 'Facebook',
                'profile_uri' => 'https://www.facebook.com/profile.php?id=61594588617262',
                'fb_id' => '61594588617262',
                'folder' => 'meta-2026-Oct-10-11-29-43',
            ],
            [
                'name' => 'Jiovanny Cedillo',
                'email' => 'df344332@outlook.com',
                'login_email' => 'jiovanny.cedillo@evidencias.local',
                'social_network' => 'Facebook',
                'profile_uri' => 'https://www.facebook.com/profile.php?id=61594054181765',
                'fb_id' => '61594054181765',
                'folder' => 'meta-2026-Oct-10-11-22-41',
            ],
            [
                'name' => 'Juana Rodriguez',
                'email' => 'juana.rogj69@gmail.com',
                'login_email' => 'juana.rodriguez@evidencias.local',
                'social_network' => 'Facebook',
                'profile_uri' => 'https://www.facebook.com/profile.php?id=61593018723655',
                'fb_id' => '61593018723655',
                'folder' => 'meta-2026-Oct-06-11-09-05',
            ],
            [
                'name' => 'Sergio Perez',
                'email' => 'sergio.perez@outlook.com',
                'login_email' => 'sergio.perez@evidencias.local',
                'social_network' => 'Facebook',
                'profile_uri' => 'https://www.facebook.com/profile.php?id=61592991939900',
                'fb_id' => '61592991939900',
                'folder' => 'meta-2026-Oct-09-17-31-01',
            ],
        ];

        foreach ($driveProfiles as $item) {
            // 1. Create or update collaborator user with 'user' role
            $user = User::updateOrCreate(
                ['email' => $item['login_email']],
                [
                    'name' => $item['name'],
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'is_active' => true,
                ]
            );

            // 2. Create or update the EmailAccount extracted from Meta profile
            $emailAccount = EmailAccount::updateOrCreate(
                ['email' => $item['email']],
                [
                    'alias' => "Correo {$item['name']}",
                    'password' => 'secret123',
                    'status' => 'active',
                    'created_by' => $admin->id,
                ]
            );

            // 3. Create or update the monitored Profile matching Facebook name
            $profile = Profile::updateOrCreate(
                [
                    'name' => $item['name'],
                    'social_network' => $item['social_network'],
                ],
                [
                    'email_account_id' => $emailAccount->id,
                    'facebook_identifier' => 'id:'.$item['fb_id'],
                    'password' => 'fbpassword123',
                    'status' => 'active',
                    'created_by' => $admin->id,
                ]
            );

            // 4. Assign profile exclusively to its respective collaborator user
            $profile->users()->sync([$user->id]);
        }
    }
}
