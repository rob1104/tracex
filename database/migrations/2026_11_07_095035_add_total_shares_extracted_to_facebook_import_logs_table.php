<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facebook_import_logs', function (Blueprint $table): void {
            $table->unsignedInteger('total_shares_extracted')->default(0)->after('total_reactions_extracted');
        });

        DB::table('facebook_import_logs')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(50, function ($importLogs): void {
                foreach ($importLogs as $importLog) {
                    $metadata = json_decode((string) $importLog->metadata, true);
                    if (! is_array($metadata)) {
                        continue;
                    }

                    $shareCount = max(
                        (int) ($metadata['facebook_shares_count'] ?? 0),
                        (int) ($metadata['diagnostics']['raw_extracted']['shares'] ?? 0),
                        is_array($metadata['facebook_shares'] ?? null) ? count($metadata['facebook_shares']) : 0,
                    );

                    DB::table('facebook_import_logs')
                        ->where('id', $importLog->id)
                        ->update(['total_shares_extracted' => $shareCount]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facebook_import_logs', function (Blueprint $table): void {
            $table->dropColumn('total_shares_extracted');
        });
    }
};
