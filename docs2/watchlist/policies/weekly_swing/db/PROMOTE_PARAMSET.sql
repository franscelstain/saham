-- 15_WS_PROMOTE_PARAMSET.sql
-- Util script khusus WS untuk promosi param_set. Validasi JSON tetap wajib di app layer.

SET @POLICY := 'WS';
SET @TARGET_PARAM_SET_ID := 123;

START TRANSACTION;

SELECT GET_LOCK(CONCAT(@POLICY, ':PARAMSET'), 10) AS got_lock;

UPDATE watchlist_param_sets
SET status = 'DEPRECATED', updated_at = NOW()
WHERE policy_code = @POLICY
  AND status = 'ACTIVE';

UPDATE watchlist_param_sets
SET status = 'ACTIVE', updated_at = NOW()
WHERE param_set_id = @TARGET_PARAM_SET_ID
  AND policy_code = @POLICY;

COMMIT;

SELECT RELEASE_LOCK(CONCAT(@POLICY, ':PARAMSET')) AS released;
