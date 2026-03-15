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

-- LOCKED SEMANTICS
-- 1. One trade_date may have multiple historical publications over time.
-- 2. Only one publication may be current for a given trade_date.
-- 3. The current publication must represent the consumer-readable state for that trade_date.
-- 4. Superseded publications remain auditable and must not be deleted merely because they are no longer current.
-- 5. A corrected publication should reference the prior publication through supersedes_publication_id.
-- 6. Publication_version should increase only when a newly sealed publication for the same trade_date
--    becomes a distinct published state.
-- 7. Unchanged reruns must not create a fake new publication_version.

-- IMPORTANT NOTE
-- MariaDB cannot enforce "only one row with is_current=1 per trade_date" using a partial unique index
-- in the same way some other databases can.
-- Therefore the single-current-publication invariant must be enforced by application transaction discipline
-- or a locked stored-procedure publication-switch flow.

-- RECOMMENDED PUBLICATION SWITCH FLOW
-- 1. Resolve the current publication for trade_date D.
-- 2. Insert the new sealed publication row with is_current = 0.
-- 3. In one protected transaction / locked flow:
--      - set prior current publication to is_current = 0
--      - set new publication to is_current = 1
-- 4. Preserve prior publication row as audit history.
-- LOCKED INTERPRETATION
-- 1. `is_current` is supporting mirror state only when the hardened pointer model is used.
-- 2. Current-publication ownership remains with `eod_current_publication_pointer`.
-- 3. Builders must not implement current-publication resolution from this table alone when the pointer table is present.
