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
        // 1. Collection / Distribution Centers
        Schema::create('collection_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('level', ['district', 'tehsil', 'village'])->default('district');
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('tehsil_id')->nullable()->constrained('tehsils')->nullOnDelete();
            $table->string('village_name')->nullable();
            $table->string('focal_person_name')->nullable();
            $table->string('focal_person_phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('capacity_units', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['district_id', 'level', 'is_active']);
        });

        // 2. Scheme Stock Items (physical items distributed in schemes)
        Schema::create('scheme_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained('schemes')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('item_code')->nullable();
            $table->string('unit')->default('Bag (50kg)');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scheme_id', 'is_active']);
        });

        // 3. Stock Receipts (Items received from suppliers/depots into a collection center)
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->foreignId('scheme_stock_item_id')->constrained('scheme_stock_items')->cascadeOnDelete();
            $table->foreignId('collection_center_id')->constrained('collection_centers')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->decimal('quantity_received', 12, 2);
            $table->date('received_date');
            $table->string('supplier_source')->nullable();
            $table->string('batch_no')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proof_attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['collection_center_id', 'scheme_stock_item_id']);
        });

        // 4. Center Inventories (Real-time stock balance per center per item)
        Schema::create('center_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_center_id')->constrained('collection_centers')->cascadeOnDelete();
            $table->foreignId('scheme_stock_item_id')->constrained('scheme_stock_items')->cascadeOnDelete();
            $table->decimal('quantity_received', 12, 2)->default(0);
            $table->decimal('quantity_transferred_in', 12, 2)->default(0);
            $table->decimal('quantity_transferred_out', 12, 2)->default(0);
            $table->decimal('quantity_distributed', 12, 2)->default(0);
            $table->decimal('quantity_damaged', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['collection_center_id', 'scheme_stock_item_id'], 'center_inv_center_item_unique');
        });

        // 5. Stock Transfers between Centers (with demand justification)
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->foreignId('scheme_stock_item_id')->constrained('scheme_stock_items')->cascadeOnDelete();
            $table->foreignId('from_center_id')->constrained('collection_centers')->cascadeOnDelete();
            $table->foreignId('to_center_id')->constrained('collection_centers')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->text('demand_justification'); // Mandatory justification based on demand
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->foreignId('transferred_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['from_center_id', 'to_center_id']);
        });

        // 6. Stock Damages / Wastages (with image or report proof)
        Schema::create('stock_damages', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('collection_center_id')->constrained('collection_centers')->cascadeOnDelete();
            $table->foreignId('scheme_stock_item_id')->constrained('scheme_stock_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->enum('damage_type', [
                'spoilage',
                'packaging_tear',
                'water_moisture',
                'pest_infestation',
                'transit_breakage',
                'expiry',
                'other'
            ])->default('packaging_tear');
            $table->text('reason'); // Detailed reason / justification
            $table->string('proof_file_path')->nullable(); // Image or PDF inspection report
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->string('action_taken')->nullable();
            $table->date('incident_date');
            $table->timestamps();

            $table->index(['collection_center_id', 'scheme_stock_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_damages');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('center_inventories');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('scheme_stock_items');
        Schema::dropIfExists('collection_centers');
    }
};
