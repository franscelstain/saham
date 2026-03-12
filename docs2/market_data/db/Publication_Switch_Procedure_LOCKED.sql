-- LOCKED PROCEDURE PATTERN
-- Purpose: enforce deterministic publication switching so one trade_date has one current publication.

-- NOTE:
-- This is a reference locked procedure pattern.
-- Exact syntax may be adapted to local MariaDB deployment conventions.

DELIMITER $$

CREATE PROCEDURE sp_switch_current_publication (
    IN p_trade_date DATE,
    IN p_new_publication_id BIGINT UNSIGNED,
    IN p_new_run_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_current_publication_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_new_seal_state VARCHAR(16);
    DECLARE v_new_trade_date DATE;

    START TRANSACTION;

    SELECT publication_id
      INTO v_current_publication_id
      FROM eod_publications
     WHERE trade_date = p_trade_date
       AND is_current = 1
     FOR UPDATE;

    SELECT seal_state, trade_date
      INTO v_new_seal_state, v_new_trade_date
      FROM eod_publications
     WHERE publication_id = p_new_publication_id
       AND run_id = p_new_run_id
     FOR UPDATE;

    IF v_new_trade_date IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'New publication not found for requested switch';
    END IF;

    IF v_new_trade_date <> p_trade_date THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Publication trade_date mismatch';
    END IF;

    IF v_new_seal_state <> 'SEALED' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Unsealed publication cannot become current';
    END IF;

    UPDATE eod_publications
       SET is_current = 0,
           updated_at = NOW()
     WHERE trade_date = p_trade_date
       AND is_current = 1;

    UPDATE eod_publications
       SET is_current = 1,
           updated_at = NOW()
     WHERE publication_id = p_new_publication_id;

    UPDATE eod_runs
       SET is_current_publication = 0,
           updated_at = NOW()
     WHERE trade_date_effective = p_trade_date
       AND is_current_publication = 1;

    UPDATE eod_runs
       SET is_current_publication = 1,
           updated_at = NOW()
     WHERE run_id = p_new_run_id;

    COMMIT;
END$$

DELIMITER ;

-- LOCKED SEMANTICS
-- 1. Only a SEALED publication may become current.
-- 2. Switching current publication is transactional.
-- 3. Old current publication is demoted before/with new promotion inside the same protected flow.
-- 4. eod_runs current-publication flags are kept aligned with eod_publications.
-- 5. This procedure pattern is the enforcement answer where MariaDB lacks a partial unique index for one-current-per-date.