-- ============================================================================
-- Live Server Sync Script for Scheme #44 (Upscaling of Green Pakistan - Trees)
-- ============================================================================
-- Compatible with: MariaDB 10.4+ and MySQL 8.0+ (phpMyAdmin / MySQL CLI safe).
-- What this SQL script does (idempotent):
--   1. Ensures Scheme #44 has requires_challan = 1, unit_label = 'Trees', has_auto_dashboard = 1.
--   2. Ensures Scheme #44's billable FormField sets is_billable_items_field = 1 and updates the 'Cherry' option in option_configs_json to paid @ Rs. 350 (govt_share_pct = null -> inherits template).
--   3. Updates existing UNPAID application_items on Scheme #44 (Cherry -> Rs. 350, inherits template share) without touching PAID applications or PAID bills.
--   4. Backfills missing application_items from legacy application_field_values (e.g. "Apple: 20; Cherry: 150 chery; Cherry: ۲۰; ...") and updates demanded_quantity (leaving 'submitted' apps in 'submitted' stage until DD approves).
--   5. Regenerates/updates challan_amount & pending bills.bill_amount (preserving existing 20-digit PSIDs) for approved unpaid apps containing paid items (Cherry), and cancels pending bills promoting to 'eligible_beneficiary' if an approved unpaid app has only free items (Rs. 0 payable).
--
-- Prerequisites on Live Server:
--   - Ensure latest code & migrations are deployed (`php artisan migrate --force`), specifically `application_items`, `schemes.unit_label`, and `application_items.inherits_template_share`.
--   - Take a database backup before running in production.
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS `sp_sync_scheme_44_paid_items`;

DELIMITER $$

