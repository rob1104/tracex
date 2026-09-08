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
        Schema::create('evidence_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidence')->onDelete('cascade');
            $table->string('screenshot_path');
            $table->string('screenshot_hash')->index();
            $table->string('screenshot_mime');
            $table->unsignedBigInteger('screenshot_size');
            $table->boolean('is_suspect')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_images');
    }
};
