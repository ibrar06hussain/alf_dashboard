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
        if (! Schema::hasTable('sms_templates')) {
            Schema::create('sms_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('event_key', 80)->index();
                $table->foreignId('scheme_id')->nullable()->constrained('schemes')->nullOnDelete();
                $table->string('send_language', 20)->default('both'); // en, ur, both
                $table->text('message_en')->nullable();
                $table->text('message_ur')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->index(['event_key', 'scheme_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('sms_logs')) {
            Schema::create('sms_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
                $table->foreignId('sms_template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
                $table->string('event_key', 80)->nullable()->index();
                $table->string('recipient_phone', 30)->index();
                $table->string('recipient_name')->nullable();
                $table->string('language', 10)->default('en');
                $table->boolean('is_unicode')->default(false);
                $table->string('mask', 50)->nullable();
                $table->text('message');
                $table->string('status', 30)->default('sent')->index(); // sent, failed, simulated
                $table->string('zong_status_code', 30)->nullable();
                $table->string('zong_sms_id', 100)->nullable();
                $table->text('zong_response')->nullable();
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        // Seed default bilingual SMS templates
        $now = now();
        $defaultTemplates = [
            [
                'name' => 'Scheme Registration & Login Credentials',
                'event_key' => 'submitted',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, thank you for registering for {scheme}. Your Application ID is {application_id} and Password is your CNIC ({password}). Track status at: {tracking_url} - ALF Dept GB',
                'message_ur' => 'محترم {name}، اسکیم ({scheme}) میں رجسٹریشن کا شکریہ۔ آپ کی درخواست آئی ڈی {application_id} اور پاس ورڈ آپ کا شناختی کارڈ نمبر ({password}) ہے۔ محکمہ زراعت، لائیو اسٹاک و فشریز جی بی',
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Application Approved & Challan Issued',
                'event_key' => 'approved',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, your application {application_id} for {scheme} has been APPROVED for {approved_bags} bag(s). Payable Challan Fee: Rs. {challan_amount} (PSID: {psid}, Due: {due_date}). - ALF Dept GB',
                'message_ur' => 'محترم {name}، آپ کی درخواست {application_id} برائے {scheme} منظور کر لی گئی ہے۔ منظور شدہ تھیلے: {approved_bags}۔ واجب الادا چالان فیس: {challan_amount} روپے (PSID: {psid}، آخری تاریخ: {due_date})۔ محکمہ زراعت جی بی',
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Application Rejected Notification',
                'event_key' => 'rejected',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, we regret to inform you that your application {application_id} for {scheme} was not approved. Reason: {rejection_reason}. - ALF Dept GB',
                'message_ur' => 'محترم {name}، آپ کی درخواست {application_id} برائے {scheme} منظور نہیں ہو سکی۔ وجہ: {rejection_reason}۔ محکمہ زراعت، لائیو اسٹاک و فشریز جی بی',
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'GBPay Bill Payment Success Confirmation',
                'event_key' => 'gbpay_payment_success',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, payment of Rs. {amount_paid} against PSID {psid} for Application {application_id} ({approved_bags} bag(s)) has been received successfully (Txn: {transaction_id}). You are now eligible for collection. - ALF Dept GB',
                'message_ur' => 'محترم {name}، درخواست نمبر {application_id} ({approved_bags} تھیلے) کے چالان PSID {psid} کی رقم {amount_paid} روپے کامیابی سے موصول ہو گئی ہے (ٹرانزیکشن: {transaction_id})۔ اب آپ بیج حاصل کرنے کے اہل ہیں۔ محکمہ زراعت جی بی',
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Application Correction Required',
                'event_key' => 'correction_required',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, correction is required on your application {application_id}: {correction_remarks}. Please login with Application ID {application_id} and CNIC {password} at {tracking_url}. - ALF Dept GB',
                'message_ur' => 'محترم {name}، آپ کی درخواست {application_id} میں درستگی درکار ہے: {correction_remarks}۔ براہ کرم پورٹل پر اپنی درخواست اپڈیٹ کریں۔ محکمہ زراعت جی بی',
                'is_active' => true,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Field Survey Assigned',
                'event_key' => 'field_survey_assigned',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, a field verification officer has been assigned for your application {application_id} ({scheme}) in {district}. - ALF Dept GB',
                'message_ur' => 'محترم {name}، آپ کی درخواست {application_id} ({scheme}) کی زمین کی تصدیق کے لیے فیلڈ آفیسر مقرر کر دیا گیا ہے۔ محکمہ زراعت جی بی',
                'is_active' => false,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Stock Disbursed / Handed Over',
                'event_key' => 'fully_distributed',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, {disbursed_bags} bag(s) have been disbursed against your Application {application_id} ({scheme}). Thank you! - ALF Dept GB',
                'message_ur' => 'محترم {name}، آپ کی درخواست {application_id} ({scheme}) کے تحت {disbursed_bags} تھیلے آپ کے حوالے کر دیے گئے ہیں۔ شکریہ! محکمہ زراعت جی بی',
                'is_active' => true,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Unpaid Challan Expired (Dormant)',
                'event_key' => 'dormant',
                'scheme_id' => null,
                'send_language' => 'both',
                'message_en' => 'Dear {name}, the challan for your application {application_id} has expired on {due_date}. Please contact the District Deputy Director office in {district} for reactivation. - ALF Dept GB',
                'message_ur' => 'محترم {name}، آپ کی درخواست {application_id} کے چالان کی میعاد {due_date} کو ختم ہو گئی ہے۔ دوبارہ بحالی کے لیے ضلعی ڈپٹی ڈائریکٹر آفس ({district}) سے رابطہ کریں۔',
                'is_active' => false,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        if (Schema::hasTable('sms_templates') && DB::table('sms_templates')->count() === 0) {
            DB::table('sms_templates')->insert($defaultTemplates);
        }

        // Seed default Zong CBS Gateway settings into site_settings if table exists
        if (Schema::hasTable('site_settings')) {
            $settings = [
                ['key' => 'sms_enabled', 'label' => 'Enable Automated SMS Notifications', 'value' => '1', 'group' => 'sms'],
                ['key' => 'zong_cbs_api_mode', 'label' => 'Zong CBS API Protocol (rest / soap)', 'value' => 'rest', 'group' => 'sms'],
                ['key' => 'zong_cbs_base_url', 'label' => 'Zong CBS REST Base URL', 'value' => 'https://cbs.zong.com.pk/reachrestapi/home', 'group' => 'sms'],
                ['key' => 'zong_cbs_soap_url', 'label' => 'Zong CBS SOAP Endpoint URL', 'value' => 'https://cbs.zong.com.pk/ReachCWSv2/CorporateSMS.svc', 'group' => 'sms'],
                ['key' => 'zong_cbs_login_id', 'label' => 'Zong CBS Login ID', 'value' => env('ZONG_CBS_LOGIN_ID', ''), 'group' => 'sms'],
                ['key' => 'zong_cbs_login_password', 'label' => 'Zong CBS Login Password', 'value' => env('ZONG_CBS_LOGIN_PASSWORD', ''), 'group' => 'sms'],
                ['key' => 'zong_cbs_mask', 'label' => 'Zong CBS Masking Name', 'value' => env('ZONG_CBS_MASK', 'ALF-GB'), 'group' => 'sms'],
                ['key' => 'zong_cbs_shortcode_preferred', 'label' => 'Short Code Preferred (N / Y)', 'value' => 'N', 'group' => 'sms'],
            ];

            foreach ($settings as $setting) {
                if (! DB::table('site_settings')->where('key', $setting['key'])->exists()) {
                    DB::table('site_settings')->insert(array_merge($setting, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_templates');
    }
};

