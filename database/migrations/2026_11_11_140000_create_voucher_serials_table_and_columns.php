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
        if (! Schema::hasTable('voucher_serials')) {
            Schema::create('voucher_serials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('serial_no')->unique();
                $table->string('voucher_code', 10)->unique();
                $table->unsignedBigInteger('application_id')->nullable()->index();
                $table->unsignedBigInteger('bill_id')->nullable()->index();
                $table->string('psid', 20)->nullable()->index();
                $table->string('challan_no', 100)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('psid_config') && ! Schema::hasColumn('psid_config', 'last_voucher_serial')) {
            Schema::table('psid_config', function (Blueprint $table) {
                $table->unsignedBigInteger('last_voucher_serial')->default(0)->after('is_active');
            });
        }

        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'voucher_serial')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->unsignedBigInteger('voucher_serial')->nullable()->unique()->after('psid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'voucher_serial')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropUnique(['voucher_serial']);
                $table->dropColumn('voucher_serial');
            });
        }

        if (Schema::hasTable('psid_config') && Schema::hasColumn('psid_config', 'last_voucher_serial')) {
            Schema::table('psid_config', function (Blueprint $table) {
                $table->dropColumn('last_voucher_serial');
            });
        }

        Schema::dropIfExists('voucher_serials');
    }
};

