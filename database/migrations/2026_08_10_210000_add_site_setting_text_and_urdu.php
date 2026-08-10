<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE site_settings MODIFY value TEXT NULL');
            DB::statement('ALTER TABLE site_settings MODIFY fallback TEXT NULL');
        }

        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'value_ur')) {
                $table->text('value_ur')->nullable()->after('value');
            }
            if (! Schema::hasColumn('site_settings', 'fallback_ur')) {
                $table->text('fallback_ur')->nullable()->after('fallback');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('site_settings', 'value_ur')) {
                $table->dropColumn('value_ur');
            }
            if (Schema::hasColumn('site_settings', 'fallback_ur')) {
                $table->dropColumn('fallback_ur');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE site_settings MODIFY value VARCHAR(255) NULL');
            DB::statement('ALTER TABLE site_settings MODIFY fallback VARCHAR(255) NULL');
        }
    }
};
