-- 05_DB_DDL_MARIADB.sql
-- DDL lengkap tabel global Watchlist (MariaDB 10.4)
-- Catatan: ALTER yang pernah dibahas sudah diinkorporasi langsung ke CREATE TABLE ini.

-- 1) watchlist_param_sets
CREATE TABLE IF NOT EXISTS watchlist_param_sets (
  param_set_id BIGINT NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  policy_version VARCHAR(64) NOT NULL,
  status ENUM('DRAFT','ACTIVE','DEPRECATED') NOT NULL DEFAULT 'DRAFT',
  params_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (param_set_id),
  KEY IDX_param_policy_status (policy_code, status, updated_at)
) ENGINE=InnoDB;

-- 2) watchlist_plan_runs
CREATE TABLE IF NOT EXISTS watchlist_plan_runs (
  plan_run_id BIGINT NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  policy_version VARCHAR(64) NOT NULL,
  asof_eod_date DATE NOT NULL,
  plan_trade_date DATE NOT NULL,
  param_set_id BIGINT NOT NULL,
  run_status ENUM('OK','NO_TRADE','FAILED') NOT NULL,
  data_batch_hash CHAR(64) NOT NULL,
  hash_count INT NOT NULL DEFAULT 0,
  missing_required_count INT NOT NULL DEFAULT 0,
  processed_count INT NOT NULL DEFAULT 0,
  eligible_count INT NOT NULL DEFAULT 0,
  supersedes_plan_run_id BIGINT NULL,
  is_active ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
  fail_code VARCHAR(64) NULL,
  run_metrics_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (plan_run_id),
  KEY IDX_plan_active (policy_code, plan_trade_date, is_active),
  KEY IDX_plan_asof (policy_code, asof_eod_date),
  CONSTRAINT FK_plan_paramset FOREIGN KEY (param_set_id) REFERENCES watchlist_param_sets(param_set_id)
) ENGINE=InnoDB;

-- 3) watchlist_plan_items
CREATE TABLE IF NOT EXISTS watchlist_plan_items (
  plan_item_id BIGINT NOT NULL AUTO_INCREMENT,
  plan_run_id BIGINT NOT NULL,
  policy_code VARCHAR(16) NOT NULL,
  trade_date DATE NOT NULL,
  ticker_id BIGINT NOT NULL,
  ticker_code VARCHAR(16) NULL,
  group_semantic ENUM('TOP_PICKS','SECONDARY','WATCH_ONLY','AVOID') NOT NULL,
  display_bucket ENUM('SHOW','HIDE') NOT NULL,
  selection_reason_code VARCHAR(64) NOT NULL,
  score_total DECIMAL(10,6) NOT NULL DEFAULT 0,
  scores_json LONGTEXT NOT NULL,
  inputs_json LONGTEXT NOT NULL,
  plan_levels_json LONGTEXT NOT NULL,
  reason_codes_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (plan_item_id),
  KEY IDX_items_run_ticker (plan_run_id, ticker_id),
  KEY IDX_items_policy_date_bucket (policy_code, trade_date, display_bucket),
  CONSTRAINT FK_items_planrun FOREIGN KEY (plan_run_id) REFERENCES watchlist_plan_runs(plan_run_id)
) ENGINE=InnoDB;

-- 4) watchlist_confirm_checks
CREATE TABLE IF NOT EXISTS watchlist_confirm_checks (
  confirm_check_id BIGINT NOT NULL AUTO_INCREMENT,
  plan_run_id BIGINT NOT NULL,
  policy_code VARCHAR(16) NOT NULL,
  policy_version VARCHAR(64) NOT NULL,
  checked_at DATETIME NOT NULL,
  snapshot_age_sec INT NOT NULL DEFAULT 0,
  run_status ENUM('OK','FAILED') NOT NULL,
  fail_code VARCHAR(64) NULL,
  run_metrics_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (confirm_check_id),
  KEY IDX_confirm_plan_run (plan_run_id, checked_at),
  CONSTRAINT FK_confirm_planrun FOREIGN KEY (plan_run_id) REFERENCES watchlist_plan_runs(plan_run_id)
) ENGINE=InnoDB;

