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
        if (! Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('event_key', 80)->index();
                $table->foreignId('scheme_id')->nullable()->constrained('schemes')->nullOnDelete();
                $table->string('send_language', 20)->default('both'); // en, ur, both
                $table->string('template_name', 120)->nullable(); // Optional Meta Cloud API template identifier
                $table->text('message_en')->nullable();
                $table->text('message_ur')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->index(['event_key', 'scheme_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('whatsapp_logs')) {
            Schema::create('whatsapp_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
                $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
                $table->string('event_key', 80)->nullable()->index();
                $table->string('recipient_phone', 30)->index();
                $table->string('recipient_name')->nullable();
                $table->string('operator', 30)->nullable()->index();
                $table->string('language', 10)->default('en');
                $table->string('provider', 40)->nullable();
                $table->text('message');
                $table->string('status', 30)->default('sent')->index(); // sent, failed, simulated
                $table->string('provider_status_code', 50)->nullable();
                $table->string('provider_message_id', 120)->nullable();
                $table->text('provider_response')->nullable();
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        $defaultTemplates = [
            [
                'name' => 'Scheme Registration & Login Credentials (WhatsApp)',
                'event_key' => 'submitted',
                'scheme_id' => null,
                'send_language' => 'both',
                'template_name' => 'scheme_registration_submitted',
                'message_en' => "*ALF Department Gilgit-Baltistan*\nDear *{name}*, thank you for registering for *{scheme}*.\n\n• Application ID: *{application_id}*\n• Password (CNIC): *{password}*\n• Track Status: {tracking_url}",
                'message_ur' => "*محکمہ زراعت، لائیو اسٹاک و فشریز گلگت بلتستان*\nمحترم *{name}*، اسکیم (*{scheme}*) میں رجسٹریشن کا شکریہ۔\n• درخواست آئی ڈی: *{application_id}*\n• پاس ورڈ (شناختی کارڈ): *{password}*\n• ٹریکنگ لنک: {tracking_url}",
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Application Approved & Challan Issued (WhatsApp)',
                'event_key' => 'approved',
                'scheme_id' => null,
                'send_language' => 'both',
                'template_name' => 'application_approved_challan',
                'message_en' => "*Application Approved — ALF Dept GB*\nDear *{name}*, your application *{application_id}* for *{scheme}* has been *APPROVED* for *{approved_bags}* bag(s).\n\n• Payable Challan Fee: *Rs. {challan_amount}*\n• PSID: *{psid}*\n• Due Date: *{due_date}*",
                'message_ur' => "*درخواست منظور — محکمہ زراعت جی بی*\nمحترم *{name}*، آپ کی درخواست *{application_id}* برائے *{scheme}* منظور کر لی گئی ہے۔\n• منظور شدہ تھیلے: *{approved_bags}*\n• واجب الادا چالان فیس: *{challan_amount} روپے*\n• PSID: *{psid}*\n• آخری تاریخ: *{due_date}*",
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Application Rejected Notification (WhatsApp)',
                'event_key' => 'rejected',
                'scheme_id' => null,
                'send_language' => 'both',
                'template_name' => 'application_rejected',
                'message_en' => "*Application Status Update — ALF Dept GB*\nDear *{name}*, we regret to inform you that your application *{application_id}* for *{scheme}* was not approved.\n• Reason: {rejection_reason}",
                'message_ur' => "*محکمہ زراعت، لائیو اسٹاک و فشریز جی بی*\nمحترم *{name}*، آپ کی درخواست *{application_id}* برائے *{scheme}* منظور نہیں ہو سکی۔\n• وجہ: {rejection_reason}",
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'GBPay Bill Payment Success Confirmation (WhatsApp)',
                'event_key' => 'gbpay_payment_success',
                'scheme_id' => null,
                'send_language' => 'both',
                'template_name' => 'gbpay_payment_confirmed',
                'message_en' => "*Payment Confirmed — ALF Dept GB*\nDear *{name}*, payment of *Rs. {amount_paid}* against PSID *{psid}* for Application *{application_id}* (*{approved_bags}* bag(s)) has been received (Txn: {transaction_id}). You are now eligible for collection.",
                'message_ur' => "*ادائیگی کی تصدیق — محکمہ زراعت جی بی*\nمحترم *{name}*، درخواست نمبر *{application_id}* (*{approved_bags}* تھیلے) کے چالان PSID *{psid}* کی رقم *{amount_paid} روپے* کامیابی سے موصول ہو گئی ہے (ٹرانزیکشن: {transaction_id})۔ اب آپ بیج حاصل کرنے کے اہل ہیں۔",
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Application Correction Required (WhatsApp)',
                'event_key' => 'correction_required',
                'scheme_id' => null,
                'send_language' => 'both',
                'template_name' => 'application_correction_required',
                'message_en' => "*Correction Required — ALF Dept GB*\nDear *{name}*, correction is required on your application *{application_id}*: {correction_remarks}.\nLogin with Application ID *{application_id}* and CNIC *{password}* at {tracking_url}.",
                'message_ur' => "*درخواست میں درستگی درکار ہے — محکمہ زراعت جی بی*\nمحترم *{name}*، آپ کی درخواست *{application_id}* میں درستگی درکار ہے: {correction_remarks}۔ براہ کرم پورٹل پر اپنی درخواست اپڈیٹ کریں۔",
                'is_active' => true,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Stock Disbursed / Handed Over (WhatsApp)',
                'event_key' => 'fully_distributed',
                'scheme_id' => null,
                'send_language' => 'both',
                'template_name' => 'stock_fully_distributed',
                'message_en' => "*Stock Disbursed — ALF Dept GB*\nDear *{name}*, *{disbursed_bags}* bag(s) have been disbursed against your Application *{application_id}* (*{scheme}*). Thank you!",
                'message_ur' => "*بیج کی فراہمی — محکمہ زراعت جی بی*\nمحترم *{name}*، آپ کی درخواست *{application_id}* (*{scheme}*) کے تحت *{disbursed_bags}* تھیلے آپ کے حوالے کر دیے گئے ہیں۔ شکریہ!",
                'is_active' => true,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        if (Schema::hasTable('whatsapp_templates') && DB::table('whatsapp_templates')->count() === 0) {
            DB::table('whatsapp_templates')->insert($defaultTemplates);
        }

        if (Schema::hasTable('site_settings')) {
            $settings = [
                ['key' => 'whatsapp_enabled', 'label' => 'Enable Automated WhatsApp Notifications', 'value' => '1', 'group' => 'whatsapp'],
                ['key' => 'whatsapp_provider', 'label' => 'WhatsApp API Provider (meta_cloud / ultramsg / custom_http)', 'value' => 'meta_cloud', 'group' => 'whatsapp'],
                ['key' => 'whatsapp_api_url', 'label' => 'WhatsApp API Base URL', 'value' => 'https://graph.facebook.com/v20.0', 'group' => 'whatsapp'],
                ['key' => 'whatsapp_phone_number_id', 'label' => 'WhatsApp Phone Number ID / Instance ID', 'value' => env('WHATSAPP_PHONE_NUMBER_ID', ''), 'group' => 'whatsapp'],
                ['key' => 'whatsapp_business_account_id', 'label' => 'WhatsApp Business Account (WABA) ID', 'value' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''), 'group' => 'whatsapp'],
                ['key' => 'whatsapp_access_token', 'label' => 'WhatsApp API Access Token / API Key', 'value' => env('WHATSAPP_ACCESS_TOKEN', ''), 'group' => 'whatsapp'],
                ['key' => 'whatsapp_sender_number', 'label' => 'WhatsApp Sender / Display Number', 'value' => env('WHATSAPP_SENDER_NUMBER', ''), 'group' => 'whatsapp'],
                ['key' => 'whatsapp_verify_token', 'label' => 'WhatsApp Webhook Verify Token', 'value' => env('WHATSAPP_VERIFY_TOKEN', ''), 'group' => 'whatsapp'],
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
        Schema::dropIfExists('whatsapp_logs');
        Schema::dropIfExists('whatsapp_templates');
    }
};

