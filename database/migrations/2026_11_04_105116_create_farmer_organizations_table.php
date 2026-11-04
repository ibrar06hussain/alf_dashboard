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
        Schema::create('farmer_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('sheet_code', 20)->nullable()->index();
            $table->string('division_name', 100)->nullable();
            $table->string('district_name', 100)->index();
            $table->string('sub_division', 150)->nullable();
            $table->string('uc_mc', 150)->nullable();
            $table->string('village_name', 200);
            $table->string('organization_name', 255)->nullable();
            $table->boolean('is_vo_formed')->default(true)->index();
            $table->string('focal_person_name', 150)->nullable();
            $table->string('focal_person_phone', 100)->nullable();
            $table->string('focal_person_raw', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['district_name', 'is_vo_formed']);
            $table->index('village_name');
            $table->index('organization_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmer_organizations');
    }
};
