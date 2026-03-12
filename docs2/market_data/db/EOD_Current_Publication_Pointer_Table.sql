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
-- 1. This table is the strongest DB-facing pointer for "one current publication per trade_date".
-- 2. There can be at most one current publication pointer row for one trade_date because trade_date is the primary key.
-- 3. publication_id is also unique so one publication cannot be current for more than one trade_date.
-- 4. This table does not replace eod_publications history; it provides a hardened current-state pointer.
-- 5. Consumer-readable publication resolution must use this pointer first where implemented; behavioral meaning is owned by ../book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md.