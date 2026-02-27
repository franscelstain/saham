# Runtime Output Examples — Weekly Swing (WS_EOD_PLAN_CONFIRM)

## Purpose
Contoh bentuk output minimum agar implementasi runtime, persistence, dan UI tidak berbeda-beda.

## Notes
- **LOCKED:** File ini berisi dua jenis contoh:
  - **API/UI RESPONSE**: contoh payload yang harus sesuai `WS_RUNTIME_OUTPUT_SCHEMA.md`.
  - **PERSISTENCE RECORD**: contoh bentuk record `plan_run` / `plan_item` untuk audit/debug.
- Contoh **PERSISTENCE RECORD** bukan kontrak UI, dan tidak wajib 1:1 dengan schema API/UI.
- Kontrak payload API/UI yang wajib diikuti ada di `WS_RUNTIME_OUTPUT_SCHEMA.md`.

## Example A — plan_run
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

## Example B — plan_item
```json
{
  "run_id": 1001,
  "ticker_id": "ABCD",
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
  "display_status": "SHOW",
  "reason_codes": [
    "WS_LIQ_OK",
    "WS_ATR_OK",
    "WS_SECONDARY_Q_PASS"
  ],
  "created_at": "2026-02-27T18:10:01+07:00"
}
```

## Example C — plan_item (WATCH ONLY) 
```json
{
  "run_id": 1001,
  "ticker_id": "EFGH",
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
  "display_status": "SHOW",
  "reason_codes": [
    "WS_BREAKOUT_EXTENDED",
    "WS_FORCE_WATCH_ONLY"
  ],
  "created_at": "2026-02-27T18:10:02+07:00"
}
```

## Example D — confirm_check
```json
{
  "confirm_id": 2001,
  "run_id": 1001,
  "ticker_id": "ABCD",
  "snapshot_ts": "2026-02-28T09:15:00+07:00",
  "last_price": 1028.0000,
  "snapshot_age_sec": 120,
  "drift_pct": 0.007843,
  "spread_pct": 0.006000,
  "confirm_result": "PASS",
  "reason_codes": [
    "WS_CONFIRM_PASS"
  ],
  "created_at": "2026-02-28T09:15:01+07:00"
}
```

## Example E — confirm_item
```json
{
  "run_id": 1001,
  "ticker_id": "ABCD",
  "group_semantic": "SECONDARY",
  "confirm_label": "PASS",
  "last_price": 1028.0000,
  "drift_pct": 0.007843,
  "spread_pct": 0.006000,
  "action_hint": "ok",
  "reason_codes": [
    "WS_CONFIRM_PASS"
  ]
}
```

## Example F — confirm_item (CAUTION)
```json
{
  "run_id": 1001,
  "ticker_id": "IJKL",
  "group_semantic": "TOP_PICKS",
  "confirm_label": "CAUTION",
  "last_price": 2115.0000,
  "drift_pct": 0.012500,
  "spread_pct": 0.011000,
  "action_hint": "delay",
  "reason_codes": [
    "WS_SPREAD_WIDE"
  ]
}
```

## Example G — no_trade run
```json
{
  "run_id": 1002,
  "policy_code": "WS",
  "run_type": "PLAN",
  "trade_date": "2026-02-27",
  "plan_trade_date": "2026-02-28",
  "param_set_id": 55,
  "run_status": "NO_TRADE",
  "data_batch_hash": "ac919a05cf7a1b567a9029bf67963b6996b3c588014f470dd57a0c1fc493f269",
  "eligible_count": 12,
  "top_picks_count": 0,
  "secondary_count": 0,
  "watch_only_count": 0,
  "avoid_count": 0,
  "reason_codes": [
    "WS_NO_TRADE_MIN_ELIGIBLE"
  ],
  "created_at": "2026-02-27T18:11:00+07:00"
}
```
## Example H — abort run
```json
{
  "run_id": 1003,
  "policy_code": "WS",
  "run_type": "PLAN",
  "trade_date": "2026-02-27",
  "plan_trade_date": "2026-02-28",
  "param_set_id": 55,
  "run_status": "ABORT",
  "data_batch_hash": "0000000000000000000000000000000000000000000000000000000000000000",
  "eligible_count": 0,
  "top_picks_count": 0,
  "secondary_count": 0,
  "watch_only_count": 0,
  "avoid_count": 0,
  "reason_codes": [
    "WS_EOD_INCOMPLETE"
  ],
  "created_at": "2026-02-27T17:45:00+07:00"
}
```