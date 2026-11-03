-- ============================================================================
-- ALFGB PRODUCTION DATABASE UPDATE SCRIPT (LAST 4 DAYS)
-- Generated: 2026-11-01
-- Covers all changes, migrations, tables, seeders, and routing from Oct 27 to Oct 30, 2026:
--   1. Stock Disbursements Table (Oct 27)
--   2. Demanded & Approved Quantity on Applications Table (Oct 27)
--   3. Dynamic Challan Templates Table & Scheme association (Oct 27)
--   4. GBPay 1Link 20-Digit PSID Tables (Oct 27)
--   5. SMS Templates & SMS Logs Tables (Oct 28)
--   6. Default Bilingual SMS Templates Seed Data (Oct 28)
--   7. Live Zong CBS Gateway Settings in `site_settings` (Oct 30)
--   8. Automated District Deputy Director Assignment for Unassigned Records (Oct 30)
--   9. Laravel Migrations Table Sync
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- STEP 1: CREATE `stock_disbursements` TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_disbursements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `application_id` bigint(20) unsigned NOT NULL,
  `collection_center_id` bigint(20) unsigned NOT NULL,
  `scheme_stock_item_id` bigint(20) unsigned NOT NULL,
  `district_id` bigint(20) unsigned NOT NULL,
  `quantity_disbursed` decimal(12,2) NOT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_cnic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_original_applicant` tinyint(1) NOT NULL DEFAULT 1,
  `representative_relationship` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `challan_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `challan_verified` tinyint(1) NOT NULL DEFAULT 1,
  `disbursement_date` date NOT NULL,
  `disbursed_by` bigint(20) unsigned DEFAULT NULL,
  `proof_attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_disbursements_disbursement_no_unique` (`disbursement_no`),
  KEY `stock_disbursements_district_id_collection_center_id_index` (`district_id`,`collection_center_id`),
  KEY `stock_disbursements_application_id_index` (`application_id`),
  KEY `stock_disbursements_collection_center_id_foreign` (`collection_center_id`),
  KEY `stock_disbursements_scheme_stock_item_id_foreign` (`scheme_stock_item_id`),
  KEY `stock_disbursements_disbursed_by_foreign` (`disbursed_by`),
  CONSTRAINT `stock_disbursements_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_disbursements_collection_center_id_foreign` FOREIGN KEY (`collection_center_id`) REFERENCES `collection_centers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_disbursements_disbursed_by_foreign` FOREIGN KEY (`disbursed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_disbursements_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_disbursements_scheme_stock_item_id_foreign` FOREIGN KEY (`scheme_stock_item_id`) REFERENCES `scheme_stock_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- STEP 2: ADD `demanded_quantity` & `approved_quantity` TO `applications`
-- ----------------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'applications' 
      AND COLUMN_NAME = 'demanded_quantity'
);

SET @stmt = IF(@col_exists = 0, 
    'ALTER TABLE `applications` ADD COLUMN `demanded_quantity` decimal(10,2) NULL AFTER `reactivation_reason`, ADD COLUMN `approved_quantity` decimal(10,2) NULL AFTER `demanded_quantity`', 
    'SELECT "Column demanded_quantity already exists"'
);
PREPARE alter_stmt FROM @stmt;
EXECUTE alter_stmt;
DEALLOCATE PREPARE alter_stmt;

-- ----------------------------------------------------------------------------
-- STEP 3: CREATE `challan_templates` TABLE & ADD `challan_template_id` TO `schemes`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `challan_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
  `sub_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Government of Gilgit-Baltistan',
  `challan_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FEE DEPOSIT CHALLAN',
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'National Bank Of Pakistan',
  `account_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
  `account_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '4256260305',
  `account_head` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Application Processing Fee(C03870)',
  `validity_days` int(10) unsigned NOT NULL DEFAULT 10,
  `directions` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'seed_configured',
  `govt_share_pct` decimal(5,2) NOT NULL DEFAULT 50.00,
  `farmer_share_pct` decimal(5,2) NOT NULL DEFAULT 50.00,
  `show_breakdown` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add challan_template_id column to schemes if missing
SET @col_exists2 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'schemes' 
      AND COLUMN_NAME = 'challan_template_id'
);

SET @stmt2 = IF(@col_exists2 = 0, 
    'ALTER TABLE `schemes` ADD COLUMN `challan_template_id` bigint(20) unsigned NULL AFTER `challan_fee_per_unit`, ADD CONSTRAINT `schemes_challan_template_id_foreign` FOREIGN KEY (`challan_template_id`) REFERENCES `challan_templates` (`id`) ON DELETE SET NULL', 
    'SELECT "Column challan_template_id already exists"'
);
PREPARE alter_stmt2 FROM @stmt2;
EXECUTE alter_stmt2;
DEALLOCATE PREPARE alter_stmt2;

