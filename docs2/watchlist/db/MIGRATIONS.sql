-- MIGRATIONS.sql
-- Kumpulan ALTER untuk schema watchlist global (MariaDB 10.4).
-- Jalankan hanya jika database sudah terlanjur dibuat dari versi DDL yang lebih lama.

-- Add run-level metrics to PLAN runs (untuk simpan cutoff dinamis, coverage, dll)
ALTER TABLE watchlist_plan_runs
  ADD COLUMN run_metrics_json LONGTEXT NOT NULL
  AFTER fail_code;
