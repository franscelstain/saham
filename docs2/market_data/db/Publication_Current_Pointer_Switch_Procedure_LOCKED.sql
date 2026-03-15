-- LOCKED PROCEDURE PATTERN
-- Purpose: switch the hardened current-publication pointer in the same protected flow
-- that promotes a new sealed publication to current state.
-- This is the preferred production switch path for new implementations.

DELIMITER $$

CREATE PROCEDURE sp_switch_current_publication_pointer (
    IN p_trade_date DATE,
    IN p_new_publication_id BIGINT UNSIGNED,
    IN p_new_run_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_pub_trade_date DATE;
    DECLARE v_pub_seal_state VARCHAR(16);
    DECLARE v_pub_version INT UNSIGNED;

    START TRANSACTION;

    SELECT trade_date, seal_state, publication_version
      INTO v_pub_trade_date, v_pub_seal_state, v_pub_version
      FROM eod_publications
     WHERE publication_id = p_new_publication_id
       AND run_id = p_new_run_id
     FOR UPDATE;

    IF v_pub_trade_date IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Publication not found for pointer switch';
    END IF;

    IF v_pub_trade_date <> p_trade_date THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Pointer switch trade_date mismatch';
    END IF;

    IF v_pub_seal_state <> 'SEALED' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Unsealed publication cannot become current pointer target';
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

    INSERT INTO eod_current_publication_pointer (
        trade_date,
        publication_id,
        run_id,
        publication_version,
        sealed_at,
        updated_at
    )
    VALUES (
        p_trade_date,
        p_new_publication_id,
        p_new_run_id,
        v_pub_version,
        NOW(),
        NOW()
    )
    ON DUPLICATE KEY UPDATE
        publication_id = VALUES(publication_id),
        run_id = VALUES(run_id),
        publication_version = VALUES(publication_version),
        sealed_at = VALUES(sealed_at),
        updated_at = VALUES(updated_at);

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
-- 1. The pointer table is updated transactionally with publication promotion.
-- 2. Only a SEALED publication may become the pointer target.
-- 3. Pointer, eod_publications.is_current, and eod_runs.is_current_publication are required to stay aligned.
-- 4. Missing or mismatched pointer state is an incident and must fail safe for consumer readability.
