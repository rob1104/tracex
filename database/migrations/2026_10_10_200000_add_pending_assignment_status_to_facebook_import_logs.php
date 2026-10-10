<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_import_logs', function (Blueprint $table) {
            $table->enum('status', ['downloaded', 'processing', 'pending_assignment', 'completed', 'failed'])
                ->default('downloaded')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('facebook_import_logs')
            ->where('status', 'pending_assignment')
            ->update(['status' => 'failed']);

        Schema::table('facebook_import_logs', function (Blueprint $table) {
            $table->enum('status', ['downloaded', 'processing', 'completed', 'failed'])
                ->default('downloaded')
                ->change();
        });
    }
};
