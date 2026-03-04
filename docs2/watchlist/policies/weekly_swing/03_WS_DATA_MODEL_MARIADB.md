# 03 — Data Model (MariaDB 10.4) — Weekly Swing

## Purpose
Menetapkan mapping kebutuhan WS terhadap schema global watchlist (tabel global ada di `../../db/`). Dokumen ini tidak mendefinisikan DDL tabel global.

## Prerequisites
### Weekly Swing
02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md

## Inputs
- MariaDB 10.4
- Universe tickers (table master)

## Process
### Prinsip schema (WS)
- PLAN snapshot tables bersifat **append-only** untuk items.
- Supersede dilakukan dengan flip `plan_runs.is_active` saja.
- Semua ticker di universe memiliki row di `watchlist_plan_items` (audit).

### Mapping ke tabel global (konseptual)
1) `watchlist_param_sets`
- param_set_id (PK)
- policy_code, policy_version
- status (DRAFT/ACTIVE/DEPRECATED)
- params_json (LONGTEXT/JSON stored as text in MariaDB 10.4)
- created_at, updated_at

2) `watchlist_plan_runs`
- plan_run_id (PK)
- policy_code, policy_version
- asof_eod_date, plan_trade_date
- param_set_id (FK)
- run_status (OK/NO_TRADE/FAILED)
- data_batch_hash, hash_count, missing_required_count
- processed_count, eligible_count
- supersedes_plan_run_id (nullable)
- is_active (Yes/No)
- created_at

3) `watchlist_plan_items`
- plan_item_id (PK)
- plan_run_id (FK)
- trade_date (plan_trade_date)
- ticker_id, ticker_code (optional cache)
- group_semantic (TOP_PICKS/SECONDARY/WATCH_ONLY/AVOID)
- display_bucket (SHOW/HIDE)
- selection_reason_code (ringkas)
- score_total + component scores
- inputs: close, hh20, roc20, atr14_pct, dv20_idr
- plan_levels: entry_ref, entry_low, entry_high, stop_price, tp1_price, rr
- reason_codes_json (array reason_code)
- created_at

4) CONFIRM:
- `watchlist_confirm_checks` (header)
- `watchlist_confirm_items` (detail per ticker)

5) Dictionaries:
- `watchlist_reason_codes` (WS-specific dictionary)
- `watchlist_fail_codes` (global dictionary; folder `_shared`)

## LOCKED — Persisted Records ↔ Runtime Output Mapping

Bagian ini mengunci bagaimana data yang dipersist ke tabel global dipetakan ke **runtime output** (API/UI) yang didefinisikan di `_refs/WS_RUNTIME_OUTPUT_SCHEMA.md`. Tujuannya: mencegah drift antara “DB record” vs “response payload”.

### A) PLAN header: `watchlist_plan_runs` → `WS_PLAN_RUNTIME_OUTPUT.meta`
Mapping (LOCKED):
- `policy_code` → `meta.policy`
- `asof_eod_date` → `meta.asof_eod_date`
- `plan_trade_date` → `meta.trade_date`
- `param_set_id` → `meta.paramset_id`
- `paramset_hash` → `meta.paramset_hash`
- `plan_hash` → `meta.plan_hash` (wajib dihitung sesuai canonicalization schema)
- `data_batch_hash` → `meta.data_batch_hash`
- `generated_at` → `meta.generated_at`
- `run_status = 'NO_TRADE'` → `meta.fail_code = 'NO_TRADE'` dan `meta.fail_reason_codes` memuat `WS_NO_TRADE_ALL_FILTERED`
- `run_status = 'OK'` → `meta.fail_code = null` dan `meta.fail_reason_codes = []`

Catatan (LOCKED):
- `meta.fail_code` **bukan** “status run” umum; dia hanya dipakai untuk gating yang memaksa `items=[]` (lihat NO_TRADE).
- Field runtime `meta.source.*` boleh berasal dari join/compute (bukan kolom wajib di header).

