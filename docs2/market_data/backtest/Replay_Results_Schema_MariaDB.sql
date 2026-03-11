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

-- LOCKED SEMANTICS
-- 1. One row in md_replay_daily_metrics represents the replay evaluation result for one trade_date inside one replay execution.
-- 2. comparison_result meanings:
--    - MATCH: actual output matches expected golden outcome
--    - MISMATCH: actual output differs unexpectedly from expected outcome
--    - EXPECTED_DEGRADE: degraded-input scenario produced the expected degraded outcome
--    - UNEXPECTED: output class itself is not the expected one and requires investigation
-- 3. comparison_note is short structured explanation text for the replay result.
-- 4. artifact_changed_scope indicates which artifact layer changed, for example:
--    - bars_only
--    - indicators_only
--    - eligibility_only
--    - multi_artifact
--    - none
-- 5. config_identity stores the effective config snapshot identity used by the replayed logic.
-- 6. publication_version is optional but recommended when replay validates correction-aware publication behavior.
-- 7. mismatch_summary is a concise operator-facing explanation of the mismatch and must not replace detailed evidence elsewhere.

-- RECOMMENDED USAGE NOTES
-- 1. Replay verification should compare actual vs expected:
--    - terminal status
--    - effective trade date
--    - seal state
--    - content hashes
--    - artifact row counts
--    - reason-code counts
-- 2. md_replay_reason_code_counts is used to preserve row-level blocking/anomaly distribution without flattening it into one summary string.
-- 3. Replay storage is proof storage, not production publication state.