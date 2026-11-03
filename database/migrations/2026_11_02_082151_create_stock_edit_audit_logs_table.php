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
        Schema::create('stock_edit_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('stock_type'); // 'receipt' (Stock In) or 'disbursement' (Stock Out)
            $table->unsignedBigInteger('stock_id');
            $table->string('reference_no'); // receipt_no or disbursement_no
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_role')->nullable();
            $table->json('old_values');
            $table->json('new_values');
            $table->json('changed_fields');
            $table->text('justification'); // Mandatory justification for the modification
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['stock_type', 'stock_id']);
            $table->index('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_edit_audit_logs');
    }
};
