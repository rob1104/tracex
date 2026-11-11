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
        Schema::table('evidence_text_imports', function (Blueprint $table) {
            $table->longText('duplicate_preview')->nullable();
            $table->longText('duplicate_decisions')->nullable();
            $table->unsignedBigInteger('replaced_count')->default(0);
        });

        Schema::table('evidence', function (Blueprint $table) {
            $table->foreignId('evidence_text_import_id')
                ->nullable()
                ->after('facebook_import_log_id')
                ->constrained('evidence_text_imports')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evidence', function (Blueprint $table) {
            $table->dropForeign(['evidence_text_import_id']);
            $table->dropColumn('evidence_text_import_id');
        });

        Schema::table('evidence_text_imports', function (Blueprint $table) {
            $table->dropColumn(['duplicate_preview', 'duplicate_decisions', 'replaced_count']);
        });
    }
};
