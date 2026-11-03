-- ============================================================================
-- ALFGB DATABASE MIGRATION SCRIPT (LAST 2 DAYS CHANGES)
-- Date: 2026-10-30
-- Target: Live Production Database
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. CREATE SMS TEMPLATES TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sms_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheme_id` bigint(20) unsigned DEFAULT NULL,
  `send_language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `message_en` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_ur` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sms_templates_event_key_index` (`event_key`),
  KEY `sms_templates_scheme_id_foreign` (`scheme_id`),
  KEY `sms_templates_event_key_scheme_id_is_active_index` (`event_key`,`scheme_id`,`is_active`),
  CONSTRAINT `sms_templates_scheme_id_foreign` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. CREATE SMS LOGS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned DEFAULT NULL,
  `sms_template_id` bigint(20) unsigned DEFAULT NULL,
  `event_key` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `is_unicode` tinyint(1) NOT NULL DEFAULT 0,
  `mask` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `zong_status_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zong_sms_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zong_response` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_by` bigint(20) unsigned DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sms_logs_application_id_foreign` (`application_id`),
  KEY `sms_logs_sms_template_id_foreign` (`sms_template_id`),
  KEY `sms_logs_sent_by_foreign` (`sent_by`),
  KEY `sms_logs_event_key_index` (`event_key`),
  KEY `sms_logs_recipient_phone_index` (`recipient_phone`),
  KEY `sms_logs_status_index` (`status`),
  CONSTRAINT `sms_logs_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sms_logs_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sms_logs_sms_template_id_foreign` FOREIGN KEY (`sms_template_id`) REFERENCES `sms_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. INSERT DEFAULT BILINGUAL SMS TEMPLATES
-- ----------------------------------------------------------------------------
INSERT INTO `sms_templates` (`name`, `event_key`, `scheme_id`, `send_language`, `message_en`, `message_ur`, `is_active`, `is_system`, `created_at`, `updated_at`)
VALUES
(
  'Scheme Registration & Login Credentials',
  'submitted',
  NULL,
  'both',
  'Dear {name}, thank you for registering for {scheme}. Your Application ID is {application_id} and Password is your CNIC ({password}). Track status at: {tracking_url} - ALF Dept GB',
  'محترم {name}، اسکیم ({scheme}) میں رجسٹریشن کا شکریہ۔ آپ کی درخواست آئی ڈی {application_id} اور پاس ورڈ آپ کا شناختی کارڈ نمبر ({password}) ہے۔ محکمہ زراعت، لائیو اسٹاک و فشریز جی بی',
  1, 1, NOW(), NOW()
),
(
  'Application Approved & Challan Issued',
  'approved',
  NULL,
  'both',
  'Dear {name}, your application {application_id} for {scheme} has been APPROVED for {approved_bags} bag(s). Payable Challan Fee: Rs. {challan_amount} (PSID: {psid}, Due: {due_date}). - ALF Dept GB',
  'محترم {name}، آپ کی درخواست {application_id} برائے {scheme} منظور کر لی گئی ہے۔ منظور شدہ تھیلے: {approved_bags}۔ واجب الادا چالان فیس: {challan_amount} روپے (PSID: {psid}، آخری تاریخ: {due_date})۔ محکمہ زراعت جی بی',
  1, 1, NOW(), NOW()
),
(
  'Application Rejected Notification',
  'rejected',
  NULL,
  'both',
  'Dear {name}, we regret to inform you that your application {application_id} for {scheme} was not approved. Reason: {rejection_reason}. - ALF Dept GB',
  'محترم {name}، آپ کی درخواست {application_id} برائے {scheme} منظور نہیں ہو سکی۔ وجہ: {rejection_reason}۔ محکمہ زراعت، لائیو اسٹاک و فشریز جی بی',
  1, 1, NOW(), NOW()
),
(
  'GBPay Bill Payment Success Confirmation',
  'gbpay_payment_success',
  NULL,
  'both',
  'Dear {name}, payment of Rs. {amount_paid} against PSID {psid} for Application {application_id} ({approved_bags} bag(s)) has been received successfully (Txn: {transaction_id}). You are now eligible for collection. - ALF Dept GB',
  'محترم {name}، درخواست نمبر {application_id} ({approved_bags} تھیلے) کے چالان PSID {psid} کی رقم {amount_paid} روپے کامیابی سے موصول ہو گئی ہے (ٹرانزیکشن: {transaction_id})۔ اب آپ بیج حاصل کرنے کے اہل ہیں۔ محکمہ زراعت جی بی',
  1, 1, NOW(), NOW()
),
(
  'Application Correction Required',
  'correction_required',
  NULL,
  'both',
  'Dear {name}, correction is required on your application {application_id}: {correction_remarks}. Please login with Application ID {application_id} and CNIC {password} at {tracking_url}. - ALF Dept GB',
  'محترم {name}، آپ کی درخواست {application_id} میں درستگی درکار ہے: {correction_remarks}۔ براہ کرم پورٹل پر اپنی درخواست اپڈیٹ کریں۔ محکمہ زراعت جی بی',
  1, 0, NOW(), NOW()
),
(
  'Field Survey Assigned',
  'field_survey_assigned',
  NULL,
  'both',
  'Dear {name}, a field verification officer has been assigned for your application {application_id} ({scheme}) in {district}. - ALF Dept GB',
  'محترم {name}، آپ کی درخواست {application_id} ({scheme}) کی زمین کی تصدیق کے لیے فیلڈ آفیسر مقرر کر دیا گیا ہے۔ محکمہ زراعت جی بی',
  0, 0, NOW(), NOW()
),
(
  'Stock Disbursed / Handed Over',
  'fully_distributed',
  NULL,
  'both',
  'Dear {name}, {disbursed_bags} bag(s) have been disbursed against your Application {application_id} ({scheme}). Thank you! - ALF Dept GB',
  'محترم {name}، آپ کی درخواست {application_id} ({scheme}) کے تحت {disbursed_bags} تھیلے آپ کے حوالے کر دیے گئے ہیں۔ شکریہ! محکمہ زراعت جی بی',
  1, 0, NOW(), NOW()
),
(
  'Unpaid Challan Expired (Dormant)',
  'dormant',
  NULL,
  'both',
  'Dear {name}, the challan for your application {application_id} has expired on {due_date}. Please contact the District Deputy Director office in {district} for reactivation. - ALF Dept GB',
  'محترم {name}، آپ کی درخواست {application_id} کے چالان کی میعاد {due_date} کو ختم ہو گئی ہے۔ دوبارہ بحالی کے لیے ضلعی ڈپٹی ڈائریکٹر آفس ({district}) سے رابطہ کریں۔',
  0, 0, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- ----------------------------------------------------------------------------
-- 4. INSERT / UPDATE ZONG CBS GATEWAY SETTINGS IN `site_settings`
-- ----------------------------------------------------------------------------
INSERT INTO `site_settings` (`key`, `label`, `value`, `group`, `created_at`, `updated_at`)
VALUES
  ('sms_enabled', 'Enable Automated SMS Notifications', '1', 'sms', NOW(), NOW()),
  ('zong_cbs_api_mode', 'Zong CBS API Protocol (rest / soap)', 'rest', 'sms', NOW(), NOW()),
  ('zong_cbs_base_url', 'Zong CBS REST Base URL', 'https://cbs.zong.com.pk/reachrestapi/home', 'sms', NOW(), NOW()),
  ('zong_cbs_soap_url', 'Zong CBS SOAP Endpoint URL', 'https://cbs.zong.com.pk/ReachCWSv2/CorporateSMS.svc', 'sms', NOW(), NOW()),
  ('zong_cbs_login_id', 'Zong CBS Login ID', '923159587777', 'sms', NOW(), NOW()),
  ('zong_cbs_login_password', 'Zong CBS Login Password', 'hck#234%L', 'sms', NOW(), NOW()),
  ('zong_cbs_mask', 'Zong CBS Masking Name', 'Zong CBS TR', 'sms', NOW(), NOW()),
  ('zong_cbs_shortcode_preferred', 'Short Code Preferred (N / Y)', 'N', 'sms', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
  `value` = VALUES(`value`),
  `updated_at` = NOW();

-- ----------------------------------------------------------------------------
-- 5. REGISTER MIGRATION ENTRY (SO LARAVEL KNOWS IT HAS RUN)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `migrations` (`migration`, `batch`)
VALUES ('2026_10_28_140000_create_sms_templates_and_logs_tables', (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations` m));

