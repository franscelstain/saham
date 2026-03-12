-- Purpose:
-- Enforce append-only / immutable behavior for production-grade history tables.

DELIMITER $$

CREATE TRIGGER trg_no_update_eod_bars_history
BEFORE UPDATE ON eod_bars_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'eod_bars_history is immutable and cannot be updated';
END$$

CREATE TRIGGER trg_no_delete_eod_bars_history
BEFORE DELETE ON eod_bars_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'eod_bars_history is immutable and cannot be deleted';
END$$

CREATE TRIGGER trg_no_update_eod_indicators_history
BEFORE UPDATE ON eod_indicators_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'eod_indicators_history is immutable and cannot be updated';
END$$

CREATE TRIGGER trg_no_delete_eod_indicators_history
BEFORE DELETE ON eod_indicators_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'eod_indicators_history is immutable and cannot be deleted';
END$$

CREATE TRIGGER trg_no_update_eod_eligibility_history
BEFORE UPDATE ON eod_eligibility_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'eod_eligibility_history is immutable and cannot be updated';
END$$

CREATE TRIGGER trg_no_delete_eod_eligibility_history
BEFORE DELETE ON eod_eligibility_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'eod_eligibility_history is immutable and cannot be deleted';
END$$

DELIMITER ;

-- LOCKED SEMANTICS
-- 1. History tables are append-only snapshot tables.
-- 2. A publication-specific snapshot may be inserted once, but never updated in place.
-- 3. Removal of history rows in normal operation is forbidden.
-- 4. If exceptional maintenance is ever needed, it must be handled outside normal production flow and versioned/documented explicitly.