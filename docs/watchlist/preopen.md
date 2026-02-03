# WATCHLIST Preopen Contract (LOCKED)

Dokumen ini mendefinisikan output JSON dari endpoint `GET /watchlist/preopen`.

## Catatan tanggal

Kontrak memakai 2 tanggal:
- `meta.trade_date` = tanggal eksekusi (hari bursa yang sedang/akan dieksekusi).
- `meta.asof_eod_date` = tanggal EOD yang dipakai untuk scoring/ranking (biasanya hari bursa sebelumnya).

## Top-level schema

```json
{
  "meta": {
    "policy": "WEEKLY_SWING|DIVIDEND_SWING|POSITION_TRADE|INTRADAY_LIGHT|NO_TRADE",
    "trade_date": "YYYY-MM-DD",
    "asof_eod_date": "YYYY-MM-DD",
    "canonical_ready": true,
    "flags": ["EOD_NOT_READY"],
    "reasons": [{"code":"GL_EOD_NOT_READY","message":"...","severity":"ERROR"}]
  },
  "groups": {
    "top_picks": [/* TickerItem */],
    "secondary": [/* TickerItem */],
    "watch_only": [/* TickerItem */],
    "avoid": [],
    "no_trade": []
  },
  "recommendations": {/* Recommendations */},
  "confirm": {/* Confirm */}
}
```

## TickerItem

```json
{
  "ticker": "BBRI",
  "rank": 1,
  "score_total": 74.5,
  "reasons": [{"code":"MOM_RSI_OK","message":"...","severity":"INFO"}],
  "eod_bar": {
    "asof_eod_date": "YYYY-MM-DD",
    "open": 0,
    "high": 0,
    "low": 0,
    "close": 0,
    "prev_close": 0,
    "gap_pct": 0.0123,
    "volume_shares": 0,
    "value_idr": 0
  },
  "ticker_plan": {
    "setup_type": "BREAKOUT|PULLBACK",
    "plan_entry": 0,
    "plan_stop": 0,
    "plan_tp1": 0,
    "rr_est": 1.5,
    "execution_slices": [
      {
        "n": 1,
        "time": "09:20",
        "lots": 1,
        "plan_limit_price": 0,
        "plan_price_cap": 0
      }
    ]
  }
}
```

## Recommendations

```json
{
  "mode": "A_NO_CAPITAL|B_WITH_CAPITAL",
  "capital_idr": 5000000,
  "items": [
    {
      "ticker": "BBRI",
      "action": "BUY|WAIT|SKIP",
      "alloc_idr": 1000000,
      "lots": 1,
      "plan_entry": 0,
      "plan_stop": 0,
      "plan_tp1": 0,
      "setup_type": "BREAKOUT|PULLBACK",
      "execution_slices": [
        {
          "n": 1,
          "time": "09:20",
          "lots": 1,
          "plan_limit_price": 0,
          "plan_price_cap": 0
        }
      ],
      "reasons": [{"code":"...","message":"...","severity":"INFO"}]
    }
  ],
  "cash_remaining_idr": 0
}
```

Catatan:
- `lots` dan `alloc_idr` dapat `null` pada mode `A_NO_CAPITAL`.
- `cash_remaining_idr` bisa `null` jika `canonical_ready = false`.

## Confirm

`confirm` digunakan untuk menilai kelayakan eksekusi intraday berdasarkan snapshot LIVE dan guard dari `docs/watchlist/scorecard.md`.

```json
{
  "status": "none|partial|complete",
  "checked_count": 0,
  "by_ticker": {
    "BBRI": {
      "checked_at": "09:20:12",
      "decision": "APPROVE|DELAY|REJECT",
      "eligible_now": true,
      "next_check_at": "09:20:42",
      "reasons": [{"code":"CF_OK","message":"...","severity":"INFO"}],
      "computed": {
        "gap_pct": 0.0123,
        "spread_pct": 0.0045,
        "chase_pct": 0.0030,
        "snapshot_age_sec": 8
      },
      "recommended_orders": [
        {
          "n": 1,
          "time_window": "09:20",
          "action": "PLACE_LIMIT|WAIT|SKIP",
          "lots": 1,
          "plan_limit_price": 0,
          "plan_price_cap": 0,
          "recommended_limit_price": 0,
          "reasons": [{"code":"CF_PRICE_AT_ASK1_WITHIN_CAP","message":"...","severity":"INFO"}],
          "inputs_used": {
            "ask_best": 0,
            "bid_best": 0,
            "spread_pct": 0.0045,
            "snapshot_age_sec": 8
          }
        }
      ]
    }
  }
}
```

Aturan praktis:
- `decision=APPROVE` hanya jika ada minimal 1 tranche `action=PLACE_LIMIT`.
- `decision=DELAY` dipakai untuk kondisi yang bisa membaik (contoh: snapshot stale atau chase melewati cap).
- `decision=REJECT` dipakai untuk kondisi yang tidak layak dieksekusi saat ini (contoh: gap-up terlalu besar, spread terlalu lebar, breakout overextended).

