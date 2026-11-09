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
        Schema::create('evidence_text_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_file_name');
            $table->string('source_path')->nullable();
            $table->string('parsed_path')->nullable();
            $table->string('status', 32)->default('queued_preview')->index();
            $table->unsignedBigInteger('total_entries')->default(0);
            $table->unsignedBigInteger('recognized_count')->default(0);
            $table->unsignedBigInteger('unrecognized_count')->default(0);
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('imported_count')->default(0);
            $table->unsignedBigInteger('duplicate_count')->default(0);
            $table->json('preview_records')->nullable();
            $table->json('preview_unparsed')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_text_imports');
    }
};