### B) PLAN items: `watchlist_plan_items` → `WS_PLAN_RUNTIME_OUTPUT.items[]`
Mapping (LOCKED):
- `ticker_code` → `items[].ticker`
- `ranking` → `items[].rank`
- `group_semantic` → `items[].group_semantic`
- `score_total` → `items[].score_total`
- component scores → `items[].scores.*`
- plan_levels → `items[].levels.*`
- `display_bucket`/flags internal → `items[].flags.hidden` (SHOW→false, HIDE→true)
- guard eligibility internal → `items[].flags.eligible`
- `reason_codes_json` → `items[].reasons[]` (setiap reason minimal punya `code` + `severity` sesuai dictionary)

### C) CONFIRM header & items
- `watchlist_confirm_checks` → `WS_CONFIRM_RUNTIME_OUTPUT.meta`
- `watchlist_confirm_items` → `WS_CONFIRM_RUNTIME_OUTPUT.items[]`

Aturan (LOCKED):
- CONFIRM **dilarang** mengubah PLAN rows; hasil CONFIRM harus ditulis di tabel CONFIRM (terpisah).
- Jika CONFIRM menerima payload dengan non-contract fields, field tersebut boleh hadir tapi wajib di-**ignore** (PASS+ignore) dan dapat dicatat sebagai `ignored_fields[]` di output.

## Examples — Persisted Records (Doc-First, LOCKED)

Contoh berikut mengunci **bentuk record** dan mapping field; nilai ID/ts bersifat contoh.

### Example 1 — PLAN persisted rows (header + 1 item)

**Source runtime example:** `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`

```json
{
  "watchlist_plan_runs": {
    "plan_run_id": 9001,
    "policy_code": "WS",
    "policy_version": "v1",
    "asof_eod_date": "2026-02-13",
    "plan_trade_date": "2026-02-14",
    "param_set_id": WS_ACTIVE_EXAMPLE,
    "paramset_hash": "5d8c7315a540cadc4e9a890a1e2a7cc2641d37dde63ee10805eff4dcfaa910ff",
    "plan_hash": "3a4b4299b5a2008cb2f99f4088155e3769421d295045104d89e33e8871b8e7ca",
    "data_batch_hash": "c0949d4c5248ddf77e90dad82993e26f06c934de2744cb91e5962bc3f892c312",
    "run_status": "OK",
    "is_active": "Yes",
    "created_at": "2026-03-04T00:00:00Z"
  },
  "watchlist_plan_items": [
    {
      "plan_item_id": 99001,
      "plan_run_id": 9001,
      "trade_date": "2026-02-14",
      "ticker_id": 101,
      "ticker_code": "AAA",
      "group_semantic": "TOP_PICKS",
      "display_bucket": "SHOW",
      "ranking": 1,
      "score_total": 0.75,
      "score_momentum": 0.5,
      "score_breakout": 1.0,
      "score_volume": 0.5,
      "score_risk": 1.0,
      "entry_ref": 100.0,
      "entry_low": 99.0,
      "entry_high": 101.0,
      "stop_price": 94.0,
      "tp1_price": 112.0,
      "reason_codes_json": ["WS_SEL_PCT"],
      "created_at": "2026-03-04T00:00:00Z"
    }
  ]
}
```

### Example 2 — CONFIRM persisted rows (header + 1 item)

**Source runtime example:** `examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`

```json
{
  "watchlist_confirm_checks": {
    "confirm_check_id": 8001,
    "policy_code": "WS",
    "plan_run_id": 9001,
    "checked_at": "2026-02-14T02:30:00Z",
    "snapshot_ts": "2026-02-14T02:28:30Z",
    "snapshot_age_sec": 90,
    "created_at": "2026-02-14T02:30:00Z"
  },
  "watchlist_confirm_items": [
    {
      "confirm_item_id": 88001,
      "confirm_check_id": 8001,
      "ticker_id": 101,
      "ticker_code": "AAA",
      "label": "CONFIRMED",
      "confirm_reason_codes_json": ["WS_CONFIRM_OK"],
      "created_at": "2026-02-14T02:30:00Z"
    }
  ]
}
```

## Outputs
- Daftar table & invariants yang harus diimplementasikan.

## Failure modes
- Update plan_items (melanggar append-only) => tidak boleh.

---

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
