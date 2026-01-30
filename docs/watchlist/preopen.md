# preopen.md (LOCKED)

Dokumen ini mendefinisikan **output standar** endpoint/fitur `watchlist/preopen` untuk semua policy.
Tujuan: UI/DTO stabil, dan seluruh strategi berbagi schema yang sama. Perbedaan policy hanya pada isi (threshold, ranking, setup, guards).

## Prinsip inti (LOCKED)

1. **PLAN = EOD-only** (data kemarin, `asof_eod_date`). PLAN **tidak boleh** dimodifikasi oleh CONFIRM.
2. **CONFIRM opsional & terpisah**. CONFIRM hanya memberi keputusan live dan rekomendasi harga/eksekusi **bounded** oleh PLAN.
3. **Tidak ada filler/placeholder**. Semua ticker yang tampil sudah lolos `Universe filter` + aturan group masing-masing.
4. **Tidak ada hard cap tersembunyi**. Jumlah item dinamis berbasis kualitas sinyal.
5. **Reasons selalu object**: `{code, message, severity?}`. Message disediakan oleh layer aplikasi (mis. `app/Trade/Explain`).

---

## Output schema (LOCKED)

### Root
```json
{
  "meta": { ... },
  "groups": { ... },
  "recommendations": { ... },
  "confirm": { ... }
}
```

### meta (LOCKED)
```json
{
  "policy": "WEEKLY_SWING|DIVIDEND_SWING|POSITION_TRADE|INTRADAY_LIGHT|NO_TRADE",
  "trade_date": "YYYY-MM-DD",
  "asof_eod_date": "YYYY-MM-DD",
  "canonical_ready": true,
  "flags": [],
  "reasons": []
}
```

- Jika `canonical_ready == false`:
  - `recommendations.items = []` (wajib)
  - `groups.*` tetap dihitung untuk monitoring, dan `flags` memuat `EOD_NOT_READY` + reason `GL_EOD_NOT_READY`.

### groups (LOCKED)
```json
{
  "top_picks":   [ <TickerItem> ],
  "secondary":   [ <TickerItem> ],
  "watch_only":  [ <TickerItem> ],
  "avoid":       [ <TickerAvoidItem> ],
  "no_trade":    [ <TickerNoTradeItem> ]
}
```

### recommendations (LOCKED)
`recommendations` adalah rencana eksekusi beli untuk hari itu (berdasarkan policy aktif).
- Mode A: capital tidak diberikan → lots = null, tapi harga PLAN tetap ada.
- Mode B: capital diberikan → lots integer + estimasi biaya.

```json
{
  "mode": "A_NO_CAPITAL|B_WITH_CAPITAL",
  "capital_idr": 5000000,
  "items": [ <RecommendationItem> ],
  "cash_remaining_idr": 12345
}
```

### confirm (LOCKED)
CONFIRM hanya muncul jika user melakukan input snapshot.
```json
{
  "status": "none|partial|complete",
  "checked_count": 0,
  "by_ticker": {
    "PGAS": <ConfirmResult>
  }
}
```

---

## Common objects (LOCKED)

### Reasons object (LOCKED)
```json
{ "code": "WS_RR_OK", "message": "RR estimasi memenuhi minimum.", "severity": "INFO" }
```

### eod_bar (INFO, recommended)
Ringkas OHLCV EOD untuk konteks UI/audit.
```json
{
  "asof_eod_date": "YYYY-MM-DD",
  "open": 1000, "high": 1050, "low": 990, "close": 1020,
  "prev_close": 1005,
  "gap_pct": 0.0149,
  "volume_shares": 123456789,
  "value_idr": 123456789000
}
```

### TickerItem (groups) (LOCKED)
```json
{
  "ticker": "PGAS",
  "rank": 1,
  "score_total": 0.86,
  "reasons": [ <Reason> ],
  "eod_bar": { ... },
  "ticker_plan": <TickerPlan>
}
```

### TickerPlan (PLAN, EOD-only) (LOCKED)
```json
{
  "setup_type": "PULLBACK|BREAKOUT",
  "plan_entry": 2090,
  "plan_stop": 2020,
  "plan_tp1": 2180,
  "rr_est": 1.29,
  "execution_slices": [ <ExecutionSlicePlan> ]
}
```

### ExecutionSlicePlan (PLAN tranche) (LOCKED)
Harga PLAN wajib ada walau capital kosong.
```json
{
  "n": 1,
  "time": "09:20",
  "lots": 6,
  "plan_limit_price": 2090,
  "plan_price_cap": 2090,
  "plan_price_floor": null,
  "trigger": "PULLBACK: last_live <= plan_entry",
  "reason": { "code": "WS_TRANCHE1_BASE", "message": "Tranche awal.", "severity": "INFO" }
}
```

### RecommendationItem (LOCKED)
```json
{
  "ticker": "PGAS",
  "rank_ref": 1,
  "weight_pct": 0.60,
  "planned_lots": 10,
  "estimated_cost_idr": 2310500,
  "fee_included": true,
  "reasons": [ <Reason> ],
  "setup_type": "PULLBACK",
  "plan_entry": 2090, "plan_stop": 2020, "plan_tp1": 2180,
  "execution_slices": [ <ExecutionSlicePlan> ]
}
```

---

## Harga tranche: PLAN vs CONFIRM (LOCKED)

### PLAN price intent (EOD-only)
- **PULLBACK**:
  - `plan_limit_price = plan_entry`
  - `plan_price_cap = plan_entry` (no-chase)
- **BREAKOUT**:
  - `plan_limit_price = plan_entry`
  - `plan_price_cap = round_up(plan_entry * (1 + CF_MAX_CHASE_PCT_policy))`

Policy hanya boleh override nilai `CF_MAX_CHASE_PCT_policy` dan window/jam eksekusi, bukan rumus.

### CONFIRM recommended price (live, bounded)
Untuk setiap tranche yang dieksekusi, CONFIRM menghasilkan rekomendasi limit price **tanpa mengubah PLAN**.

Aturan deterministik:
- ambil `ask_best = ask1` dari orderbook (Top-N).
- jika `ask_best > plan_price_cap` → `action = WAIT` atau `REJECT`, reason `CF_CHASE_BLOCK`.
- jika lolos:
  - **PULLBACK**: `recommended_limit_price = min(plan_limit_price, ask_best, plan_price_cap)`
  - **BREAKOUT**:  `recommended_limit_price = min(ask_best, plan_price_cap)`

### ConfirmResult (LOCKED)
```json
{
  "checked_at": "09:20:12",
  "decision": "APPROVE|DELAY|REJECT",
  "eligible_now": true,
  "reasons": [ <Reason> ],
  "computed": { "spread_pct": 0.0048, "snapshot_age_sec": 8 },
  "recommended_orders": [ <ConfirmOrder> ]
}
```

### ConfirmOrder (per tranche, LOCKED)
```json
{
  "n": 1,
  "time_window": "09:20",
  "action": "PLACE_LIMIT|WAIT|SKIP",
  "lots": 6,
  "plan_limit_price": 2090,
  "plan_price_cap": 2090,
  "recommended_limit_price": 2090,
  "reasons": [ <Reason> ],
  "inputs_used": { "ask1": 2090, "bid1": 2080, "spread_pct": 0.0048, "snapshot_age_sec": 8 }
}
```

---

## Policy-specific behavior
Detail hard rules, ranking, default execution mode, dan policy overrides ada di `policy/*.md`.
`preopen.md` hanya mengunci schema output + kontrak PLAN/CONFIRM lintas policy.
