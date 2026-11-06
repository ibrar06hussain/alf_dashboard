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
        if (Schema::hasTable('psid_config') && Schema::hasColumn('psid_config', 'department_service_no')) {
            Schema::table('psid_config', function (Blueprint $table) {
                $table->string('one_link_prefix', 6)->default('101530')->change();
                $table->string('department_code', 2)->default('09')->change();
                $table->string('department_service_no', 2)->default('01')->change();
            });

            DB::table('psid_config')->update([
                'one_link_prefix' => '101530',
                'department_code' => '09',
                'department_service_no' => '01',
                'updated_at' => now(),
            ]);
        }

        if (DB::getDriverName() !== 'sqlite') {
            if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'bill_amount')) {
                DB::table('bills')
                    ->whereRaw('bill_amount != CEIL(bill_amount)')
                    ->update([
                        'bill_amount' => DB::raw('CEIL(bill_amount)'),
                    ]);
            }

            if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'challan_amount')) {
                DB::table('applications')
                    ->whereNotNull('challan_amount')
                    ->whereRaw('challan_amount != CEIL(challan_amount)')
                    ->update([
                        'challan_amount' => DB::raw('CEIL(challan_amount)'),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('psid_config') && Schema::hasColumn('psid_config', 'department_service_no')) {
            DB::table('psid_config')
                ->whereRaw('CHAR_LENGTH(department_service_no) > 1')
                ->update([
                    'department_service_no' => DB::raw('RIGHT(department_service_no, 1)'),
                ]);

            Schema::table('psid_config', function (Blueprint $table) {
                $table->string('department_service_no', 1)->default('1')->change();
            });
        }
    }
};
