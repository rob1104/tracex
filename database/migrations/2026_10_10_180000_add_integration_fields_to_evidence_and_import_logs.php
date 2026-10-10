<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facebook_import_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('profile_id')->nullable()->after('user_id')->constrained('profiles')->nullOnDelete();
        });

        Schema::table('evidence', function (Blueprint $table) {
            $table->foreignId('facebook_import_log_id')->nullable()->after('profile_id')->constrained('facebook_import_logs')->nullOnDelete();
            $table->string('origin', 20)->default('manual')->after('facebook_import_log_id');
            $table->timestamp('external_published_at')->nullable()->after('origin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropForeign(['facebook_import_log_id']);
            $table->dropColumn(['facebook_import_log_id', 'origin', 'external_published_at']);
        });

        Schema::table('facebook_import_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['profile_id']);
            $table->dropColumn(['user_id', 'profile_id']);
        });
    }
};
