-- NON-NORMATIVE SQL ARTIFACT
-- REVIEWER RULE: semantic changes must be introduced in normative markdown first.
-- Dokumen owner semantik tetap berada pada file markdown normatif terkait.
-- Jika ada konflik, file normatif markdown selalu menang dan SQL ini harus disesuaikan.

-- Implementation artifact for Weekly Swing backtest schema.
-- Normative schema and calibration owner:
-- docs/watchlist/system/policies/weekly_swing/12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md
--
-- Column semantics, scope, and backtest-related meaning must follow the
-- normative Weekly Swing backtest documents.
-- This SQL file realizes schema implementation and does not replace
-- normative ownership.

-- 08_WS_BACKTEST_SCHEMA_DDL.sql
-- Schema backtest untuk policy Weekly Swing (WS)
-- MariaDB 10.4 / InnoDB
--
-- Catatan desain:
-- - policy_code untuk saat ini dikunci 'WS' lewat prosedur runner (bukan constraint).
-- - Semua hasil evaluasi param grid disimpan agar bisa diaudit dan diulang.
-- - Tabel dibuat minimal, tapi cukup untuk: grid param → eval → picks per asof date.

CREATE TABLE IF NOT EXISTS watchlist_bt_param_grid (
  param_id INT NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  -- guardrails
  min_dv20_idr BIGINT NOT NULL,
  max_atr14_pct DECIMAL(10,6) NOT NULL,
  min_vol_ratio DECIMAL(10,6) NOT NULL,
  -- weights (raw; normalisasi dilakukan saat compute)
  w_momentum DECIMAL(10,6) NOT NULL,
  w_volume DECIMAL(10,6) NOT NULL,
  w_breakout DECIMAL(10,6) NOT NULL,
  w_risk DECIMAL(10,6) NOT NULL,
  -- picks sizing
  top_picks_target INT NOT NULL,
  secondary_target INT NOT NULL,
  -- grouping quantile cutoffs (BT)
  top_min_score_q DECIMAL(10,6) NOT NULL,
  secondary_min_score_q DECIMAL(10,6) NOT NULL,
  -- meta
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (param_id),
  KEY IDX_bt_grid_policy (policy_code, param_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS watchlist_bt_eval (
  eval_id BIGINT NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  param_id INT NOT NULL,

  -- evaluation window metadata
  from_date DATE NOT NULL,
  to_date DATE NOT NULL,
  days_covered SMALLINT UNSIGNED NOT NULL,

  -- core top-bucket metrics (used for calibration)
  picks_count INT NOT NULL,
  avg_ret_net_top DECIMAL(10,6) NOT NULL,
  win_rate_top DECIMAL(10,6) NOT NULL,

  -- distribution & downside metrics (anti-outlier / risk bound)
  median_ret_net_top DECIMAL(10,6) NOT NULL,
  p25_ret_net_top DECIMAL(10,6) NOT NULL,
  p75_ret_net_top DECIMAL(10,6) NOT NULL,
  min_ret_net_top DECIMAL(10,6) NOT NULL,
  max_ret_net_top DECIMAL(10,6) NOT NULL,

  -- stability metrics (period-based, default: monthly)
  periods_count TINYINT UNSIGNED NOT NULL,
  period_fail_count TINYINT UNSIGNED NOT NULL,
  month_win_rate_min DECIMAL(10,6) NOT NULL,
  month_avg_ret_net_min DECIMAL(10,6) NOT NULL,

  -- optional extra stats
  avg_ret_net_all DECIMAL(10,6) NULL,
  win_rate_all DECIMAL(10,6) NULL,
  median_ret_net_all DECIMAL(10,6) NULL,
  p25_ret_net_all DECIMAL(10,6) NULL,
  p75_ret_net_all DECIMAL(10,6) NULL,
  min_ret_net_all DECIMAL(10,6) NULL,
  max_ret_net_all DECIMAL(10,6) NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (eval_id),
  UNIQUE KEY UQ_bt_eval_policy_param_window (policy_code, param_id, from_date, to_date),

  -- ranking index: keep existing + extend for robust ranking
  KEY IDX_bt_eval_rank (policy_code, avg_ret_net_top, median_ret_net_top, month_win_rate_min, p25_ret_net_top, win_rate_top),

  CONSTRAINT FK_bt_eval_param FOREIGN KEY (param_id) REFERENCES watchlist_bt_param_grid(param_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS watchlist_bt_oos_eval_ws (
  oos_id BIGINT NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  policy_version VARCHAR(32) NOT NULL,
  eval_model VARCHAR(96) NOT NULL,
  param_id_best_is INT NOT NULL,

  -- in-sample window (where it was selected)
  from_date_is DATE NOT NULL,
  to_date_is DATE NOT NULL,

  -- out-of-sample window (where it was proven)
  from_date_oos DATE NOT NULL,
  to_date_oos DATE NOT NULL,
  days_covered_oos SMALLINT UNSIGNED NOT NULL,

  -- OOS metrics (same core set as bt_eval top bucket)
  picks_count_oos INT NOT NULL,
  avg_ret_net_top_oos DECIMAL(10,6) NOT NULL,
  win_rate_top_oos DECIMAL(10,6) NOT NULL,
  median_ret_net_top_oos DECIMAL(10,6) NOT NULL,
  p25_ret_net_top_oos DECIMAL(10,6) NOT NULL,
  month_win_rate_min_oos DECIMAL(10,6) NOT NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (oos_id),  
  UNIQUE KEY UQ_bt_oos_policy_param_windows (
    policy_code, policy_version, eval_model, param_id_best_is,
    from_date_is, to_date_is, from_date_oos, to_date_oos
  ),
  KEY IDX_bt_oos_rank (policy_code, avg_ret_net_top_oos, win_rate_top_oos, median_ret_net_top_oos),
  

  CONSTRAINT FK_bt_oos_param_best_is FOREIGN KEY (param_id_best_is) REFERENCES watchlist_bt_param_grid(param_id)
) ENGINE=InnoDB;

-- Picks yang dihasilkan per asof_eod_date dan param_id (untuk audit dan analisis detail)
CREATE TABLE IF NOT EXISTS watchlist_bt_picks_ws (
  pick_id BIGINT NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  param_id INT NOT NULL,
  asof_eod_date DATE NOT NULL,
  ticker_id BIGINT NOT NULL,
  bucket_code VARCHAR(16) NOT NULL, -- TOP_PICKS | SECONDARY
  -- outcome (ret_net) harus sudah include biaya & slippage versi backtest yang disepakati
  ret_net DECIMAL(10,6) NOT NULL,
  pass_guard TINYINT NOT NULL,
  score_total DECIMAL(10,6) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pick_id),
  KEY IDX_bt_picks_date (policy_code, asof_eod_date, param_id),
  KEY IDX_bt_picks_param_ticker (policy_code, param_id, ticker_id),
  CONSTRAINT FK_bt_picks_param FOREIGN KEY (param_id) REFERENCES watchlist_bt_param_grid(param_id)
) ENGINE=InnoDB;

/* ===================== Weekly Swing Backtest Universe (AUDIT) ===================== */
CREATE TABLE IF NOT EXISTS watchlist_bt_universe_ws (
  asof_eod_date   DATE NOT NULL,
  ticker_id       INT  NOT NULL,

  -- data-quality (required fields available)
  required_ok     TINYINT(1) NOT NULL,
  missing_fields  VARCHAR(255) NULL,

  -- guardrails (evaluated using the active paramset thresholds)
  guard_ok        TINYINT(1) NOT NULL,
  eligible_ok     TINYINT(1) NOT NULL,

  -- metrics snapshot (for equivalence debugging)
  dv20_idr        BIGINT NULL,
  atr14_pct       DECIMAL(10,6) NULL,
  vol_ratio       DECIMAL(10,6) NULL,

  -- canonical reason for NOT eligible (must follow priority in doc 15)
  reason_code     VARCHAR(32) NULL,

  PRIMARY KEY (asof_eod_date, ticker_id),

  KEY idx_bt_univ_ws_req (asof_eod_date, required_ok),
  KEY idx_bt_univ_ws_elig (asof_eod_date, eligible_ok),
  KEY idx_bt_univ_ws_reason (asof_eod_date, reason_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS watchlist_bt_cutoffs_ws (
  policy_code VARCHAR(16) NOT NULL,
  param_id INT NOT NULL,
  asof_eod_date DATE NOT NULL,

  top_cutoff_score DECIMAL(10,6) NOT NULL,
  secondary_cutoff_score DECIMAL(10,6) NOT NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (policy_code, param_id, asof_eod_date),
  KEY IDX_bt_cutoffs_date (policy_code, asof_eod_date, param_id),
  CONSTRAINT FK_bt_cutoffs_param FOREIGN KEY (param_id) REFERENCES watchlist_bt_param_grid(param_id)
) ENGINE=InnoDB;