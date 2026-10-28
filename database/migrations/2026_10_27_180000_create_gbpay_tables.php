<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. PSID Configuration Table
        if (! Schema::hasTable('psid_config')) {
            Schema::create('psid_config', function (Blueprint $table) {
                $table->id('config_id');
                $table->string('one_link_prefix', 6)->default('101530'); // Digits 1-6
                $table->string('department_code', 2)->default('15');     // Digits 7-8
                $table->string('district_code', 2)->default('01');       // Digits 9-10
                $table->string('department_service_no', 1)->default('1'); // Digit 11
                $table->string('rco', 20)->nullable()->default('GL1800'); // RCO Code
                $table->string('head', 20)->nullable()->default('C03870'); // Head of Account Code
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::table('psid_config')->insert([
                'one_link_prefix' => '101530',
                'department_code' => '15',
                'district_code' => '01',
                'department_service_no' => '1',
                'rco' => 'GL1800',
                'head' => 'C03870',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Bills Table (linked to applications)
        if (! Schema::hasTable('bills')) {
            Schema::create('bills', function (Blueprint $table) {
                $table->id('bill_id');
                $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
                $table->string('psid', 20)->unique();
                $table->decimal('bill_amount', 12, 2);
                $table->string('status', 20)->default('pending'); // pending, paid, cancelled
                $table->timestamp('generated_at')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->longText('gbpay_response')->nullable();
                $table->string('transaction_id', 100)->nullable();
                $table->string('bank_mnemonic', 50)->nullable();
                $table->longText('challan_data')->nullable();
                $table->timestamps();

                $table->index('psid');
                $table->index('status');
            });
        }

        // 3. GBPay Responses Audit Log Table
        if (! Schema::hasTable('gbpay_responses')) {
            Schema::create('gbpay_responses', function (Blueprint $table) {
                $table->id('response_id');
                $table->unsignedBigInteger('bill_id')->nullable();
                $table->string('psid', 20)->nullable();
                $table->string('request_type', 50); // generate_bill, bill_inquiry, bill_payment, search_psid, check_status, webhook
                $table->longText('request_data')->nullable();
                $table->longText('response_data');
                $table->string('status_code', 20)->nullable();
                $table->string('status_message', 255)->nullable();
                $table->timestamps();

                $table->foreign('bill_id')->references('bill_id')->on('bills')->nullOnDelete();
                $table->index('psid');
                $table->index('request_type');
            });
        }

        // 4. GBPay API Credentials Table
        if (! Schema::hasTable('gbpay_api_credentials')) {
            Schema::create('gbpay_api_credentials', function (Blueprint $table) {
                $table->id('credential_id');
                $table->string('username', 100)->unique();
                $table->string('password');
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_ip_address', 45)->nullable();
                $table->timestamps();
            });

            DB::table('gbpay_api_credentials')->insert([
                [
                    'username' => 'alfgb@gbpay.gov.pk',
                    'password' => Hash::make('ChangeThisPassword123!'),
                    'description' => 'Default GBPay API credentials for ALF Gilgit-Baltistan',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'username' => 'gbhouse@gbpay.gov.pk',
                    'password' => Hash::make('ChangeThisPassword123!'),
                    'description' => 'Secondary/Test GBPay API credentials',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // 5. Add psid & gbpay_transaction_id to applications table
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'psid')) {
                $table->string('psid', 20)->nullable()->after('challan_no')->index();
            }
            if (! Schema::hasColumn('applications', 'gbpay_transaction_id')) {
                $table->string('gbpay_transaction_id', 100)->nullable()->after('challan_paid_at');
            }
        });

        // 6. Add rco_code and head_code to challan_templates table
        if (Schema::hasTable('challan_templates')) {
            Schema::table('challan_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('challan_templates', 'rco_code')) {
                    $table->string('rco_code', 20)->nullable()->default('GL1800')->after('account_head');
                }
                if (! Schema::hasColumn('challan_templates', 'head_code')) {
                    $table->string('head_code', 20)->nullable()->default('C03870')->after('rco_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('challan_templates')) {
            Schema::table('challan_templates', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('challan_templates', 'rco_code')) {
                    $cols[] = 'rco_code';
                }
                if (Schema::hasColumn('challan_templates', 'head_code')) {
                    $cols[] = 'head_code';
                }
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        Schema::table('applications', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('applications', 'psid')) {
                $cols[] = 'psid';
            }
            if (Schema::hasColumn('applications', 'gbpay_transaction_id')) {
                $cols[] = 'gbpay_transaction_id';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('gbpay_api_credentials');
        Schema::dropIfExists('gbpay_responses');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('psid_config');
    }
};

