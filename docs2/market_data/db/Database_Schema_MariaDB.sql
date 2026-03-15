-- =========================================================
-- Market Data Platform (EOD) — Core MariaDB Schema
-- LOCKED DDL
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_reason_codes (
  code VARCHAR(64) NOT NULL,
  category VARCHAR(32) NOT NULL,
  description VARCHAR(255) NOT NULL,
  severity ENUM('INFO','WARN','HARD') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (code),
  KEY idx_reason_codes_category (category),
  KEY idx_reason_codes_active (is_active)
) ENGINE=InnoDB;

-- =========================================================
-- Canonical readable bars
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
  publication_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_eod_bars_ticker_date (ticker_id, trade_date),
  KEY idx_eod_bars_run (run_id),
  KEY idx_eod_bars_publication (publication_id)
) ENGINE=InnoDB;

-- LOCKED NOTE
-- eod_bars stores the current canonical readable row set for a given trade_date.
-- publication_id is mandatory publication context on every live current row,
-- but it is not part of the live-table primary key.
-- Historical auditability across corrections is provided through publication trail,
-- hash trail, correction evidence, and immutable snapshot tables below.

-- =========================================================
-- Invalid/rejected source-row evidence
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_invalid_bars (
  invalid_bar_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date DATE NOT NULL,
  ticker_id BIGINT UNSIGNED NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  source VARCHAR(32) NOT NULL,
  source_row_ref VARCHAR(255) NULL,
  open DECIMAL(20,4) NULL,
  high DECIMAL(20,4) NULL,
  low DECIMAL(20,4) NULL,
  close DECIMAL(20,4) NULL,
  volume BIGINT NULL,
  adj_close DECIMAL(20,4) NULL,
  invalid_reason_code VARCHAR(64) NOT NULL,
  invalid_note VARCHAR(255) NULL,
  loser_of_trade_date DATE NULL,
  loser_of_ticker_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (invalid_bar_id),
  KEY idx_invalid_bars_trade_date_ticker (trade_date, ticker_id),
  KEY idx_invalid_bars_run (run_id),
  KEY idx_invalid_bars_reason_code (invalid_reason_code),
  KEY idx_invalid_bars_source_row_ref (source_row_ref),
  KEY idx_invalid_bars_duplicate_loser (loser_of_trade_date, loser_of_ticker_id)
) ENGINE=InnoDB;

-- =========================================================
-- Indicator artifact
-- =========================================================

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
  publication_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_eod_indicators_ticker_date (ticker_id, trade_date),
  KEY idx_eod_indicators_run (run_id),
  KEY idx_eod_indicators_invalid_reason (invalid_reason_code),
  KEY idx_eod_indicators_publication (publication_id)
) ENGINE=InnoDB;

-- LOCKED NOTE
-- eod_indicators stores the current readable indicator row set.
-- publication_id is mandatory publication context on every live current row,
-- but live-table identity remains (trade_date, ticker_id).

-- =========================================================
-- Eligibility artifact
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_eligibility (
  trade_date DATE NOT NULL,
  ticker_id BIGINT UNSIGNED NOT NULL,
  eligible TINYINT(1) NOT NULL,
  reason_code VARCHAR(64) NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  publication_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_eod_eligibility_ticker_date (ticker_id, trade_date),
  KEY idx_eod_eligibility_run (run_id),
  KEY idx_eod_eligibility_reason (reason_code),
  KEY idx_eod_eligibility_publication (publication_id)
) ENGINE=InnoDB;

-- LOCKED NOTE
-- eod_eligibility stores the current readable eligibility row set.
-- publication_id is mandatory publication context on every live current row,
-- but live-table identity remains (trade_date, ticker_id).

-- =========================================================
-- Runs with separated state model
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_runs (
  run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date_requested DATE NOT NULL,
  trade_date_effective DATE NULL,

  lifecycle_state ENUM('PENDING','RUNNING','FINALIZING','COMPLETED','FAILED','CANCELLED') NOT NULL,
  terminal_status ENUM('SUCCESS','HELD','FAILED') NULL,
  quality_gate_state ENUM('PENDING','PASS','FAIL','BLOCKED') NOT NULL DEFAULT 'PENDING',
  publishability_state ENUM('NOT_READABLE','READABLE') NOT NULL DEFAULT 'NOT_READABLE',

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
  KEY idx_runs_requested_lifecycle (trade_date_requested, lifecycle_state),
  KEY idx_runs_requested_terminal (trade_date_requested, terminal_status),
  KEY idx_runs_effective_terminal (trade_date_effective, terminal_status),
  KEY idx_runs_effective_publishability (trade_date_effective, publishability_state),
  KEY idx_runs_gate_state (quality_gate_state),
  KEY idx_runs_stage (stage),
  KEY idx_runs_trade_date_current_pub (trade_date_effective, is_current_publication),
  KEY idx_runs_supersedes (supersedes_run_id)
) ENGINE=InnoDB;

-- LOCKED SEMANTICS
-- 1. lifecycle_state tracks execution progression.
-- 2. terminal_status tracks consumer-facing terminal outcome.
-- 3. quality_gate_state tracks gate evaluation.
-- 4. publishability_state tracks readability.
-- 5. These meanings must remain distinct; do not collapse them back into one overloaded status.
-- 6. terminal_status may remain NULL until finalization resolves outcome.
-- 7. publishability_state='READABLE' must never coexist with missing required seal/publication semantics.

