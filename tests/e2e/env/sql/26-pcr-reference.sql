-- PCR reference data for the E2E suite (D238).
--
-- WHY THIS FILE EXISTS. `ci_test_groups` and `ci_test_panels` carry many NOT
-- NULL columns with no DEFAULT, so a plain INSERT naming only the interesting
-- columns fails. Agents worked around that by CLONING a real row inside their
-- spec (CREATE TEMPORARY TABLE … LIKE …; INSERT … SELECT …; UPDATE …), which
-- put reference-data creation in the test body -- the wrong tier under D085,
-- and duplicated per spec. The clone trick is right; its home is here, where it
-- runs once and lands inside baseline.sql, so it survives every reset.sh and no
-- test writes reference data at runtime.
--
-- Idempotent: every insert is guarded by NOT EXISTS on the natural key.
-- Runs from seed.sh AFTER sql/30-strip-pii.sql (which does not touch these
-- catalog tables) and alongside sql/25-lab-pool.sql.

-- ---------------------------------------------------------------- test group
-- Cloned from a live pcr group so every NOT NULL no-default column is filled,
-- then overwritten with the columns the state-reporting scenarios need:
--   tg_show_req=1              orderable on the pcr requisition form
--   tg_manual_edit=1           the saver creates the report row eagerly
--   tg_auto_approve_pos_neg=1  a POS/NEG result auto-approves to estatus='C'
--   tg_is_covid=0              NOT the covid group; that one is seed reference
--                              data ('nCoV-2019') and is left alone
CREATE TEMPORARY TABLE e2e_tg LIKE ci_test_groups;
INSERT INTO e2e_tg SELECT * FROM ci_test_groups WHERE tg_type='pcr' ORDER BY tg_ID LIMIT 1;
UPDATE e2e_tg
   SET tg_ID = 0,
       tg_name = 'E2E STA PCR Panel',
       tg_short_name = 'E2ESTA',
       tg_label_name = 'ES',
       tg_type = 'pcr',
       tg_show_req = 1,
       tg_is_covid = 0,
       tg_parent = 0,
       tg_manual_edit = 1,
       tg_auto_approve_pos_neg = 1,
       tg_auto_approve_neg = 0;
INSERT INTO ci_test_groups SELECT * FROM e2e_tg
 WHERE NOT EXISTS (SELECT 1 FROM ci_test_groups WHERE tg_name='E2E STA PCR Panel');
DROP TEMPORARY TABLE e2e_tg;

-- ---------------------------------------------------------------- test panel
-- te_in_req=1 is the other half of the orderability precondition.
CREATE TEMPORARY TABLE e2e_tp LIKE ci_test_panels;
INSERT INTO e2e_tp SELECT * FROM ci_test_panels ORDER BY te_ID LIMIT 1;
UPDATE e2e_tp
   SET te_ID = 0,
       te_name = 'E2E STA Covid Analyte',
       te_abrev = 'E2ESTA1',
       te_abrev2 = '',
       te_type = 'pcr',
       te_in_req = 1,
       te_is_control = 0,
       te_inconclusive_ID = 0,
       te_group_id = (SELECT tg_ID FROM ci_test_groups WHERE tg_name='E2E STA PCR Panel');
INSERT INTO ci_test_panels SELECT * FROM e2e_tp
 WHERE NOT EXISTS (SELECT 1 FROM ci_test_panels WHERE te_name='E2E STA Covid Analyte');
DROP TEMPORARY TABLE e2e_tp;
