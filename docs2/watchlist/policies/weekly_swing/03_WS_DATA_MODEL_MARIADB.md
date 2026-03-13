# 03 — Data Model (MariaDB 10.4) — Weekly Swing

## Purpose
Menetapkan mapping kebutuhan WS terhadap schema global watchlist (tabel global ada di [`../../db/`](../../db/README.md)). Dokumen ini tidak mendefinisikan DDL tabel global, tetapi wajib konsisten dengannya.

## Prerequisites
### Weekly Swing
02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md

## Inputs
- MariaDB 10.4
- Universe tickers (table master)

## Principles (LOCKED)
- `watchlist_plan_items` bersifat **append-only**.
- `watchlist_plan_runs` bersifat **immutable-history**: UPDATE hanya boleh untuk deaktivasi supersede satu arah pada `is_active` (`Yes -> No`).
- Supersede PLAN dilakukan dengan: insert header baru aktif + insert items baru + deaktifkan header lama dalam transaksi yang sama.
- Semua ticker di universe memiliki row di `watchlist_plan_items` (audit completeness).

## Mapping ke tabel global (konseptual)
1) `watchlist_param_sets`
- `param_set_id` (PK)
- `policy_code`, `policy_version`
- `status` (DRAFT/ACTIVE/DEPRECATED)
- `params_json`
- `created_at`, `updated_at`

2) `watchlist_plan_runs`
- `plan_run_id` (PK)
- `policy_code`, `policy_version`
- `asof_eod_date`, `plan_trade_date`
- `param_set_id` (FK)
- `run_status` (OK/NO_TRADE/FAILED)
- `data_batch_hash`, `hash_count`, `missing_required_count`
- `processed_count`, `eligible_count`
- `supersedes_plan_run_id` (nullable)
- `is_active` (Yes/No)
- `fail_code` (nullable; FK ke `watchlist_fail_codes.fail_code`)
- `created_at`

3) `watchlist_plan_items`
- `plan_item_id` (PK)
- `plan_run_id` (FK)
- `trade_date` (`plan_trade_date`)
- `ticker_id`, `ticker_code`
- `group_semantic` (TOP_PICKS/SECONDARY/WATCH_ONLY/AVOID)
- `display_bucket` (SHOW/HIDE)
- `selection_reason_code`
- `score_total` + component scores via `scores_json`
- `inputs_json` untuk feature EOD (mis. `close`, `hh20`, `roc20`, `atr14_pct`, `dv20_idr`)
- `plan_levels_json` untuk level entry/stop/tp
- `reason_codes_json`
- `created_at`

4) CONFIRM:
- `watchlist_confirm_checks` (header)
- `watchlist_confirm_items` (detail per ticker)

5) Dictionaries:
- `watchlist_reason_codes` (WS-specific dictionary)
- `watchlist_fail_codes` (global dictionary)

## LOCKED — Persisted Records ↔ Runtime Output Mapping

Bagian ini mengunci bagaimana data yang dipersist ke tabel global dipetakan ke runtime output (API/UI) pada policy Weekly Swing. Untuk PLAN, mapping normatif dikunci pada bagian ini. Untuk CONFIRM, bentuk output minimum normatif dikunci pada `10_WS_CONFIRM_OVERLAY.md`. Dokumen [`_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`](_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md) hanya menjadi referensi contoh output dan quick shape guide, dan tidak boleh diperlakukan sebagai sumber aturan utama.

### A) PLAN header: `watchlist_plan_runs` → `WS_PLAN_RUNTIME_OUTPUT.meta`
Mapping (LOCKED):
- `policy_code` → `meta.policy`
- `asof_eod_date` → `meta.asof_eod_date`
- `plan_trade_date` → `meta.trade_date`
- `param_set_id` → `meta.paramset_id`
- `data_batch_hash` → `meta.data_batch_hash`
- `created_at` → `meta.generated_at`
- `run_status = 'NO_TRADE'` → `meta.fail_code = "NO_TRADE"`; `summary.no_trade = true` adalah view-model turunan.
- `run_status = 'OK'` → `meta.fail_code = null`
- `fail_code` adalah source of truth run-level untuk `FAILED` maupun `NO_TRADE`; reason turunan seperti `summary.no_trade_reason` atau `meta.fail_reason_codes` berasal dari dictionary/fail mapping.

Catatan (LOCKED):
- `paramset_hash` dan `plan_hash` boleh dihitung di runtime output; tidak wajib menjadi kolom persisted.
- `meta.source.*` boleh berasal dari join/compute.

### B) PLAN items: `watchlist_plan_items` → `WS_PLAN_RUNTIME_OUTPUT.items[]`
Mapping (LOCKED):
- `ticker_code` → `items[].ticker`
- rank dihitung dari urutan canonical output, bukan kolom persisted
- `group_semantic` → `items[].group_semantic`
- `score_total` → `items[].score_total`
- `scores_json` → `items[].scores.*`
- `plan_levels_json` → `items[].levels.*`
- `display_bucket` → `items[].flags.hidden`
- eligibility → `items[].flags.eligible`
- `reason_codes_json` → `items[].reasons[]`

### C) CONFIRM header & items
- `watchlist_confirm_checks` → `WS_CONFIRM_RUNTIME_OUTPUT.meta`
- `watchlist_confirm_items` → `WS_CONFIRM_RUNTIME_OUTPUT.items[]`

Aturan (LOCKED):
- CONFIRM **dilarang** mengubah PLAN rows.
- Non-contract fields pada payload CONFIRM wajib di-ignore, dan boleh dicatat pada `ignored_fields[]` di output.

## Example notes
Contoh persisted rows di dokumen/example file bersifat ilustratif terhadap **bentuk** record dan mapping. Nilai ID/timestamp hanyalah contoh, tetapi nama field dan semantics tidak boleh drift dari DDL.

## Outputs
- Daftar tabel & invariants WS yang harus diimplementasikan konsisten dengan schema global.

## Failure modes
- UPDATE `watchlist_plan_items` => melanggar append-only.
- UPDATE `watchlist_plan_runs` selain deaktivasi supersede satu arah => parity failure.
- Menyebut field persisted yang tidak ada di DDL tanpa penanda computed => ambiguity.

## CONFIRM Snapshot Tables (LOCKED)
Weekly Swing CONFIRM menggunakan snapshot manual yang disimpan di DB:
- `watchlist_confirm_snapshots`
- `watchlist_confirm_snapshot_items`

Spesifikasi tabel + kolom wajib + contoh data:
- 11_WS_INTRADAY_SNAPSHOT_TABLES.md

TTL (LOCKED): `snapshot_max_age_sec = 900` dihitung dari `effective_captured_at = LEAST(captured_at, inserted_at)`.

## Next
### Weekly Swing
- 04_WS_PARAMSET_JSON_CONTRACT.md
