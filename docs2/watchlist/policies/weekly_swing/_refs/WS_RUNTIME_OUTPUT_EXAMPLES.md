# Runtime Output Examples — Weekly Swing (WS_EOD_PLAN_CONFIRM)

## Purpose
Contoh bentuk output minimum agar implementasi runtime, persistence, dan UI tidak berbeda-beda.

## Notes
- **LOCKED:** File ini berisi dua jenis contoh:
  - **API/UI RESPONSE**: contoh payload yang harus sesuai `WS_RUNTIME_OUTPUT_SCHEMA.md`.
  - **PERSISTENCE RECORD**: contoh bentuk record `plan_run` / `plan_item` untuk audit/debug.
- Contoh **PERSISTENCE RECORD** bukan kontrak UI, dan tidak wajib 1:1 dengan schema API/UI.
- Kontrak payload API/UI yang wajib diikuti ada di `WS_RUNTIME_OUTPUT_SCHEMA.md`.

## B. API/UI Response Examples (LOCKED)

- PLAN example (schema-valid + plan_hash): `../examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`
- CONFIRM example (schema-valid): `../examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`
- PLAN+CONFIRM invariant pair (plan_hash unchanged): `../examples/WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json`

## A. Persistence Examples (Canonical DB Shape)

Catatan:
- Bagian ini harus mengikuti shape persistence canonical sesuai data model dan DDL.
- Jangan memakai flattened/debug fields di bagian ini kecuali field tersebut memang tersimpan di kolom JSON resmi (`run_metrics_json`, `scores_json`, `inputs_json`, `plan_levels_json`, `runtime_json`).

### Example A — plan_run
```json
{
  "plan_run_id": 1001,
  "policy_code": "WS",
  "policy_version": "1.0.0",
  "plan_trade_date": "2026-02-28",
  "asof_eod_date": "2026-02-27",
  "param_set_id": 55,
  "data_batch_hash": "ac919a05cf7a1b567a9029bf67963b6996b3c588014f470dd57a0c1fc493f269",
  "hash_count": 900,
  "missing_required_count": 0,
  "processed_count": 900,
  "eligible_count": 318,
  "run_status": "OK",
  "fail_code": null,
  "run_metrics_json": {
    "top_picks_count": 8,
    "secondary_count": 12,
    "watch_only_count": 34,
    "avoid_count": 264
  },
  "supersedes_plan_run_id": null,
  "is_active": "Yes",
  "created_at": "2026-02-27T18:10:00+07:00"
}
```

### Example B — plan_item
```json
{
  "plan_item_id": 2001,
  "plan_run_id": 1001,
  "policy_code": "WS",
  "trade_date": "2026-02-28",
  "ticker_id": 501,
  "group_semantic": "SECONDARY",
  "selection_reason_code": "WS_GRP_SEC",
  "score_total": 0.788846,
  "display_bucket": "SHOW",
  "scores_json": {
    "score_momentum": 0.546154,
    "score_breakout": 0.750000,
    "score_volume": 1.000000,
    "score_risk": 1.000000
  },
  "inputs_json": {
    "liq_bucket": "STRONG",
    "risk_bucket": "IDEAL"
  },
  "plan_levels_json": {
    "entry_ref": 1020.0000,
    "entry_min": 1009.8000,
    "entry_max": 1030.2000,
    "stop_price": 937.3800,
    "tp1_price": 1143.9300,
    "rr": 1.500000
  },
  "reason_codes_json": [
    "WS_LIQ_STRONG",
    "WS_RISK_IDEAL",
    "WS_GRP_SEC",
    "WS_SHOW"
  ],
  "created_at": "2026-02-27T18:10:01+07:00"
}
```