-- ----------------------------------------------------------------------------
-- 6. AUTOMATICALLY ASSIGN UNASSIGNED APPLICATIONS TO DISTRICT DEPUTY DIRECTORS
-- ----------------------------------------------------------------------------
UPDATE `applications` a
JOIN `users` u 
    ON u.`district_id` = a.`district_id`
   AND u.`department_id` = a.`department_id`
   AND u.`is_active` = 1
JOIN `model_has_roles` mhr 
    ON mhr.`model_id` = u.`id` 
   AND mhr.`model_type` = 'App\\Models\\User'
JOIN `roles` r 
    ON r.`id` = mhr.`role_id` 
   AND r.`name` IN ('District Deputy Director', 'District Officer')
LEFT JOIN `offices` o 
    ON o.`district_id` = a.`district_id` 
   AND o.`department_id` = a.`department_id`
SET 
    a.`assigned_to`           = u.`id`,
    a.`assigned_at`           = COALESCE(a.`assigned_at`, NOW()),
    a.`responsible_office_id` = COALESCE(o.`id`, a.`responsible_office_id`),
    a.`is_unassigned`         = 0,
    a.`stage_updated_at`      = NOW(),
    a.`updated_at`            = NOW()
WHERE 
    a.`is_unassigned` = 1 
    OR a.`assigned_to` IS NULL;

