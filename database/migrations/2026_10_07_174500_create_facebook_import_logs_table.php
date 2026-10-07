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
        Schema::create('facebook_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('drive_file_id')->unique();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->enum('status', ['downloaded', 'processing', 'completed', 'failed'])->default('downloaded')->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('drive_deleted_at')->nullable()->index(); // Prueba de destrucción en nube (Regla Cero-Retención)
            $table->unsignedInteger('total_comments_extracted')->default(0);
            $table->unsignedInteger('total_reactions_extracted')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_import_logs');
    }
};
