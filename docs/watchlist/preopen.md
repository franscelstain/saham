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


## Catatan score_total

- `score_total` adalah float **range [0..1]** (bukan 0..100).
- Jika UI mau tampilkan persentase, UI yang mengalikan `score_total*100`.

## TickerItem

Catatan: `score_total` adalah float dalam range **[0..1]**.

```json
{
  "ticker": "BBRI",
  "rank": 1,
  "score_total": 0.745,
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
        "lots": null,
        "plan_limit_price": 0,
        "plan_price_cap": 0,
        "plan_price_floor": null,
        "trigger": "BREAKOUT: last_live >= plan_entry",
        "reason": {"code":"WS_TRANCHE1","message":"...","severity":"INFO"}
      }
    ]
  }
}
```

> **Catatan (ANTI SALAH TAFSIR)**
> - `ticker_plan.execution_slices[]` boleh selalu ada sebagai **template rencana eksekusi**.
> - Field `execution_slices[].lots` **boleh `null`** jika ticker **bukan** bagian dari `recommendations` (atau mode A tanpa capital).
> - Hanya untuk item di `recommendations.items[]`, `execution_slices[].lots` harus berupa angka lot yang sudah dialokasikan.

## Recommendations

> **Catatan (ANTI SALAH TAFSIR)**
> - `weight_pct` adalah **fraction 0..1** (contoh: 0.20 = 20%). UI boleh menampilkan dalam persen, tapi payload tetap 0..1.

```json
{
  "mode": "A_NO_CAPITAL|B_WITH_CAPITAL",
  "capital_idr": 5000000,
  "items": [
    {
      "ticker": "BBRI",
      "rank_ref": 1,
      "weight_pct": 0.20,
      "planned_lots": 1,
      "estimated_cost_idr": 1000000,
      "fee_included": true,
      "reasons": [{"code":"...","message":"...","severity":"INFO"}],
      "setup_type": "BREAKOUT|PULLBACK",
      "plan_entry": 0,
      "plan_stop": 0,
      "plan_tp1": 0,
      "execution_slices": [
        {
          "n": 1,
          "time": "09:20",
          "lots": 1,
          "plan_limit_price": 0,
          "plan_price_cap": 0,
          "plan_price_floor": null,
          "trigger": "BREAKOUT: last_live >= plan_entry",
          "reason": {"code":"WS_TRANCHE1","message":"...","severity":"INFO"}
        }
      ]
    }
  ],
  "cash_remaining_idr": 0
}
```

Catatan:
- `planned_lots` dan `estimated_cost_idr` dapat `null` pada mode `A_NO_CAPITAL`.
- `cash_remaining_idr` dapat `null` jika `canonical_ready = false`, atau jika sistem tidak memiliki angka `cash_remaining` dari engine.

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
      "retry": {
        "retry_count": 0,
        "max_retry_windows": 0
      },
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

