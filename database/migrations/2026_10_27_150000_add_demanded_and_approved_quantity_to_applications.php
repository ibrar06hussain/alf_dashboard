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
        Schema::table('applications', function (Blueprint $table) {
            $table->decimal('demanded_quantity', 10, 2)->nullable()->after('reactivation_reason');
            $table->decimal('approved_quantity', 10, 2)->nullable()->after('demanded_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['demanded_quantity', 'approved_quantity']);
        });
    }
};