CREATE PROCEDURE `sp_sync_scheme_44_paid_items`()
BEGIN
    DECLARE v_scheme_id BIGINT DEFAULT 44;
    DECLARE v_form_id BIGINT DEFAULT NULL;
    DECLARE v_field_id BIGINT DEFAULT NULL;
    DECLARE v_fallback_field_id BIGINT DEFAULT NULL;
    DECLARE v_govt_pct DECIMAL(5,2) DEFAULT 0.00;
    DECLARE v_farmer_pct DECIMAL(5,2) DEFAULT 100.00;
    DECLARE v_validity_days INT DEFAULT 30;
    DECLARE v_cherry_price DECIMAL(10,2) DEFAULT 350.00;

    DECLARE v_opt_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_opt_len INT DEFAULT 0;
    DECLARE v_opt_idx INT DEFAULT 0;
    DECLARE v_opt_label VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    DECLARE v_done INT DEFAULT 0;
    DECLARE v_app_id BIGINT;
    DECLARE v_stage VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_status VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_raw_val LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_is_approved TINYINT(1);

    DECLARE v_rest LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_sep CHAR(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_pos INT;
    DECLARE v_part TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_colon INT;
    DECLARE v_label VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_qty_raw TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_qty_norm TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_qty_val DECIMAL(12,2);
    DECLARE v_i INT;
    DECLARE v_len INT;
    DECLARE v_ch CHAR(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_num_buf VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_seen_dot TINYINT(1);
    DECLARE v_started_num TINYINT(1);

    DECLARE v_item_key VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_existing_item_id BIGINT DEFAULT NULL;
    DECLARE v_is_free TINYINT(1);
    DECLARE v_unit_price DECIMAL(10,2);
    DECLARE v_inherits TINYINT(1);
    DECLARE v_it_govt DECIMAL(5,2);
    DECLARE v_it_farmer DECIMAL(5,2);
    DECLARE v_gross DECIMAL(12,2);
    DECLARE v_govt_amt DECIMAL(12,2);
    DECLARE v_farmer_amt DECIMAL(12,2);
    DECLARE v_total_demanded DECIMAL(12,2);
    DECLARE v_total_approved DECIMAL(12,2);

    DECLARE v_cur_apps CURSOR FOR
        SELECT
            a.id,
            COALESCE(a.current_stage, ''),
            COALESCE(a.status, ''),
            COALESCE(NULLIF(TRIM(fv_primary.value), ''), NULLIF(TRIM(fv_fallback.value), ''), '') AS raw_items_text
        FROM applications a
        LEFT JOIN application_field_values fv_primary
            ON fv_primary.application_id = a.id
            AND fv_primary.form_field_id = v_field_id
        LEFT JOIN application_field_values fv_fallback
            ON fv_fallback.application_id = a.id
            AND fv_fallback.form_field_id = v_fallback_field_id
        WHERE a.scheme_id = v_scheme_id
          AND a.payment_verified_at IS NULL
          AND a.gbpay_transaction_id IS NULL
          AND a.challan_paid_at IS NULL
          AND COALESCE(a.distributed_quantity, 0) <= 0
          AND COALESCE(a.current_stage, a.status, '') NOT IN (
              'rejected', 'dormant', 'payment_verified',
              'stock_allocated', 'partially_distributed', 'fully_distributed',
              'completed', 'disbursed'
          )
          AND NOT EXISTS (
              SELECT 1 FROM bills b WHERE b.application_id = a.id AND b.status = 'paid'
          )
          AND NOT EXISTS (
              SELECT 1 FROM application_items ai WHERE ai.application_id = a.id
          );

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    -- 1. Ensure Scheme #44 settings & resolve active ChallanTemplate percentages
    UPDATE schemes
    SET
        requires_challan = 1,
        unit_label = COALESCE(NULLIF(TRIM(unit_label), ''), 'Trees'),
        has_auto_dashboard = 1,
        updated_at = NOW()
    WHERE id = v_scheme_id;

    SELECT
        COALESCE(t.govt_share_pct, 0.00),
        COALESCE(t.farmer_share_pct, 100.00),
        COALESCE(NULLIF(t.validity_days, 0), 30)
    INTO v_govt_pct, v_farmer_pct, v_validity_days
    FROM schemes s
    LEFT JOIN challan_templates t
        ON t.id = COALESCE(
            s.challan_template_id,
            (SELECT id FROM challan_templates WHERE is_default = 1 LIMIT 1)
        )
    WHERE s.id = v_scheme_id
    LIMIT 1;

    -- 2. Locate published form & checkbox_with_text billable field, and ensure Cherry = paid @ Rs. 350
    SELECT f.id INTO v_form_id
    FROM forms f
    WHERE f.scheme_id = v_scheme_id
    ORDER BY f.is_published DESC, f.version DESC, f.id DESC
    LIMIT 1;

    SELECT ff.id, ff.option_configs_json INTO v_field_id, v_opt_json
    FROM form_fields ff
    WHERE ff.form_id = v_form_id
      AND (ff.field_type = 'checkbox_with_text' OR ff.is_billable_items_field = 1)
    ORDER BY ff.is_billable_items_field DESC, ff.id ASC
    LIMIT 1;

    IF v_field_id IS NOT NULL THEN
        SELECT ff2.id INTO v_fallback_field_id
        FROM form_fields ff2
        INNER JOIN form_fields ff1 ON ff1.id = v_field_id
        WHERE ff2.form_id = v_form_id
          AND ff2.field_key = ff1.field_key
          AND ff2.id <> ff1.id
        ORDER BY ff2.id ASC
        LIMIT 1;

        IF v_opt_json IS NOT NULL AND JSON_VALID(v_opt_json) THEN
            SET v_opt_len = JSON_LENGTH(v_opt_json);
            SET v_opt_idx = 0;
            WHILE v_opt_idx < v_opt_len DO
                SET v_opt_label = LOWER(TRIM(COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(v_opt_json, CONCAT('$[', v_opt_idx, '].label'))),
                    ''
                )));
                IF v_opt_label = 'cherry' THEN
                    SET v_opt_json = JSON_SET(
                        v_opt_json,
                        CONCAT('$[', v_opt_idx, '].pricing_mode'), 'paid',
                        CONCAT('$[', v_opt_idx, '].unit_price'), 350,
                        CONCAT('$[', v_opt_idx, '].govt_share_pct'), NULL,
                        CONCAT('$[', v_opt_idx, '].unit_label'), 'Trees'
                    );
                END IF;
                SET v_opt_idx = v_opt_idx + 1;
            END WHILE;
        END IF;

        UPDATE form_fields
        SET
            is_billable_items_field = 1,
            option_configs_json = COALESCE(v_opt_json, option_configs_json),
            updated_at = NOW()
        WHERE id = v_field_id;
    END IF;

    -- 3. Update existing UNPAID application_items on Scheme #44 (Cherry -> Rs. 350, inherit template share)
    UPDATE application_items ai
    INNER JOIN applications a ON a.id = ai.application_id
    SET
        ai.unit_label = CASE
            WHEN ai.unit_label IS NULL OR TRIM(ai.unit_label) = '' OR LOWER(TRIM(ai.unit_label)) = 'units'
            THEN 'Trees'
            ELSE ai.unit_label
        END,
        ai.is_free = CASE WHEN LOWER(TRIM(ai.item_label)) = 'cherry' THEN 0 ELSE 1 END,
        ai.unit_price = CASE WHEN LOWER(TRIM(ai.item_label)) = 'cherry' THEN v_cherry_price ELSE 0.00 END,
        ai.inherits_template_share = CASE WHEN LOWER(TRIM(ai.item_label)) = 'cherry' THEN 1 ELSE 0 END,
        ai.govt_share_pct = CASE WHEN LOWER(TRIM(ai.item_label)) = 'cherry' THEN v_govt_pct ELSE 100.00 END,
        ai.farmer_share_pct = CASE WHEN LOWER(TRIM(ai.item_label)) = 'cherry' THEN v_farmer_pct ELSE 0.00 END,
        ai.gross_amount = CASE
            WHEN LOWER(TRIM(ai.item_label)) = 'cherry'
            THEN CEIL(COALESCE(ai.approved_qty, ai.demanded_qty) * v_cherry_price)
            ELSE 0.00
        END,
        ai.govt_share_amount = CASE
            WHEN LOWER(TRIM(ai.item_label)) = 'cherry'
            THEN CEIL(CEIL(COALESCE(ai.approved_qty, ai.demanded_qty) * v_cherry_price) * (v_govt_pct / 100))
            ELSE 0.00
        END,
        ai.farmer_payable_amount = CASE
            WHEN LOWER(TRIM(ai.item_label)) = 'cherry'
            THEN CEIL(CEIL(COALESCE(ai.approved_qty, ai.demanded_qty) * v_cherry_price) * (v_farmer_pct / 100))
            ELSE 0.00
        END,
        ai.updated_at = NOW()
    WHERE a.scheme_id = v_scheme_id
      AND a.payment_verified_at IS NULL
      AND a.gbpay_transaction_id IS NULL
      AND a.challan_paid_at IS NULL
      AND COALESCE(a.distributed_quantity, 0) <= 0
      AND COALESCE(a.current_stage, a.status, '') NOT IN (
          'rejected', 'dormant', 'payment_verified',
          'stock_allocated', 'partially_distributed', 'fully_distributed',
          'completed', 'disbursed'
      )
      AND NOT EXISTS (
          SELECT 1 FROM bills b WHERE b.application_id = a.id AND b.status = 'paid'
      );

    -- 4. Backfill missing application_items from legacy application_field_values
    IF v_field_id IS NOT NULL THEN
        SET v_done = 0;
        OPEN v_cur_apps;

        read_loop: LOOP
            FETCH v_cur_apps INTO v_app_id, v_stage, v_status, v_raw_val;
            IF v_done = 1 THEN
                LEAVE read_loop;
            END IF;

            IF v_raw_val IS NULL OR TRIM(v_raw_val) = '' THEN
                ITERATE read_loop;
            END IF;

            SET v_is_approved = CASE
                WHEN v_stage IN ('approved', 'challan_issued', 'eligible_beneficiary', 'directorate_approved')
                     OR (v_stage = '' AND v_status = 'directorate_approved')
                THEN 1 ELSE 0
            END;

            SET v_rest = TRIM(v_raw_val);
            SET v_sep = IF(LOCATE(';', v_rest) > 0, ';', ',');
            SET v_total_demanded = 0.00;
            SET v_total_approved = 0.00;

            seg_loop: WHILE LENGTH(v_rest) > 0 DO
                SET v_pos = LOCATE(v_sep, v_rest);
                IF v_pos > 0 THEN
                    SET v_part = TRIM(SUBSTRING(v_rest, 1, v_pos - 1));
                    SET v_rest = TRIM(SUBSTRING(v_rest, v_pos + 1));
                ELSE
                    SET v_part = TRIM(v_rest);
                    SET v_rest = '';
                END IF;

                IF v_part = '' OR LOCATE(':', v_part) = 0 THEN
                    ITERATE seg_loop;
                END IF;

                SET v_colon = LOCATE(':', v_part);
                SET v_label = TRIM(SUBSTRING(v_part, 1, v_colon - 1));
                SET v_qty_raw = TRIM(SUBSTRING(v_part, v_colon + 1));

                IF v_label = '' OR LOWER(v_qty_raw) IN ('', 'nil', 'none', 'null', 'n/a', '-') THEN
                    ITERATE seg_loop;
                END IF;

                -- Translate Persian/Urdu/Arabic numerals to ASCII digits
                SET v_qty_norm = v_qty_raw;
                SET v_qty_norm = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_qty_norm, '۰', '0'), '۱', '1'), '۲', '2'), '۳', '3'), '۴', '4');
                SET v_qty_norm = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_qty_norm, '۵', '5'), '۶', '6'), '۷', '7'), '۸', '8'), '۹', '9');
                SET v_qty_norm = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_qty_norm, '٠', '0'), '١', '1'), '٢', '2'), '٣', '3'), '٤', '4');
                SET v_qty_norm = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_qty_norm, '٥', '5'), '٦', '6'), '٧', '7'), '٨', '8'), '٩', '9');

                -- Extract first numeric token
                SET v_i = 1;
                SET v_len = CHAR_LENGTH(v_qty_norm);
                SET v_num_buf = '';
                SET v_seen_dot = 0;
                SET v_started_num = 0;

                num_loop: WHILE v_i <= v_len DO
                    SET v_ch = SUBSTRING(v_qty_norm, v_i, 1);
                    IF v_ch BETWEEN '0' AND '9' THEN
                        SET v_num_buf = CONCAT(v_num_buf, v_ch);
                        SET v_started_num = 1;
                    ELSEIF v_ch = '.' AND v_started_num = 1 AND v_seen_dot = 0 THEN
                        SET v_num_buf = CONCAT(v_num_buf, '.');
                        SET v_seen_dot = 1;
                    ELSEIF v_started_num = 1 THEN
                        LEAVE num_loop;
                    END IF;
                    SET v_i = v_i + 1;
                END WHILE;

                IF v_num_buf <> '' THEN
                    SET v_qty_val = CAST(v_num_buf AS DECIMAL(12,2));
                    IF v_qty_val <= 0 THEN
                        ITERATE seg_loop;
                    END IF;
                ELSE
                    -- Non-numeric descriptive choice (e.g. "Plant"): record row with 0.00 for DD review
                    SET v_qty_val = 0.00;
                END IF;

                SET v_item_key = LOWER(REPLACE(TRIM(v_label), ' ', '_'));
                SET v_is_free = IF(LOWER(TRIM(v_label)) = 'cherry', 0, 1);
                SET v_unit_price = IF(v_is_free = 0, v_cherry_price, 0.00);
                SET v_inherits = IF(v_is_free = 0, 1, 0);
                SET v_it_govt = IF(v_is_free = 0, v_govt_pct, 100.00);
                SET v_it_farmer = IF(v_is_free = 0, v_farmer_pct, 0.00);

                SET v_existing_item_id = (
                    SELECT id
                    FROM application_items
                    WHERE application_id = v_app_id AND item_key = v_item_key
                    LIMIT 1
                );

                IF v_existing_item_id IS NOT NULL THEN
                    UPDATE application_items
                    SET
                        demanded_qty = demanded_qty + v_qty_val,
                        approved_qty = IF(v_is_approved = 1, COALESCE(approved_qty, 0) + v_qty_val, approved_qty),
                        gross_amount = IF(v_is_free = 0, CEIL(COALESCE(approved_qty, demanded_qty) * v_unit_price), 0.00),
                        govt_share_amount = IF(v_is_free = 0, CEIL(gross_amount * (v_it_govt / 100)), 0.00),
                        farmer_payable_amount = IF(v_is_free = 0, CEIL(gross_amount * (v_it_farmer / 100)), 0.00),
                        updated_at = NOW()
                    WHERE id = v_existing_item_id;
                ELSE
                    SET v_gross = IF(v_is_free = 0, CEIL(v_qty_val * v_unit_price), 0.00);
                    SET v_govt_amt = IF(v_is_free = 0, CEIL(v_gross * (v_it_govt / 100)), 0.00);
                    SET v_farmer_amt = IF(v_is_free = 0, CEIL(v_gross * (v_it_farmer / 100)), 0.00);

                    INSERT INTO application_items (
                        application_id, form_field_id, item_key, item_label, item_label_ur,
                        unit_label, is_free, unit_price, govt_share_pct, inherits_template_share,
                        farmer_share_pct, demanded_qty, approved_qty, disbursed_qty,
                        gross_amount, govt_share_amount, farmer_payable_amount,
                        created_at, updated_at
                    )
                    VALUES (
                        v_app_id, v_field_id, v_item_key, v_label, NULL,
                        'Trees', v_is_free, v_unit_price, v_it_govt, v_inherits,
                        v_it_farmer, v_qty_val, IF(v_is_approved = 1, v_qty_val, NULL), 0.00,
                        v_gross, v_govt_amt, v_farmer_amt,
                        NOW(), NOW()
                    );
                END IF;

                SET v_total_demanded = v_total_demanded + v_qty_val;
                IF v_is_approved = 1 THEN
                    SET v_total_approved = v_total_approved + v_qty_val;
                END IF;
            END WHILE;

            UPDATE applications
            SET
                demanded_quantity = IF(v_total_demanded > 0, v_total_demanded, demanded_quantity),
                approved_quantity = IF(v_is_approved = 1, v_total_approved, approved_quantity),
                updated_at = NOW()
            WHERE id = v_app_id;
        END LOOP;

        CLOSE v_cur_apps;
    END IF;

    -- 5. Sync approved_quantity from items on all unpaid itemized approved apps
    UPDATE applications a
    INNER JOIN (
        SELECT application_id, SUM(COALESCE(approved_qty, demanded_qty)) AS sum_approved
        FROM application_items
        GROUP BY application_id
    ) agg ON agg.application_id = a.id
    SET a.approved_quantity = agg.sum_approved
    WHERE a.scheme_id = v_scheme_id
      AND COALESCE(a.current_stage, a.status, '') IN ('approved', 'challan_issued', 'eligible_beneficiary', 'directorate_approved')
      AND a.payment_verified_at IS NULL
      AND a.challan_paid_at IS NULL
      AND COALESCE(a.distributed_quantity, 0) <= 0;

    -- 6. Regenerate/update challan_amount & pending bills for approved unpaid apps with paid items (payable > 0)
    UPDATE applications a
    INNER JOIN (
        SELECT
            application_id,
            SUM(CEIL(CEIL(COALESCE(approved_qty, demanded_qty) * unit_price) * (farmer_share_pct / 100))) AS payable_total
        FROM application_items
        WHERE is_free = 0
        GROUP BY application_id
        HAVING payable_total > 0
    ) p ON p.application_id = a.id
    SET
        a.current_stage = 'challan_issued',
        a.status = 'directorate_approved',
        a.challan_amount = p.payable_total,
        a.challan_no = COALESCE(NULLIF(a.challan_no, ''), CONCAT('CHL-', YEAR(NOW()), '-', LPAD(a.id, 6, '0'))),
        a.challan_issued_at = COALESCE(a.challan_issued_at, NOW()),
        a.challan_due_date = COALESCE(a.challan_due_date, DATE_ADD(CURDATE(), INTERVAL v_validity_days DAY)),
        a.updated_at = NOW()
    WHERE a.scheme_id = v_scheme_id
      AND COALESCE(a.current_stage, a.status, '') IN ('approved', 'challan_issued', 'eligible_beneficiary', 'directorate_approved')
      AND a.payment_verified_at IS NULL
      AND a.gbpay_transaction_id IS NULL
      AND a.challan_paid_at IS NULL
      AND COALESCE(a.distributed_quantity, 0) <= 0
      AND NOT EXISTS (
          SELECT 1 FROM bills b WHERE b.application_id = a.id AND b.status = 'paid'
      );

    -- Sync pending bills amounts (preserving existing 20-digit PSIDs)
    UPDATE bills b
    INNER JOIN applications a ON a.id = b.application_id
    SET
        b.bill_amount = a.challan_amount,
        b.updated_at = NOW()
    WHERE a.scheme_id = v_scheme_id
      AND b.status = 'pending'
      AND a.challan_amount > 0
      AND a.current_stage = 'challan_issued';

    -- 7. Cancel pending bills & promote to eligible_beneficiary for approved unpaid apps with Rs. 0 payable (free-only)
    UPDATE bills b
    INNER JOIN applications a ON a.id = b.application_id
    LEFT JOIN (
        SELECT
            application_id,
            SUM(CEIL(CEIL(COALESCE(approved_qty, demanded_qty) * unit_price) * (farmer_share_pct / 100))) AS payable_total
        FROM application_items
        GROUP BY application_id
    ) p ON p.application_id = a.id
    SET
        b.status = 'cancelled',
        b.updated_at = NOW()
    WHERE a.scheme_id = v_scheme_id
      AND b.status = 'pending'
      AND COALESCE(p.payable_total, 0) <= 0
      AND a.payment_verified_at IS NULL
      AND a.gbpay_transaction_id IS NULL
      AND a.challan_paid_at IS NULL;

    UPDATE applications a
    INNER JOIN (
        SELECT
            application_id,
            SUM(CEIL(CEIL(COALESCE(approved_qty, demanded_qty) * unit_price) * (farmer_share_pct / 100))) AS payable_total
        FROM application_items
        GROUP BY application_id
    ) p ON p.application_id = a.id
    SET
        a.current_stage = 'eligible_beneficiary',
        a.status = 'directorate_approved',
        a.challan_no = NULL,
        a.challan_amount = NULL,
        a.challan_due_date = NULL,
        a.challan_issued_at = NULL,
        a.psid = NULL,
        a.stage_updated_at = NOW(),
        a.lock_version = COALESCE(a.lock_version, 1) + 1,
        a.updated_at = NOW()
    WHERE a.scheme_id = v_scheme_id
      AND COALESCE(p.payable_total, 0) <= 0
      AND (a.current_stage = 'challan_issued' OR a.challan_no IS NOT NULL OR a.psid IS NOT NULL)
      AND COALESCE(a.current_stage, a.status, '') IN ('approved', 'challan_issued', 'directorate_approved')
      AND a.payment_verified_at IS NULL
      AND a.gbpay_transaction_id IS NULL
      AND a.challan_paid_at IS NULL
      AND COALESCE(a.distributed_quantity, 0) <= 0;

END $$

DELIMITER ;

-- Execute sync
START TRANSACTION;
CALL `sp_sync_scheme_44_paid_items`();
COMMIT;

DROP PROCEDURE IF EXISTS `sp_sync_scheme_44_paid_items`;

-- Verification summary queries (safe read-only)
SELECT current_stage, COUNT(*) AS applications_count, COALESCE(SUM(challan_amount), 0) AS total_challan_amount
FROM applications
WHERE scheme_id = 44
GROUP BY current_stage;

SELECT b.status AS bill_status, COUNT(*) AS bills_count, COALESCE(SUM(b.bill_amount), 0) AS total_bill_amount
FROM bills b
INNER JOIN applications a ON a.id = b.application_id
WHERE a.scheme_id = 44
GROUP BY b.status;

SELECT a.id, a.application_no, a.current_stage, a.demanded_quantity, a.approved_quantity, a.challan_amount, a.psid
FROM applications a
WHERE a.scheme_id = 44
  AND a.current_stage IN ('challan_issued', 'eligible_beneficiary')
ORDER BY a.id;
