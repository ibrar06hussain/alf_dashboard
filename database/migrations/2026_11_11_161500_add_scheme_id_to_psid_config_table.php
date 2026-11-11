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
        if (Schema::hasTable('psid_config') && ! Schema::hasColumn('psid_config', 'scheme_id')) {
            Schema::table('psid_config', function (Blueprint $table) {
                $table->foreignId('scheme_id')
                    ->nullable()
                    ->unique()
                    ->after('config_id')
                    ->constrained('schemes')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('psid_config') && Schema::hasColumn('psid_config', 'scheme_id')) {
            Schema::table('psid_config', function (Blueprint $table) {
                $table->dropForeign(['scheme_id']);
                $table->dropUnique(['scheme_id']);
                $table->dropColumn('scheme_id');
            });
        }
    }
};