-- Insert default challan template
INSERT INTO `challan_templates` (`id`, `name`, `department_name`, `sub_title`, `challan_title`, `bank_name`, `account_title`, `account_no`, `account_head`, `validity_days`, `directions`, `fee_mode`, `govt_share_pct`, `farmer_share_pct`, `show_breakdown`, `is_active`, `is_default`, `created_at`, `updated_at`)
VALUES (
  1,
  'Wheat Seed Subsidized Deposit Challan',
  'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
  'Government of Gilgit-Baltistan',
  'FEE DEPOSIT CHALLAN',
  'National Bank Of Pakistan',
  'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
  '4256260305',
  'Application Processing Fee(C03870)',
  10,
  'This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.',
  'seed_configured',
  50.00,
  50.00,
  1, 1, 1, NOW(), NOW()
) ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Link Wheat Seed scheme to default challan template
UPDATE `schemes` 
SET `challan_template_id` = 1 
WHERE (`id` = 1 OR `slug` LIKE '%wheat%' OR `title` LIKE '%wheat%') 
  AND `challan_template_id` IS NULL;

-- ----------------------------------------------------------------------------
-- STEP 4: CREATE GBPAY 1LINK TABLES (`psid_config`, `bills`, `gbpay_responses`, `gbpay_api_credentials`)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `psid_config` (
  `config_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `one_link_prefix` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '101530',
  `department_code` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '15',
  `district_code` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01',
  `department_service_no` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `rco` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'GL1800',
  `head` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'C03870',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `psid_config` (`config_id`, `one_link_prefix`, `department_code`, `district_code`, `department_service_no`, `rco`, `head`, `is_active`, `created_at`, `updated_at`)
VALUES (1, '101530', '15', '01', '1', 'GL1800', 'C03870', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

CREATE TABLE IF NOT EXISTS `bills` (
  `bill_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned NOT NULL,
  `psid` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bill_amount` decimal(12,2) NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `generated_at` timestamp NULL DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `gbpay_response` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_mnemonic` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `challan_data` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`bill_id`),
  UNIQUE KEY `bills_psid_unique` (`psid`),
  KEY `bills_application_id_foreign` (`application_id`),
  KEY `bills_psid_index` (`psid`),
  KEY `bills_status_index` (`status`),
  CONSTRAINT `bills_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gbpay_responses` (
  `response_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` bigint(20) unsigned DEFAULT NULL,
  `psid` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_data` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`response_id`),
  KEY `gbpay_responses_bill_id_foreign` (`bill_id`),
  KEY `gbpay_responses_psid_index` (`psid`),
  KEY `gbpay_responses_request_type_index` (`request_type`),
  CONSTRAINT `gbpay_responses_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`bill_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gbpay_api_credentials` (
  `credential_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `last_ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`credential_id`),
  UNIQUE KEY `gbpay_api_credentials_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gbpay_api_credentials` (`username`, `password`, `description`, `is_active`, `created_at`, `updated_at`)
VALUES 
  ('alfgb@gbpay.gov.pk', '$2y$12$Z0b1lHkS0FmH71j6Uq85x.xNcvTz/oD/xsqc49t6H3vJgG9xL6fuy', 'GBPay Portal Production Consumer API Key', 1, NOW(), NOW()),
  ('1link@gbpay.gov.pk', '$2y$12$Z0b1lHkS0FmH71j6Uq85x.xNcvTz/oD/xsqc49t6H3vJgG9xL6fuy', '1Link Bill Inquiry & Payment Gateway Service', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- ----------------------------------------------------------------------------
-- STEP 5: CREATE `sms_templates` & `sms_logs` TABLES
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
-- STEP 6: INSERT DEFAULT BILINGUAL SMS TEMPLATES
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
-- STEP 7: CONFIGURE ZONG CBS GATEWAY CREDENTIALS IN `site_settings`
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
-- STEP 8: AUTO-ASSIGN ALL UNASSIGNED APPLICATIONS TO DISTRICT DEPUTY DIRECTORS
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

-- ----------------------------------------------------------------------------
-- STEP 9: RECORD MIGRATIONS IN `migrations` TABLE
-- ----------------------------------------------------------------------------
SET @cur_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
('2026_10_27_120000_create_stock_disbursements_table', @cur_batch),
('2026_10_27_150000_add_demanded_and_approved_quantity_to_applications', @cur_batch),
('2026_10_27_160000_create_challan_templates_table', @cur_batch),
('2026_10_27_180000_create_gbpay_tables', @cur_batch),
('2026_10_28_140000_create_sms_templates_and_logs_tables', @cur_batch);

SET FOREIGN_KEY_CHECKS = 1;

