<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE profiles MODIFY COLUMN status ENUM('active', 'suspended', 'restricted') DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE profiles MODIFY COLUMN status ENUM('active', 'suspended') DEFAULT 'active'");
        }
    }
};
