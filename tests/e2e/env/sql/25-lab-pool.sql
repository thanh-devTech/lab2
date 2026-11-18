-- Tenancy pool (design §3.4, D172). SIXTEEN labs, baked into the baseline so they
-- survive every reset.sh.
--
-- Why a fixed pool and not a lab created per test (the R7/D161 fix):
-- a per-test lab vanishes on the next reset, CI_Base_Lab::get_user_lab_ids()
-- then hits its empty branch and PERMANENTLY delete_user_meta('_lab_ids'),
-- which silently widens the persona to every active lab. Tests keep passing
-- because they see MORE data. Pool labs are in the baseline, so that branch is
-- never reached.
--
-- Why exactly 3 characters (D163): tr_get_new_assen() prefixes the accession
-- with lab_dba_init and CI_Base_Lab::replace_assen_by_lab() hardcodes
-- substr($assen, 3) (inc/ci_base_lab.php:282); replace_assen_by_reflab()
-- hardcodes /^[a-zA-Z]{3}/ (:300). An 8-char prefix produced
-- 'm9q2k7x32k7x31234567' and ERROR 1406 into the smoke tables.
--
-- Applied by seed.sh, NOT by docker-entrypoint-initdb.d: that directory only
-- fires on an empty datadir, so a warm `./up.sh` would skip it entirely (the
-- D153 failure mode).

CREATE TEMPORARY TABLE e2e_lab_pool (init CHAR(3) PRIMARY KEY);
-- Widened 8 -> 16 for persona wave 2 (D262). One lab per SPEC FILE is the
-- isolation unit (D241), so the pool must be at least as large as the number of
-- spec files. At 8 labs a 9th spec silently fell back to LAA and shared a lab
-- with ord.spec.ts, which is exactly the collision class that produced the
-- S-REV-02/08 flakes.
INSERT INTO e2e_lab_pool (init) VALUES
  ('LAA'),('LAB'),('LAC'),('LAD'),('LAE'),('LAF'),('LAG'),('LAH'),
  ('LAI'),('LAJ'),('LAK'),('LAL'),('LAM'),('LAN'),('LAO'),('LAP');

INSERT INTO ci_labs (
  lab_name, lab_dba, lab_dba_init, lab_address, lab_city, lab_state, lab_zip,
  lab_phone, lab_email, lab_npi, lab_tax_id, lab_clia, lab_is_ref, lab_status,
  lab_start_date, lab_email_notify, lab_day_sent, lab_day_notify,
  lab_results_footer, freshbook_client_id, lab_cgx_cost
)
SELECT
  CONCAT('E2E Pool Lab ', p.init), CONCAT('E2E Pool Lab ', p.init), p.init,
  '1 Test Way', 'Testville', 'IL', '00000',
  '000-000-0000', CONCAT('e2e-', LOWER(p.init), '@example.invalid'),
  CONCAT('E2ENPI', p.init), CONCAT('E2ETAX', p.init), CONCAT('E2ECLI', p.init),
  0, 1,
  CURDATE(), '', '1970-01-01', '1970-01-01 00:00:00',
  '', 0, '0'
FROM e2e_lab_pool p
WHERE NOT EXISTS (SELECT 1 FROM ci_labs l WHERE l.lab_dba_init = p.init);

DROP TEMPORARY TABLE e2e_lab_pool;
