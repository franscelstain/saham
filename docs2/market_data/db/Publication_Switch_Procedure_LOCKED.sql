-- LOCKED COMPATIBILITY WRAPPER
-- Purpose: preserve a legacy procedure name while delegating to the hardened
-- pointer-based publication switch flow.
--
-- Normative build path:
--   1. `eod_current_publication_pointer` is the primary current-publication owner.
--   2. `sp_switch_current_publication_pointer` is the primary switch procedure.
--   3. This wrapper exists only for compatibility with older callers that still
--      invoke `sp_switch_current_publication`.
--
-- New implementations should call `sp_switch_current_publication_pointer` directly.

DELIMITER $$

CREATE PROCEDURE sp_switch_current_publication (
    IN p_trade_date DATE,
    IN p_new_publication_id BIGINT UNSIGNED,
    IN p_new_run_id BIGINT UNSIGNED
)
BEGIN
    CALL sp_switch_current_publication_pointer(
        p_trade_date,
        p_new_publication_id,
        p_new_run_id
    );
END$$

DELIMITER ;

-- LOCKED COMPATIBILITY SEMANTICS
-- 1. This wrapper must not introduce switching behavior different from
--    `sp_switch_current_publication_pointer`.
-- 2. Current-publication resolution still belongs to
--    `eod_current_publication_pointer`, not to `eod_publications.is_current` alone.
-- 3. If this wrapper is kept, it should be documented and presented as
--    compatibility-only, not as the preferred production switch path.
