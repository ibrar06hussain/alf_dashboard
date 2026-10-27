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
        Schema::create('stock_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursement_no')->unique();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('collection_center_id')->constrained('collection_centers')->cascadeOnDelete();
            $table->foreignId('scheme_stock_item_id')->constrained('scheme_stock_items')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->decimal('quantity_disbursed', 12, 2);
            $table->string('recipient_name');
            $table->string('recipient_cnic');
            $table->string('recipient_phone')->nullable();
            $table->boolean('is_original_applicant')->default(true);
            $table->string('representative_relationship')->nullable();
            $table->string('challan_no')->nullable();
            $table->boolean('challan_verified')->default(true);
            $table->date('disbursement_date');
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proof_attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['district_id', 'collection_center_id']);
            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_disbursements');
    }
};
