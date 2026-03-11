CREATE TABLE IF NOT EXISTS md_replay_daily_metrics (
  replay_id BIGINT UNSIGNED NOT NULL,
  trade_date DATE NOT NULL,
  source VARCHAR(32) NOT NULL,
  status ENUM('SUCCESS','HELD','FAILED') NOT NULL,
  coverage_ratio DECIMAL(6,4) NULL,
  bars_rows_written INT NULL,
  indicators_rows_written INT NULL,
  eligible_count INT NULL,
  invalid_bar_count INT NULL,
  invalid_indicator_count INT NULL,
  bars_batch_hash VARCHAR(64) NULL,
  indicators_batch_hash VARCHAR(64) NULL,
  eligibility_batch_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (replay_id, trade_date)
) ENGINE=InnoDB;