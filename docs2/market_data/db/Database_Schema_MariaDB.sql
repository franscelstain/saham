-- Market Data Platform (MariaDB)
-- Assumes external dependencies exist: tickers, market calendar.

CREATE TABLE IF NOT EXISTS eod_runs (
  run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date_requested DATE NOT NULL,
  trade_date_effective DATE NULL,
  status ENUM('SUCCESS','HELD','FAILED') NOT NULL,
  stage ENUM('INGEST_BARS','PUBLISH_BARS','COMPUTE_INDICATORS','BUILD_ELIGIBILITY','FINALIZE','HASH','SEAL') NOT NULL,
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
  KEY idx_runs_effective_status_sealed (trade_date_effective, status, sealed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_bars (
  trade_date DATE NOT NULL,
  ticker_id INT NOT NULL,

  `open`  DECIMAL(18,4) NOT NULL,
  `high`  DECIMAL(18,4) NOT NULL,
  `low`   DECIMAL(18,4) NOT NULL,
  `close` DECIMAL(18,4) NOT NULL,
  volume  BIGINT NOT NULL,
  adj_close DECIMAL(18,4) NULL,

  source VARCHAR(32) NOT NULL,
  ingested_at DATETIME NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,

  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_bars_ticker_date (ticker_id, trade_date),
  KEY idx_bars_run (run_id),
  CONSTRAINT fk_bars_run FOREIGN KEY (run_id) REFERENCES eod_runs(run_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_invalid_bars (
  invalid_bar_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trade_date DATE NOT NULL,
  ticker_id INT NULL,
  source VARCHAR(32) NOT NULL,
  provider_payload_ref VARCHAR(128) NULL,
  invalid_reason_code VARCHAR(64) NOT NULL,
  observed_open DECIMAL(18,4) NULL,
  observed_high DECIMAL(18,4) NULL,
  observed_low DECIMAL(18,4) NULL,
  observed_close DECIMAL(18,4) NULL,
  observed_volume BIGINT NULL,
  observed_adj_close DECIMAL(18,4) NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  recorded_at DATETIME NOT NULL,
  PRIMARY KEY (invalid_bar_id),
  KEY idx_invbars_date_ticker (trade_date, ticker_id),
  KEY idx_invbars_run (run_id),
  CONSTRAINT fk_invbars_run FOREIGN KEY (run_id) REFERENCES eod_runs(run_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_indicators (
  trade_date DATE NOT NULL,
  ticker_id INT NOT NULL,

  is_valid TINYINT NOT NULL,
  invalid_reason_code VARCHAR(64) NULL,

  indicator_set_version VARCHAR(32) NOT NULL,
  computed_at DATETIME NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,

  dv20_idr  DECIMAL(20,2) NULL,
  atr14_pct DECIMAL(10,4) NULL,
  vol_ratio DECIMAL(10,4) NULL,
  roc20     DECIMAL(10,4) NULL,
  hh20      DECIMAL(18,4) NULL,

  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_ind_ticker_date (ticker_id, trade_date),
  KEY idx_ind_run (run_id),
  KEY idx_ind_valid_date (is_valid, trade_date),
  CONSTRAINT fk_ind_run FOREIGN KEY (run_id) REFERENCES eod_runs(run_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_eligibility (
  trade_date DATE NOT NULL,
  ticker_id INT NOT NULL,

  eligible TINYINT NOT NULL,
  reason_code VARCHAR(64) NULL,

  asof_run_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,

  PRIMARY KEY (trade_date, ticker_id),
  KEY idx_el_date_eligible (trade_date, eligible),
  KEY idx_el_run (asof_run_id),
  CONSTRAINT fk_el_run FOREIGN KEY (asof_run_id) REFERENCES eod_runs(run_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_reason_codes (
  code VARCHAR(64) NOT NULL,
  category ENUM('RUN','BAR','INDICATOR','ELIGIBILITY','INTRADAY') NOT NULL,
  description VARCHAR(255) NOT NULL,
  severity ENUM('INFO','WARN','HARD') NOT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eod_run_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id BIGINT UNSIGNED NOT NULL,
  trade_date_requested DATE NOT NULL,
  event_time DATETIME NOT NULL,
  stage VARCHAR(32) NOT NULL,
  event_type VARCHAR(32) NOT NULL,
  severity ENUM('INFO','WARN','ERROR') NOT NULL,
  reason_code VARCHAR(64) NULL,
  message VARCHAR(255) NOT NULL,
  payload_json JSON NULL,
  PRIMARY KEY (event_id),
  KEY idx_run_events_run_time (run_id, event_time),
  KEY idx_run_events_date_stage (trade_date_requested, stage),
  CONSTRAINT fk_run_events_run FOREIGN KEY (run_id) REFERENCES eod_runs(run_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS intraday_snapshots (
  trade_date DATE NOT NULL,
  snapshot_slot VARCHAR(32) NOT NULL,
  ticker_id INT NOT NULL,
  captured_at DATETIME NOT NULL,

  last_price DECIMAL(18,4) NOT NULL,
  prev_close DECIMAL(18,4) NULL,
  chg_pct DECIMAL(10,4) NULL,
  volume BIGINT NULL,
  day_high DECIMAL(18,4) NULL,
  day_low DECIMAL(18,4) NULL,

  source VARCHAR(32) NOT NULL,
  http_status SMALLINT NULL,
  status ENUM('OK','PARTIAL','ERROR') NOT NULL,
  error_code VARCHAR(64) NULL,
  error_msg VARCHAR(255) NULL,

  PRIMARY KEY (trade_date, snapshot_slot, ticker_id),
  KEY idx_intra_captured (captured_at)
) ENGINE=InnoDB;