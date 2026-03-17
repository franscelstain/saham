-- NON-NORMATIVE SQL ARTIFACT
-- REVIEWER RULE: semantic changes must be introduced in normative markdown first.
-- Dokumen owner semantik tetap berada pada file markdown normatif terkait.
-- Jika ada konflik, file normatif markdown selalu menang dan SQL ini harus disesuaikan.

-- Historical / operational migration artifact for watchlist database changes.
-- This file does not override active normative documents.
-- If migration history differs from active normative ownership,
-- the active normative documents remain authoritative.

-- MIGRATIONS.sql
-- Kumpulan ALTER untuk schema watchlist global (MariaDB 10.4).
-- Jalankan hanya jika database sudah terlanjur dibuat dari versi DDL yang lebih lama.

-- 1) Tambah run-level metrics ke PLAN runs (legacy bootstrap)
ALTER TABLE watchlist_plan_runs
  ADD COLUMN run_metrics_json LONGTEXT NOT NULL
  AFTER fail_code;

-- 2) Tambah FK fail_code agar parity doc <-> DDL konsisten
ALTER TABLE watchlist_plan_runs
  ADD CONSTRAINT FK_plan_fail_code
  FOREIGN KEY (fail_code) REFERENCES watchlist_fail_codes(fail_code);

ALTER TABLE watchlist_confirm_checks
  ADD CONSTRAINT FK_confirm_fail_code
  FOREIGN KEY (fail_code) REFERENCES watchlist_fail_codes(fail_code);

-- 3) Ganti trigger UPDATE plan_runs lama dengan guard supersede satu arah
DROP TRIGGER IF EXISTS trg_wpr_no_update;

DELIMITER //
CREATE TRIGGER trg_wpr_guard_update
BEFORE UPDATE ON watchlist_plan_runs
FOR EACH ROW
BEGIN
  IF NOT (
    OLD.is_active = 'Yes' AND
    NEW.is_active = 'No' AND
    OLD.policy_code = NEW.policy_code AND
    OLD.policy_version = NEW.policy_version AND
    OLD.asof_eod_date = NEW.asof_eod_date AND
    OLD.plan_trade_date = NEW.plan_trade_date AND
    OLD.param_set_id = NEW.param_set_id AND
    OLD.run_status = NEW.run_status AND
    OLD.data_batch_hash = NEW.data_batch_hash AND
    OLD.hash_count = NEW.hash_count AND
    OLD.missing_required_count = NEW.missing_required_count AND
    OLD.processed_count = NEW.processed_count AND
    OLD.eligible_count = NEW.eligible_count AND
    ((OLD.supersedes_plan_run_id <=> NEW.supersedes_plan_run_id)) AND
    ((OLD.fail_code <=> NEW.fail_code)) AND
    OLD.run_metrics_json = NEW.run_metrics_json AND
    OLD.created_at = NEW.created_at
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_plan_runs only allows controlled is_active Yes->No supersede update';
  END IF;
END//
DELIMITER ;


-- 4) Sinkronkan watchlist_param_sets dengan kontrak normatif paramset aktif
ALTER TABLE watchlist_param_sets
  ADD COLUMN schema_version VARCHAR(64) NOT NULL DEFAULT 'PARAMSET_JSON' AFTER policy_version,
  ADD COLUMN hash_contract LONGTEXT NOT NULL AFTER schema_version,
  ADD COLUMN provenance_json LONGTEXT NOT NULL AFTER hash_contract;

ALTER TABLE watchlist_param_sets
  ADD INDEX IDX_param_policy_version (policy_code, policy_version, schema_version);
