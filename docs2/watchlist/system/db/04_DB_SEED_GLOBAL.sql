-- NON-NORMATIVE SQL ARTIFACT
-- REVIEWER RULE: semantic changes must be introduced in normative markdown first.
-- Dokumen owner semantik tetap berada pada file markdown normatif terkait.
-- Jika ada konflik, file normatif markdown selalu menang dan SQL ini harus disesuaikan.

-- 04_DB_SEED_GLOBAL.sql
-- Seed global tables for Watchlist platform (lintas policy)
-- Catatan: struktur tabel global dibuat oleh 05_DB_DDL_MARIADB.sql; file ini hanya melakukan INSERT seed.

-- A) FAIL CODES (global)
-- Layer `RECOMMENDATION` diakui oleh dictionary global aktif, tetapi seed default
-- tidak wajib mengisinya bila policy aktif menaruh fail/reason codes layer tersebut
-- di seed policy-scoped resmi.

INSERT INTO watchlist_fail_codes (fail_code, scope, severity, description_id) VALUES
('PLAN_ABORT_PARAMSET_INVALID','PLAN','ERROR','Param set tidak valid / tidak match policy_version.'),
('PLAN_ABORT_PARAMSET_NOT_FOUND','PLAN','ERROR','ACTIVE param_set tidak ditemukan untuk policy/rule yang dijalankan.'),
('PLAN_ABORT_CALENDAR_MISSING','PLAN','ERROR','Market calendar tidak tersedia untuk menentukan target trading day.'),
('PLAN_ABORT_DATA_INCOMPLETE','PLAN','ERROR','Batch data tidak lengkap menurut readiness/coverage rules policy.'),
('PLAN_ABORT_COVERAGE_LOW','PLAN','ERROR','Coverage eligible di bawah minimum sehingga PLAN gagal dijalankan.'),
('PLAN_ABORT_ALREADY_EXISTS','PLAN','ERROR','PLAN untuk target date ini sudah ada; gunakan mode rerun jika ingin buat ulang.'),
('PLAN_ABORT_LOCK_TIMEOUT','PLAN','ERROR','Gagal memperoleh lock eksekusi PLAN.'),
('PLAN_ABORT_DATA_CONTRACT','PLAN','ERROR','Required source atau required field tidak memenuhi kontrak data PLAN.'),
('PLAN_ABORT_HASH_FAILED','PLAN','ERROR','Gagal menghitung data_batch_hash.'),
('PLAN_ABORT_DB_WRITE_FAILED','PLAN','ERROR','Gagal menulis snapshot PLAN (DB error).'),
('CONFIRM_ABORT_NO_ACTIVE_PLAN','CONFIRM','ERROR','Tidak ada PLAN aktif untuk target date ini.'),
('CONFIRM_ABORT_PARAMSET_INVALID','CONFIRM','ERROR','Param set pada plan_run tidak valid / mismatch.'),
('CONFIRM_ABORT_DB_WRITE_FAILED','CONFIRM','ERROR','Gagal menulis hasil CONFIRM ke database.');

-- B) REASON CODES (global; per policy_code)
-- NOTE: Struktur tabel dibuat di 05_DB_DDL_MARIADB.sql (LOCKED). File seed hanya INSERT.
