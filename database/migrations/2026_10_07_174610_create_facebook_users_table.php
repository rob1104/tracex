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
        Schema::create('facebook_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('import_log_id')->nullable()->constrained('facebook_import_logs')->nullOnDelete();
            $table->string('age_range', 50)->nullable()->index();
            $table->date('joined_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'joined_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_users');
    }
};
