CREATE TABLE IF NOT EXISTS eod_fetch_failures (
  trade_date DATE NOT NULL,
  ticker_id INT NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  source VARCHAR(32) NOT NULL,
  http_status SMALLINT NULL,
  error_code VARCHAR(64) NOT NULL,
  error_msg VARCHAR(255) NULL,
  attempts TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (trade_date, ticker_id, run_id),
  KEY idx_fetch_fail_run (run_id),
  CONSTRAINT fk_fetch_fail_run FOREIGN KEY (run_id) REFERENCES eod_runs(run_id)
) ENGINE=InnoDB;