<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('form_fields', 'option_configs_json')) {
                $table->json('option_configs_json')->nullable()->after('options_json');
            }
            if (! Schema::hasColumn('form_fields', 'is_billable_items_field')) {
                $table->boolean('is_billable_items_field')->default(false)->after('option_configs_json');
            }
        });

        Schema::table('schemes', function (Blueprint $table) {
            if (! Schema::hasColumn('schemes', 'has_auto_dashboard')) {
                $table->boolean('has_auto_dashboard')->default(true)->after('challan_template_id');
            }
        });

        Schema::create('application_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('form_field_id')->nullable()->constrained('form_fields')->nullOnDelete();
            $table->string('item_key', 120);
            $table->string('item_label', 255);
            $table->string('item_label_ur', 255)->nullable();
            $table->string('unit_label', 60)->nullable();
            $table->boolean('is_free')->default(false);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('govt_share_pct', 5, 2)->default(0);
            $table->decimal('farmer_share_pct', 5, 2)->default(100);
            $table->decimal('demanded_qty', 12, 2)->default(0);
            $table->decimal('approved_qty', 12, 2)->nullable();
            $table->decimal('disbursed_qty', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('govt_share_amount', 12, 2)->default(0);
            $table->decimal('farmer_payable_amount', 12, 2)->default(0);
            $table->foreignId('scheme_stock_item_id')->nullable()->constrained('scheme_stock_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['application_id', 'is_free'], 'idx_app_items_app_free');
            $table->index('item_key', 'idx_app_items_key');
            $table->index(['application_id', 'item_key'], 'idx_app_items_app_key');
        });

        Schema::table('stock_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_disbursements', 'batch_voucher_no')) {
                $table->string('batch_voucher_no', 60)->nullable()->after('disbursement_no')->index();
            }
            if (! Schema::hasColumn('stock_disbursements', 'application_item_id')) {
                $table->foreignId('application_item_id')->nullable()->after('application_id')->constrained('application_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_disbursements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_disbursements', 'application_item_id')) {
                $table->dropForeign(['application_item_id']);
                $table->dropColumn('application_item_id');
            }
            if (Schema::hasColumn('stock_disbursements', 'batch_voucher_no')) {
                $table->dropIndex(['batch_voucher_no']);
                $table->dropColumn('batch_voucher_no');
            }
        });

        Schema::dropIfExists('application_items');

        Schema::table('schemes', function (Blueprint $table) {
            if (Schema::hasColumn('schemes', 'has_auto_dashboard')) {
                $table->dropColumn('has_auto_dashboard');
            }
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $drops = array_filter(['option_configs_json', 'is_billable_items_field'], fn ($col) => Schema::hasColumn('form_fields', $col));
            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
