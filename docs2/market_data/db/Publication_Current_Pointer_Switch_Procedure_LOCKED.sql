DELIMITER $$

CREATE PROCEDURE sp_promote_current_publication_pointer (
    IN p_trade_date DATE,
    IN p_new_publication_id BIGINT UNSIGNED,
    IN p_new_run_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_new_trade_date DATE;
    DECLARE v_new_is_current TINYINT;
    DECLARE v_new_seal_state VARCHAR(16);
    DECLARE v_new_publication_version INT UNSIGNED;
    DECLARE v_new_sealed_at DATETIME;

    START TRANSACTION;

    SELECT trade_date, is_current, seal_state, publication_version, sealed_at
      INTO v_new_trade_date, v_new_is_current, v_new_seal_state, v_new_publication_version, v_new_sealed_at
      FROM eod_publications
     WHERE publication_id = p_new_publication_id
       AND run_id = p_new_run_id
     FOR UPDATE;

    IF v_new_trade_date IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Publication not found for promotion';
    END IF;

    IF v_new_trade_date <> p_trade_date THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Publication trade_date mismatch';
    END IF;

    IF v_new_seal_state <> 'SEALED' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only sealed publication may become current';
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
        v_new_publication_version,
        v_new_sealed_at,
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
-- 1. This procedure updates both eod_publications and eod_current_publication_pointer in one transactional flow.
-- 2. Exactly one current pointer row exists per trade_date because the pointer table uses trade_date as PRIMARY KEY.
-- 3. This is stronger than relying on eod_publications.is_current alone.
-- 4. Consumer-readability resolution should use the pointer table when available.