### Example C — plan_item (WATCH ONLY) 
```json
{
  "plan_item_id": 2002,
  "plan_run_id": 1001,
  "policy_code": "WS",
  "trade_date": "2026-02-28",
  "ticker_id": 502,
  "group_semantic": "WATCH_ONLY",
  "selection_reason_code": "WS_GRP_WATCH",
  "score_total": 0.812541,
  "display_bucket": "SHOW",
  "scores_json": {
    "score_momentum": 0.820000,
    "score_breakout": 0.950000,
    "score_volume": 0.700000,
    "score_risk": 0.600000
  },
  "inputs_json": {
    "breakout_state": "EXTENDED"
  },
  "plan_levels_json": {
    "entry_ref": 1540.0000,
    "entry_min": 1524.6000,
    "entry_max": 1555.4000,
    "stop_price": 1462.0000,
    "tp1_price": 1657.0000,
    "rr": 1.500000
  },
  "reason_codes_json": [
    "WS_BO_EXT",
    "WS_FW_EXT",
    "WS_GRP_WATCH",
    "WS_SHOW"
  ],
  "created_at": "2026-02-27T18:10:02+07:00"
}
```

### Example D — confirm_item persistence record
```json
{
  "confirm_item_id": 2001,
  "confirm_check_id": 1001,
  "ticker_id": 501,
  "label": "CONFIRMED",
  "runtime_json": {
    "last_price": 1028.0000,
    "snapshot_age_sec": 120,
    "drift_pct": 0.007843,
    "turnover_idr": 143960000000,
    "volume_shares": 39330000
  },
  "reason_codes_json": [],
  "created_at": "2026-02-28T09:15:01+07:00"
}
```

### Example E — no_trade run
```json
{
  "plan_run_id": 1002,
  "policy_code": "WS",
  "policy_version": "1.0.0",
  "plan_trade_date": "2026-02-28",
  "asof_eod_date": "2026-02-27",
  "param_set_id": 55,
  "data_batch_hash": "ac919a05cf7a1b567a9029bf67963b6996b3c588014f470dd57a0c1fc493f269",
  "hash_count": 900,
  "missing_required_count": 0,
  "processed_count": 900,
  "eligible_count": 12,
  "run_status": "NO_TRADE",
  "fail_code": "WS_NO_TRADE_MIN_ELIGIBLE",
  "run_metrics_json": {
    "top_picks_count": 0,
    "secondary_count": 0,
    "watch_only_count": 0,
    "avoid_count": 0
  },
  "supersedes_plan_run_id": null,
  "is_active": "Yes",
  "created_at": "2026-02-27T18:11:00+07:00"
}
```
### Example F — failed run
```json
{
  "plan_run_id": 1003,
  "policy_code": "WS",
  "policy_version": "1.0.0",
  "plan_trade_date": "2026-02-28",
  "asof_eod_date": "2026-02-27",
  "param_set_id": 55,
  "data_batch_hash": "0000000000000000000000000000000000000000000000000000000000000000",
  "hash_count": 0,
  "missing_required_count": 900,
  "processed_count": 0,
  "eligible_count": 0,
  "run_status": "FAILED",
  "fail_code": "PLAN_ABORT_DATA_INCOMPLETE",
  "run_metrics_json": {
    "top_picks_count": 0,
    "secondary_count": 0,
    "watch_only_count": 0,
    "avoid_count": 0
  },
  "supersedes_plan_run_id": null,
  "is_active": "Yes",
  "created_at": "2026-02-27T17:45:00+07:00"
}
```

## B. Canonical API / UI Output Examples

### Example A — confirm_item
```json
{
  "run_id": 1001,
  "ticker": "ABCD",
  "label": "CONFIRMED",
  "reasons": []
}
```

### Example B — confirm_item (CAUTION)
```json
{
  "run_id": 1001,
  "ticker": "IJKL",
  "label": "CAUTION",
  "reasons": [
    {
      "severity": "WARN",
      "message": "Spread runtime melebihi batas maksimum.",
    }
  ]
}
```

## C. Non-canonical / Internal Examples
Tambahkan hanya jika memang perlu debug payload atau bentuk internal lain.
Jika tidak perlu, bagian ini boleh dihilangkan.