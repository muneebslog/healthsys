<?php

namespace Database\Seeders;

use App\Enums\QueueResetType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdminUser();
        $this->seedDefaultServices();
    }

    private function seedAdminUser(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = env('ADMIN_NAME', 'Administrator');
            $user->password = Hash::make(env('ADMIN_PASSWORD', 'password'));
            $user->role = UserRole::Admin;
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();
        }
    }

    /**
     * Fixed IDs: 1 = Consultation (not standalone), 2 = General Checkup (standalone).
     */
    private function seedDefaultServices(): void
    {
        $now = now();

        DB::table('services')->upsert(
            [
                [
                    'id' => 1,
                    'name' => 'Consultation',
                    'is_standalone' => false,
                    'reset_type' => QueueResetType::Daily->value,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 2,
                    'name' => 'General Checkup',
                    'is_standalone' => true,
                    'reset_type' => QueueResetType::Daily->value,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['id'],
            ['name', 'is_standalone', 'reset_type', 'is_active', 'updated_at']
        );
    }
}
