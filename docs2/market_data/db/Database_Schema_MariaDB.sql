-- =========================================================
-- Core uniqueness constraints (LOCKED)
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_bars (
  trade_date DATE NOT NULL,
  ticker_id BIGINT UNSIGNED NOT NULL,
  open DECIMAL(20,4) NOT NULL,
  high DECIMAL(20,4) NOT NULL,
  low DECIMAL(20,4) NOT NULL,
  close DECIMAL(20,4) NOT NULL,
  volume BIGINT NOT NULL,
  adj_close DECIMAL(20,4) NULL,
  source VARCHAR(32) NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_eod_bars_ticker_date (ticker_id, trade_date),
  KEY idx_eod_bars_run (run_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_indicators (
  trade_date DATE NOT NULL,
  ticker_id BIGINT UNSIGNED NOT NULL,
  is_valid TINYINT(1) NOT NULL,
  invalid_reason_code VARCHAR(64) NULL,
  indicator_set_version VARCHAR(64) NOT NULL,
  dv20_idr DECIMAL(24,2) NULL,
  atr14_pct DECIMAL(20,10) NULL,
  vol_ratio DECIMAL(20,10) NULL,
  roc20 DECIMAL(20,10) NULL,
  hh20 DECIMAL(20,4) NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_eod_indicators_ticker_date (ticker_id, trade_date),
  KEY idx_eod_indicators_run (run_id),
  KEY idx_eod_indicators_invalid_reason (invalid_reason_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_eligibility (
  trade_date DATE NOT NULL,
  ticker_id BIGINT UNSIGNED NOT NULL,
  eligible TINYINT(1) NOT NULL,
  reason_code VARCHAR(64) NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_eod_eligibility_ticker_date (ticker_id, trade_date),
  KEY idx_eod_eligibility_run (run_id),
  KEY idx_eod_eligibility_reason (reason_code)
) ENGINE=InnoDB;

-- =========================================================
-- Runs with correction/publication semantics (LOCKED)
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_runs (
  run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date_requested DATE NOT NULL,
  trade_date_effective DATE NULL,
  status ENUM('SUCCESS','HELD','FAILED') NOT NULL,
  stage ENUM('INGEST_BARS','PUBLISH_BARS','COMPUTE_INDICATORS','BUILD_ELIGIBILITY','HASH','SEAL','FINALIZE') NOT NULL,
  source VARCHAR(32) NOT NULL,

  coverage_ratio DECIMAL(6,4) NULL,
  bars_rows_written INT NULL,
  indicators_rows_written INT NULL,
  eligibility_rows_written INT NULL,

  invalid_bar_count INT NULL,
  invalid_indicator_count INT NULL,
  hard_reject_count INT NULL,
  warning_count INT NULL,

  notes TEXT NULL,

  bars_batch_hash VARCHAR(64) NULL,
  indicators_batch_hash VARCHAR(64) NULL,
  eligibility_batch_hash VARCHAR(64) NULL,

  config_version VARCHAR(64) NULL,
  config_hash VARCHAR(64) NULL,
  config_snapshot_ref VARCHAR(255) NULL,

  supersedes_run_id BIGINT UNSIGNED NULL,
  publication_version INT UNSIGNED NULL,
  is_current_publication TINYINT(1) NOT NULL DEFAULT 0,

  sealed_at DATETIME NULL,
  sealed_by VARCHAR(64) NULL,
  seal_note VARCHAR(255) NULL,

  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,

  PRIMARY KEY (run_id),
  KEY idx_runs_requested_status_stage (trade_date_requested, status, stage),
  KEY idx_runs_effective (trade_date_effective),
  KEY idx_runs_effective_status_sealed (trade_date_effective, status, sealed_at),
  KEY idx_runs_trade_date_current_pub (trade_date_effective, is_current_publication),
  KEY idx_runs_supersedes (supersedes_run_id)
) ENGINE=InnoDB;

-- =========================================================
-- Optional explicit publication table (recommended)
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_publications (
  publication_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date DATE NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  publication_version INT UNSIGNED NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  supersedes_publication_id BIGINT UNSIGNED NULL,
  sealed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (publication_id),
  UNIQUE KEY uq_publication_trade_date_version (trade_date, publication_version),
  KEY idx_publication_trade_date_current (trade_date, is_current),
  KEY idx_publication_run (run_id),
  KEY idx_publication_supersedes (supersedes_publication_id)
) ENGINE=InnoDB;

-- IMPORTANT LOCKED NOTE:
-- MariaDB cannot express "only one row with is_current=1 per trade_date"
-- as a partial unique index in the same way some other databases can.
-- Therefore this invariant must be enforced by application transaction discipline
-- or a locked stored-procedure publication-switch flow.

-- =========================================================
-- Optional correction request table
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_dataset_corrections (
  correction_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date DATE NOT NULL,
  prior_run_id BIGINT UNSIGNED NULL,
  new_run_id BIGINT UNSIGNED NULL,
  correction_reason_code VARCHAR(64) NOT NULL,
  correction_reason_note TEXT NULL,
  status ENUM('REQUESTED','APPROVED','EXECUTING','RESEALED','PUBLISHED','REJECTED','CANCELLED') NOT NULL,
  requested_by VARCHAR(64) NOT NULL,
  requested_at DATETIME NOT NULL,
  approved_by VARCHAR(64) NULL,
  approved_at DATETIME NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (correction_id),
  KEY idx_corr_trade_date_status (trade_date, status),
  KEY idx_corr_prior_run (prior_run_id),
  KEY idx_corr_new_run (new_run_id)
) ENGINE=InnoDB;

-- =========================================================
-- Replay result storage
-- =========================================================

CREATE TABLE IF NOT EXISTS md_replay_daily_metrics (
  replay_id BIGINT UNSIGNED NOT NULL,
  trade_date DATE NOT NULL,
  trade_date_effective DATE NULL,
  source VARCHAR(32) NOT NULL,
  status ENUM('SUCCESS','HELD','FAILED') NOT NULL,
  comparison_result ENUM('MATCH','MISMATCH','EXPECTED_DEGRADE','UNEXPECTED') NOT NULL,
  comparison_note VARCHAR(255) NULL,
  artifact_changed_scope VARCHAR(64) NULL,
  config_identity VARCHAR(128) NULL,
  publication_version INT UNSIGNED NULL,
  coverage_ratio DECIMAL(6,4) NULL,
  bars_rows_written INT NULL,
  indicators_rows_written INT NULL,
  eligibility_rows_written INT NULL,
  eligible_count INT NULL,
  invalid_bar_count INT NULL,
  invalid_indicator_count INT NULL,
  warning_count INT NULL,
  hard_reject_count INT NULL,
  bars_batch_hash VARCHAR(64) NULL,
  indicators_batch_hash VARCHAR(64) NULL,
  eligibility_batch_hash VARCHAR(64) NULL,
  seal_state ENUM('SEALED','UNSEALED') NOT NULL,
  sealed_at DATETIME NULL,
  expected_status ENUM('SUCCESS','HELD','FAILED') NULL,
  expected_trade_date_effective DATE NULL,
  expected_seal_state ENUM('SEALED','UNSEALED') NULL,
  mismatch_summary VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (replay_id, trade_date),
  KEY idx_replay_daily_status (replay_id, status),
  KEY idx_replay_daily_effective (replay_id, trade_date_effective),
  KEY idx_replay_daily_compare (replay_id, comparison_result)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS md_replay_reason_code_counts (
  replay_id BIGINT UNSIGNED NOT NULL,
  trade_date DATE NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  reason_count INT NOT NULL,
  PRIMARY KEY (replay_id, trade_date, reason_code),
  KEY idx_replay_reason_code (replay_id, reason_code)
) ENGINE=InnoDB;