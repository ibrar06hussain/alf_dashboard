<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_surveys', function (Blueprint $table) {
            $table->json('gps_points')->nullable()->after('gps_coordinates');
            $table->json('photos')->nullable()->after('gps_points');
        });
    }

    public function down(): void
    {
        Schema::table('application_surveys', function (Blueprint $table) {
            $table->dropColumn(['gps_points', 'photos']);
        });
    }
};
