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
        Schema::create('facebook_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('facebook_user_id')->constrained('facebook_users')->cascadeOnDelete();
            $table->foreignId('import_log_id')->nullable()->constrained('facebook_import_logs')->nullOnDelete();
            $table->string('reaction_type', 50)->index();
            $table->dateTime('published_at')->index();
            $table->string('title')->nullable();
            $table->text('target_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['facebook_user_id', 'published_at']);
            $table->index(['reaction_type', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_reactions');
    }
};
