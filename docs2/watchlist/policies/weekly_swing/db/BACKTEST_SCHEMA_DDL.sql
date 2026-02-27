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
  -- weights (raw; normalisasi dilakukan saat compute)
  w_momentum DECIMAL(10,6) NOT NULL,
  w_volume DECIMAL(10,6) NOT NULL,
  w_breakout DECIMAL(10,6) NOT NULL,
  w_risk DECIMAL(10,6) NOT NULL,
  -- picks sizing
  top_picks_target INT NOT NULL,
  secondary_target INT NOT NULL,
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
  picks_count INT NOT NULL,
  avg_ret_net_top DECIMAL(10,6) NOT NULL,
  win_rate_top DECIMAL(10,6) NOT NULL,
  -- optional extra stats
  avg_ret_net_all DECIMAL(10,6) NULL,
  win_rate_all DECIMAL(10,6) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (eval_id),
  UNIQUE KEY UQ_bt_eval_policy_param_window (policy_code, param_id, from_date, to_date),
  KEY IDX_bt_eval_rank (policy_code, avg_ret_net_top, win_rate_top),
  CONSTRAINT FK_bt_eval_param FOREIGN KEY (param_id) REFERENCES watchlist_bt_param_grid(param_id)
) ENGINE=InnoDB;

-- Picks yang dihasilkan per asof_eod_date dan param_id (untuk audit dan analisis detail)
CREATE TABLE IF NOT EXISTS watchlist_bt_picks_ws (
  pick_id BIGINT NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(16) NOT NULL,
  param_id INT NOT NULL,
  asof_eod_date DATE NOT NULL,
  ticker_id BIGINT NOT NULL,
  -- signal_code optional; isi dari dataset jika tersedia
  signal_code VARCHAR(32) NULL,
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
  required_ok     TINYINT(1) NOT NULL,
  reason_code     VARCHAR(32) NULL,
  PRIMARY KEY (asof_eod_date, ticker_id),
  KEY idx_bt_univ_ws_req (asof_eod_date, required_ok),
  KEY idx_bt_univ_ws_reason (asof_eod_date, reason_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
