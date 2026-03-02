# Runtime Output Examples — Weekly Swing (WS_EOD_PLAN_CONFIRM)

## Purpose
Contoh bentuk output minimum agar implementasi runtime, persistence, dan UI tidak berbeda-beda.

## Notes
- **LOCKED:** File ini berisi dua jenis contoh:
  - **API/UI RESPONSE**: contoh payload yang harus sesuai `WS_RUNTIME_OUTPUT_SCHEMA.md`.
  - **PERSISTENCE RECORD**: contoh bentuk record `plan_run` / `plan_item` untuk audit/debug.
- Contoh **PERSISTENCE RECORD** bukan kontrak UI, dan tidak wajib 1:1 dengan schema API/UI.
- Kontrak payload API/UI yang wajib diikuti ada di `WS_RUNTIME_OUTPUT_SCHEMA.md`.

## A. Persistence Examples

### Example A — plan_run
```json
{
  "run_id": 1001,
  "policy_code": "WS",
  "run_type": "PLAN",
  "trade_date": "2026-02-27",
  "plan_trade_date": "2026-02-28",
  "param_set_id": 55,
  "run_status": "OK",
  "data_batch_hash": "d64dd6641f446317be3e62a789246210aadfaefea7ba3888b5b0a23528288fd3",
  "eligible_count": 87,
  "top_picks_count": 5,
  "secondary_count": 10,
  "watch_only_count": 14,
  "avoid_count": 58,
  "created_at": "2026-02-27T18:10:00+07:00"
}
```

### Example B — plan_item
```json
{
  "run_id": 1001,
  "ticker": "ABCD",
  "group_semantic": "SECONDARY",
  "score_total": 0.788846,
  "score_momentum": 0.546154,
  "score_breakout": 0.750000,
  "score_volume": 1.000000,
  "score_risk": 1.000000,
  "entry_ref": 1020.0000,
  "entry_min": 1009.8000,
  "entry_max": 1030.2000,
  "stop_price": 937.3800,
  "tp1_price": 1143.9300,
  "rr": 1.500000,
  "display_bucket": "SHOW",
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
  "run_id": 1001,
  "ticker": "EFGH",
  "group_semantic": "WATCH_ONLY",
  "score_total": 0.812541,
  "score_momentum": 0.820000,
  "score_breakout": 0.950000,
  "score_volume": 0.700000,
  "score_risk": 0.600000,
  "entry_ref": 1540.0000,
  "entry_min": 1524.6000,
  "entry_max": 1555.4000,
  "stop_price": 1462.0000,
  "tp1_price": 1657.0000,
  "rr": 1.500000,
  "display_bucket": "SHOW",
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
  "ticker": "ABCD",
  "label": "CONFIRMED",
  "reason_codes_json": [],
  "created_at": "2026-02-28T09:15:01+07:00"
}
```

### Example E — no_trade run
```json
{
  "run_id": 1002,
  "policy_code": "WS",
  "run_type": "PLAN",
  "trade_date": "2026-02-27",
  "plan_trade_date": "2026-02-28",
  "param_set_id": 55,
  "run_status": "NO_TRADE",
  "fail_code": "WS_NO_TRADE_MIN_ELIGIBLE",
  "data_batch_hash": "ac919a05cf7a1b567a9029bf67963b6996b3c588014f470dd57a0c1fc493f269",
  "eligible_count": 12,
  "top_picks_count": 0,
  "secondary_count": 0,
  "watch_only_count": 0,
  "avoid_count": 0,
  "created_at": "2026-02-27T18:11:00+07:00"
}
```
### Example F — failed run
```json
{
  "run_id": 1003,
  "policy_code": "WS",
  "run_type": "PLAN",
  "trade_date": "2026-02-27",
  "plan_trade_date": "2026-02-28",
  "param_set_id": 55,
  "run_status": "FAILED",
  "fail_code": "PLAN_ABORT_DATA_INCOMPLETE",
  "data_batch_hash": "0000000000000000000000000000000000000000000000000000000000000000",
  "eligible_count": 0,
  "top_picks_count": 0,
  "secondary_count": 0,
  "watch_only_count": 0,
  "avoid_count": 0,
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
      "code": "WS_SPR_WIDE",
      "severity": "WARN",
      "message": "Spread runtime melebihi batas maksimum.",
      "payload": {
        "spread_pct": 0.011000,
        "spread_max_pct": 0.010000
      }
    }
  ]
}
```

## C. Non-canonical / Internal Examples
Tambahkan hanya jika memang perlu debug payload atau bentuk internal lain.
Jika tidak perlu, bagian ini boleh dihilangkan.