-- =========================================================
-- Append-only run event trail
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_run_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id BIGINT UNSIGNED NOT NULL,
  trade_date_requested DATE NOT NULL,
  event_time DATETIME NOT NULL,
  stage VARCHAR(64) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  severity ENUM('INFO','WARN','ERROR') NOT NULL,
  reason_code VARCHAR(64) NULL,
  message VARCHAR(255) NULL,
  event_payload_json LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (event_id),
  KEY idx_run_events_run_time (run_id, event_time),
  KEY idx_run_events_trade_date_time (trade_date_requested, event_time),
  KEY idx_run_events_stage_time (stage, event_time),
  KEY idx_run_events_reason_code (reason_code),
  KEY idx_run_events_severity_time (severity, event_time)
) ENGINE=InnoDB;

-- =========================================================
-- Explicit publication table
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_publications (
  publication_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date DATE NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  publication_version INT UNSIGNED NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  supersedes_publication_id BIGINT UNSIGNED NULL,
  seal_state ENUM('SEALED','UNSEALED') NOT NULL DEFAULT 'SEALED',
  bars_batch_hash VARCHAR(64) NULL,
  indicators_batch_hash VARCHAR(64) NULL,
  eligibility_batch_hash VARCHAR(64) NULL,
  sealed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (publication_id),
  UNIQUE KEY uq_publication_trade_date_version (trade_date, publication_version),
  KEY idx_publication_trade_date_current (trade_date, is_current),
  KEY idx_publication_run (run_id),
  KEY idx_publication_supersedes (supersedes_publication_id),
  KEY idx_publication_trade_date_sealed (trade_date, seal_state, sealed_at)
) ENGINE=InnoDB;

-- IMPORTANT NOTE
-- MariaDB cannot enforce "only one row with is_current=1 per trade_date"
-- as a partial unique index in the same way some other databases can.
-- Therefore the single-current-publication invariant must be enforced by
-- application transaction discipline or locked publication-switch procedure flow.

-- =========================================================
-- Hardened current-publication pointer
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_current_publication_pointer (
  trade_date DATE NOT NULL,
  publication_id BIGINT UNSIGNED NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  publication_version INT UNSIGNED NOT NULL,
  sealed_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date),
  UNIQUE KEY uq_current_publication_pointer_publication (publication_id),
  KEY idx_current_publication_pointer_run (run_id),
  CONSTRAINT fk_current_publication_pointer_publication
    FOREIGN KEY (publication_id) REFERENCES eod_publications(publication_id)
) ENGINE=InnoDB;

-- LOCKED SEMANTICS
-- 1. This table is the hardened DB-facing pointer for "one current publication per trade_date".
-- 2. trade_date as PK guarantees at most one pointer row per trade_date.
-- 3. publication_id uniqueness guarantees one publication cannot be current for multiple trade dates.
-- 4. Consumer-readable current publication resolution must prefer this pointer table where implemented.
-- 5. eod_publications history and eod_runs state remain required supporting evidence; the pointer does not replace them.

-- =========================================================
-- Correction request table
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
-- Immutable publication-bound history tables
-- Production-grade default strategy
-- =========================================================

CREATE TABLE IF NOT EXISTS eod_bars_history (
  publication_id BIGINT UNSIGNED NOT NULL,
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
  PRIMARY KEY (publication_id, trade_date, ticker_id),
  KEY idx_bars_history_trade_date (trade_date),
  KEY idx_bars_history_ticker_date (ticker_id, trade_date),
  KEY idx_bars_history_run (run_id),
  CONSTRAINT fk_bars_history_publication
    FOREIGN KEY (publication_id) REFERENCES eod_publications(publication_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_indicators_history (
  publication_id BIGINT UNSIGNED NOT NULL,
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
  PRIMARY KEY (publication_id, trade_date, ticker_id),
  KEY idx_indicators_history_trade_date (trade_date),
  KEY idx_indicators_history_ticker_date (ticker_id, trade_date),
  KEY idx_indicators_history_run (run_id),
  CONSTRAINT fk_indicators_history_publication
    FOREIGN KEY (publication_id) REFERENCES eod_publications(publication_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_eligibility_history (
  publication_id BIGINT UNSIGNED NOT NULL,
  trade_date DATE NOT NULL,
  ticker_id BIGINT UNSIGNED NOT NULL,
  eligible TINYINT(1) NOT NULL,
  reason_code VARCHAR(64) NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (publication_id, trade_date, ticker_id),
  KEY idx_eligibility_history_trade_date (trade_date),
  KEY idx_eligibility_history_ticker_date (ticker_id, trade_date),
  KEY idx_eligibility_history_run (run_id),
  CONSTRAINT fk_eligibility_history_publication
    FOREIGN KEY (publication_id) REFERENCES eod_publications(publication_id)
) ENGINE=InnoDB;

-- LOCKED HISTORY NOTE
-- 1. Strategy A is the default production-grade row-history strategy.
-- 2. The *_history tables are immutable publication-bound snapshots.
-- 3. Each snapshot row set belongs to exactly one publication_id.
-- 4. Snapshot rows must be written only for a sealed publication.
-- 5. Snapshot rows must never be updated or deleted in normal operation.

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
  KEY idx_replay_daily_compare (replay_id, comparison_result),
  KEY idx_replay_daily_publication_version (replay_id, publication_version),
  KEY idx_replay_daily_config_identity (replay_id, config_identity)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS md_replay_reason_code_counts (
  replay_id BIGINT UNSIGNED NOT NULL,
  trade_date DATE NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  reason_count INT NOT NULL,
  PRIMARY KEY (replay_id, trade_date, reason_code),
  KEY idx_replay_reason_code (replay_id, reason_code)
) ENGINE=InnoDB;