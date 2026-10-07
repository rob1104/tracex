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
        Schema::create('facebook_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('facebook_user_id')->constrained('facebook_user')->cascadeOnDelete();
            $table->foreignId('import_log_id')->nullable()->constrained('facebook_import_logs')->nullOnDelete();
            $table->text('content');
            $table->unsignedInteger('character_count');
            $table->unsignedInteger('word_count');
            $table->dateTime('published_at')->index();
            $table->text('post_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['facebook_user_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_comments');
    }
};
