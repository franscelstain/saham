-- NON-NORMATIVE SQL ARTIFACT
-- REVIEWER RULE: semantic changes must be introduced in normative markdown first.
-- Dokumen owner semantik tetap berada pada file markdown normatif terkait.
-- Jika ada konflik, file normatif markdown selalu menang dan SQL ini harus disesuaikan.

-- Implementation artifact for canonical Weekly Swing paramset promotion.
-- Normative procedure owner:
-- docs/watchlist/system/policies/weekly_swing/20_WS_CANONICAL_PARAMSET_PROCEDURES.md
-- Related contract owners:
-- docs/watchlist/system/policies/weekly_swing/04_WS_PARAMSET_JSON_CONTRACT.md
-- docs/watchlist/system/policies/weekly_swing/06_WS_PARAMSET_VALIDATOR_SPEC.md
--
-- This SQL file realizes the promotion flow defined by normative policy documents.
-- It does not become the owner of business-rule semantics, validation ownership,
-- or activation requirements.

-- PROMOTE_PARAMSET.sql
-- Util script khusus WS untuk promosi param_set.
-- Validasi JSON tetap wajib di app layer, tetapi script ini juga menjaga gate OOS minimum.
-- Ganti nilai variabel sebelum dijalankan.

SET @POLICY := 'WS';
SET @TARGET_PARAM_SET_ID := 123;
SET @TARGET_POLICY_VERSION := 'v1';
SET @TARGET_BT_PARAM_ID := 789;
SET @TARGET_OOS_ID := 456;
SET @LOCK_NAME := CONCAT(@POLICY, ':PARAMSET');
SET @LOCK_TIMEOUT_SEC := 10;

DELIMITER //

DROP PROCEDURE IF EXISTS ws_promote_paramset //
CREATE PROCEDURE ws_promote_paramset()
BEGIN
  DECLARE v_got_lock INT DEFAULT 0;
  DECLARE v_target_ok INT DEFAULT 0;
  DECLARE v_oos_ok INT DEFAULT 0;
  DECLARE v_promoted_rows INT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    DO RELEASE_LOCK(@LOCK_NAME);
    RESIGNAL;
  END;

  SELECT GET_LOCK(@LOCK_NAME, @LOCK_TIMEOUT_SEC) INTO v_got_lock;
  IF v_got_lock <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'WS paramset promote failed: lock timeout / lock unavailable';
  END IF;

  SELECT COUNT(*) INTO v_target_ok
  FROM watchlist_param_sets
  WHERE param_set_id = @TARGET_PARAM_SET_ID
    AND policy_code = @POLICY
    AND policy_version = @TARGET_POLICY_VERSION
    AND status = 'DRAFT';

  IF v_target_ok <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'WS paramset promote failed: target paramset missing or not DRAFT';
  END IF;

  SELECT COUNT(*) INTO v_oos_ok
  FROM watchlist_bt_oos_eval_ws o
  WHERE o.oos_id = @TARGET_OOS_ID
    AND o.policy_code = @POLICY
    AND o.policy_version = @TARGET_POLICY_VERSION
    AND o.param_id_best_is = @TARGET_BT_PARAM_ID
    AND o.picks_count_oos > 0
    AND o.avg_ret_net_top_oos > 0
    AND o.median_ret_net_top_oos >= 0
    AND o.month_win_rate_min_oos >= 0.45
    AND o.p25_ret_net_top_oos >= -0.03;

  IF v_oos_ok <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'WS paramset promote failed: OOS proof missing / failed acceptance gate';
  END IF;

  START TRANSACTION;

  UPDATE watchlist_param_sets
  SET status = 'DEPRECATED', updated_at = NOW()
  WHERE policy_code = @POLICY
    AND status = 'ACTIVE';

  UPDATE watchlist_param_sets
  SET status = 'ACTIVE', updated_at = NOW()
  WHERE param_set_id = @TARGET_PARAM_SET_ID
    AND policy_code = @POLICY
    AND policy_version = @TARGET_POLICY_VERSION
    AND status = 'DRAFT';

  SET v_promoted_rows = ROW_COUNT();
  IF v_promoted_rows <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'WS paramset promote failed: target row not promoted';
  END IF;

  COMMIT;
  DO RELEASE_LOCK(@LOCK_NAME);
END //

CALL ws_promote_paramset() //
DROP PROCEDURE ws_promote_paramset //

DELIMITER ;
