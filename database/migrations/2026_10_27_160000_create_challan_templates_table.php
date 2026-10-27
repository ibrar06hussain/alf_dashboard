<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('challan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department_name')->default('Agriculture LiveStock and Fishries Department Gilgit Baltistan');
            $table->string('sub_title')->nullable()->default('Government of Gilgit-Baltistan');
            $table->string('challan_title')->default('FEE DEPOSIT CHALLAN');
            $table->string('bank_name')->default('National Bank Of Pakistan');
            $table->string('account_title')->default('Agriculture LiveStock and Fishries Department Gilgit Baltistan');
            $table->string('account_no')->default('4256260305');
            $table->string('account_head')->default('Application Processing Fee(C03870)');
            $table->unsignedInteger('validity_days')->default(10);
            $table->text('directions')->nullable();
            $table->string('fee_mode')->default('seed_configured'); // seed_configured, fixed_unit, fixed_total, free
            $table->decimal('govt_share_pct', 5, 2)->default(50.00);
            $table->decimal('farmer_share_pct', 5, 2)->default(50.00);
            $table->boolean('show_breakdown')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('schemes', function (Blueprint $table) {
            $table->foreignId('challan_template_id')->nullable()->after('challan_fee_per_unit')->constrained('challan_templates')->nullOnDelete();
        });

        // Seed initial default template per user specifications
        $templateId = DB::table('challan_templates')->insertGetId([
            'name' => 'Wheat Seed Subsidized Deposit Challan',
            'department_name' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'sub_title' => 'Government of Gilgit-Baltistan',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank Of Pakistan',
            'account_title' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'account_no' => '4256260305',
            'account_head' => 'Application Processing Fee(C03870)',
            'validity_days' => 10,
            'directions' => 'This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.',
            'fee_mode' => 'seed_configured',
            'govt_share_pct' => 50.00,
            'farmer_share_pct' => 50.00,
            'show_breakdown' => true,
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign to Wheat Seed scheme if exists
        DB::table('schemes')
            ->where('id', 1)
            ->orWhere('slug', 'distribution-of-wheat-seed-cost-sharing')
            ->orWhere('title', 'like', '%wheat%')
            ->update(['challan_template_id' => $templateId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schemes', function (Blueprint $table) {
            $table->dropForeign(['challan_template_id']);
            $table->dropColumn('challan_template_id');
        });

        Schema::dropIfExists('challan_templates');
    }
};