-- 5) watchlist_confirm_items
CREATE TABLE IF NOT EXISTS watchlist_confirm_items (
  confirm_item_id BIGINT NOT NULL AUTO_INCREMENT,
  confirm_check_id BIGINT NOT NULL,
  ticker_id BIGINT NOT NULL,
  label ENUM('CONFIRMED','NEUTRAL','CAUTION','DELAY') NOT NULL,
  runtime_json LONGTEXT NOT NULL,
  reason_codes_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (confirm_item_id),
  KEY IDX_confirm_items_check_ticker (confirm_check_id, ticker_id),
  CONSTRAINT FK_confirm_items_check FOREIGN KEY (confirm_check_id) REFERENCES watchlist_confirm_checks(confirm_check_id)
) ENGINE=InnoDB;


-- 6) watchlist_confirm_snapshots (manual intraday snapshot source for CONFIRM)
CREATE TABLE IF NOT EXISTS watchlist_confirm_snapshots (
  snapshot_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  trade_date DATE NOT NULL,
  captured_at DATETIME NOT NULL,
  inserted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source VARCHAR(32) NOT NULL DEFAULT 'manual',
  note TEXT NULL,
  snapshot_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (snapshot_id),
  KEY idx_snap_policy_trade_captured (policy_code, trade_date, captured_at),
  KEY idx_snap_trade_inserted (trade_date, inserted_at)
) ENGINE=InnoDB;

-- 7) watchlist_confirm_snapshot_items (manual intraday snapshot items)
CREATE TABLE IF NOT EXISTS watchlist_confirm_snapshot_items (
  snapshot_item_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_id       BIGINT UNSIGNED NOT NULL,

  ticker_code       VARCHAR(16) NOT NULL,
  ticker_id         BIGINT UNSIGNED NULL,

  last_price        INT UNSIGNED NOT NULL,
  chg_pct           DECIMAL(8,4) NOT NULL,
  volume_shares     BIGINT UNSIGNED NOT NULL,
  turnover_idr      BIGINT UNSIGNED NOT NULL,

  item_hash         CHAR(64) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (snapshot_item_id),
  UNIQUE KEY uq_snap_ticker (snapshot_id, ticker_code),
  KEY idx_item_snap (snapshot_id),
  KEY idx_item_ticker (ticker_code),

  CONSTRAINT fk_wcs_items_snapshot
    FOREIGN KEY (snapshot_id)
    REFERENCES watchlist_confirm_snapshots(snapshot_id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB;

DELIMITER //

CREATE TRIGGER trg_wcsi_no_update
BEFORE UPDATE ON watchlist_confirm_snapshot_items
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshot_items is append-only (UPDATE blocked)';
END//

CREATE TRIGGER trg_wcsi_no_delete
BEFORE DELETE ON watchlist_confirm_snapshot_items
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshot_items is append-only (DELETE blocked)';
END//

CREATE TRIGGER trg_wcs_no_update
BEFORE UPDATE ON watchlist_confirm_snapshots
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshots is append-only (UPDATE blocked)';
END//

CREATE TRIGGER trg_wcs_no_delete
BEFORE DELETE ON watchlist_confirm_snapshots
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshots is append-only (DELETE blocked)';
END//

DELIMITER ;

CREATE TABLE IF NOT EXISTS watchlist_fail_codes (
  fail_code VARCHAR(64) NOT NULL,
  scope ENUM('PLAN','CONFIRM','BOTH') NOT NULL,
  severity ENUM('INFO','WARN','ERROR') NOT NULL,
  description_id TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (fail_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS watchlist_reason_codes (
  policy_code VARCHAR(16) NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  scope ENUM('PLAN','CONFIRM') NOT NULL,
  severity ENUM('INFO','WARN','BLOCK') NOT NULL,
  short_id VARCHAR(32) NOT NULL,
  description_id TEXT NOT NULL,
  description_en TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (policy_code, reason_code)
) ENGINE=InnoDB;
