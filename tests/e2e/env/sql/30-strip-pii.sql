-- PHI/PII strip (design §3.5, control 2).
--
-- wp-content/default.sql carries zero ci_batches rows (D006) but it is NOT
-- person-free: measured on a freshly booted stack, ci_phlebotomist_logs 87,
-- wp_signups 12, wp_wp_eStore_tbl 11, wp_wp_eStore_sales_tbl 7,
-- wp_wp_eStore_customer_tbl 7, ci_lab_reps 1, wp_comments 1,
-- wp_registration_log 1, plus one pre-existing ci_clinic/ci_doc pair.
--
-- Run from seed.sh, NOT from docker-entrypoint-initdb.d (D153): that directory
-- fires only on an empty datadir, so `./up.sh` on a warm volume skipped the
-- strip entirely and the zero-real-rows invariant was unenforced on the common
-- developer path.
--
-- ORDER MATTERS (design §3.4/R13): this truncates ci_lab_reps, ci_doc,
-- ci_clinic, ci_collector and ci_patients, and seed.php then recreates the
-- synthetic replacements. Running the strip after seed.php would delete them.
--
-- The list is matched against information_schema rather than issued as literal
-- TRUNCATEs, for two reasons: a table that is absent in one plugin version and
-- present in another must not abort the seed (measured: ci_2_messages does not
-- exist in this snapshot), and the `wp_<N>_` prefix means the per-subsite
-- copies (wp_2_comments, ...) must be caught by pattern, not by name.
--
-- wp_users is deliberately NOT stripped: it holds the admin account the whole
-- harness authenticates as. safety-gate.sh classifies it instead.

DROP PROCEDURE IF EXISTS e2e_strip_pii;
DELIMITER $$
CREATE PROCEDURE e2e_strip_pii()
BEGIN
  DECLARE fin INT DEFAULT 0;
  DECLARE tname VARCHAR(255);
  DECLARE cur CURSOR FOR
    SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
       -- ci_* tables are unprefixed (the app ignores $wpdb->prefix, D087);
       -- core tables carry `wp_` or, per subsite, `wp_<N>_`.
       AND TABLE_NAME REGEXP CONCAT(
             '^(wp_([0-9]+_)?)?(',
             'ci_phlebotomist_logs|ci_lab_reps|ci_messages|ci_insurances|',
             'ci_patients|ci_doc|ci_clinic|ci_collector|',
             'signups|registration_log|comments|commentmeta|',
             'wp_eStore_tbl|wp_eStore_sales_tbl|wp_eStore_customer_tbl',
             ')$');
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET fin = 1;
  OPEN cur;
  strip: LOOP
    FETCH cur INTO tname;
    IF fin THEN LEAVE strip; END IF;
    SET @s = CONCAT('TRUNCATE TABLE `', tname, '`');
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;
CALL e2e_strip_pii();
DROP PROCEDURE e2e_strip_pii;
