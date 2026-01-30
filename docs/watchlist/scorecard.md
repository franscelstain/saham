### Reasons object (LOCKED)
- Semua field `reasons` di seluruh output (groups, recommendations, confirm) **wajib** berupa array object:
  - `code` (string, machine-stable)
  - `message` (string, 1 kalimat, user-facing)
  - `severity` (optional enum: `INFO|WARN|BLOCK`)
- `code` tetap wajib dikirim untuk audit/log.
- `message` disediakan oleh layer aplikasi (mis. `app/Trade/Explain`), bukan oleh dokumen ini.

# Scorecard (CONFIRM) — Live Execution Check vs PLAN (EOD-only)

> **Source of Truth (LOCKED)**
> - `scorecard.md` mengunci: **CONFIRM input**, **guard checks**, **decision**, dan **recommended_orders per tranche**.
> - CONFIRM **tidak boleh** mengubah PLAN (PLAN immutable).
> - Hard/Soft/Risk PLAN berada di `policy/<policy>.md` dan kontrak global di `watchlist.md`.

## Reason objects (LOCKED, user-facing)
Semua `reasons[]` di CONFIRM harus berupa object:
- `code` (stable)
- `message` (1 kalimat, untuk UI)
- `severity` (opsional): `INFO | WARN | BLOCK`

Mapping `code -> message` adalah tanggung jawab layer aplikasi (mis. `app/Trade/Explain`). CONFIRM output wajib menyertakan `message` agar operator tidak perlu menghafal code.

Dokumen ini adalah **kontrak CONFIRM** (intraday/live) untuk membandingkan **PLAN (EOD-only)** dari Watchlist dengan kondisi real-time saat eksekusi.

**Aturan keras:**
- **PLAN tidak boleh diubah** oleh CONFIRM. Output CONFIRM hanya memberi status `eligible_now`, alasan, dan (opsional) rekomendasi “default next action”.
- CONFIRM **tidak boleh** menambahkan ticker baru. Universe CONFIRM hanyalah ticker yang sudah ada di output Watchlist (`groups.*` dan/atau `recommendations`).
- Semua rule di sini harus **deterministik** untuk input yang sama.

---

## 0) Terminologi

- **PLAN**: output Watchlist (EOD-only) untuk tanggal `trade_date` (kemarin).
- **CONFIRM / Execution Check**: evaluasi live per ticker terhadap PLAN + kondisi pasar saat ini.
- **Strategy Run**: artefak tersimpan yang mengikat PLAN + policy + exec_date. Ini menjadi “pembanding” lintas hari.
- **Eligible now**: ticker **boleh dieksekusi sekarang** (untuk tranche yang sedang aktif) menurut rule CONFIRM.

---

## Hardening (anti-debat) (LOCKED)

### 0) Definisi `checked_at` (LOCKED)
- `checked_at` = jam saat operator mengambil **snapshot live** dari Ajaib (refresh yang sama untuk semua angka).
- Format minimal: `HH:MM:SS` (WIB). Contoh: `09:20:12`.
- Semua field LIVE (open/last/book/depth/volume/value) **wajib** berasal dari snapshot yang sama dengan `checked_at`.
- Jika operator mengisi angka dari refresh berbeda → treat sebagai snapshot tidak sinkron → hasil harus `DELAY` (`CF_LIVE_SNAPSHOT_STALE`).

Catatan implementasi (LOCKED):
- Sistem boleh menyimpan `server_checked_at` (timestamp server saat input diterima) untuk menghitung umur snapshot.

Bagian ini mengunci titik yang biasanya memicu perbedaan implementasi.

### 1) Source-of-truth `prev_close_plan` (LOCKED)
- `prev_close_plan` = `close(trade_date)` dari dataset PLAN (EOD kemarin). Ini adalah **source-of-truth** untuk semua perhitungan gap/percent.
- Jika live feed menyediakan `prev_close_live` → simpan untuk audit saja (tidak dipakai perhitungan).
- Jika `prev_close_plan` missing/null/<=0 → CONFIRM tidak boleh menghitung gap; set `eligible_now=false` reason `CF_PLAN_INPUT_MISSING`.

Output audit:
- `live.prev_close_live` (optional, audit-only)
- `plan.prev_close_plan`

### 2) Definisi `spread_pct` & guard zero/missing (LOCKED)
- Jika **depth** tersedia (Top-3/Top-5) → gunakan definisi `spread_pct` pada bagian **Derived book metrics dari depth**.
- Jika depth **tidak** tersedia → gunakan best bid/ask:
  - `mid = (bid_best + ask_best)/2`
  - `spread_pct = (ask_best - bid_best)/mid`
- Jika `bid<=0` atau `ask<=0` atau `ask<bid` → treat live input invalid: `eligible_now=false` reason `CF_LIVE_BOOK_INVALID`.
- Jika `mid<=0` → treat missing: `eligible_now=false` reason `CF_LIVE_BOOK_INVALID`.

Catatan: *Jangan* memakai `last` sebagai denominator agar stabil terhadap spike.

### 3) Definisi `gap_pct` & `chase_pct` (LOCKED)
- `gap_pct = (open - prev_close_plan) / prev_close_plan`
- `chase_pct = (price_ref - plan_entry) / plan_entry`
  - `price_ref = open` pada window 09:00–09:10
  - `price_ref = last` pada window eksekusi (09:20/09:35/10:30)
- Jika `plan_entry<=0` → `eligible_now=false` reason `CF_PLAN_INPUT_MISSING`.

### 4) Breakout entry band (LOCKED)
Untuk setup `BREAKOUT`, entry tidak boleh “ngejar” terlalu jauh dari `plan_entry`.

- `breakout_band_pct = CF_BREAKOUT_BAND_PCT`
- Syarat BREAKOUT approve (selain spread/gap/chase):
  - `last >= plan_entry` **dan** `last <= plan_entry * (1 + breakout_band_pct)`
- Jika `last > plan_entry * (1 + breakout_band_pct)` → `REJECT` reason `CF_BREAKOUT_TOO_EXTENDED`

Default:
- `CF_BREAKOUT_BAND_PCT = 0.004` (0.4%)

### 4.1) Recommended limit price per tranche (LOCKED)

Tujuan: CONFIRM tidak cuma bilang `eligible_now`, tapi juga mengeluarkan **harga limit yang disarankan per tranche ke-N** + alasan, tanpa mengubah PLAN.

Kontrak PLAN (wajib tersedia di `recommendations[].execution.tranches[]` atau `ticker_plan.execution_slices[]`):
- `plan_limit_price` (IDR int) — harga limit yang disarankan (PLAN, immutable).
- `plan_price_cap` (IDR int) — batas maksimum boleh bayar (anti chase).
- `planned_lots` boleh null jika capital missing.

Kontrak CONFIRM (deterministik, bounded by PLAN):
- Jika depth tersedia (Top-3/Top-5) → `ask_best = ask1`.
- Jika depth tidak ada → `ask_best` dari input `ask_best`.
- Jika `ask_best > plan_price_cap` → `action = WAIT/REJECT` + reason `CF_CHASE_BLOCK`.
- Jika lolos:
  - **PULLBACK**: `recommended_limit_price = min(plan_limit_price, ask_best, plan_price_cap)`
  - **BREAKOUT**: `recommended_limit_price = min(ask_best, plan_price_cap)`

Output per tranche (di `recommended_orders[]`):
- `n` (1..N), `action ∈ {PLACE_LIMIT, WAIT, SKIP}`
- `recommended_limit_price` (nullable jika WAIT/SKIP)
- echo `plan_limit_price` + `plan_price_cap` (audit)
- `lots` (copy dari plan tranche jika ada)
- `reasons[]` (CF_*) + `inputs_used` (ask1/bid1/spread/snapshot_age) (array of objects `{ code, message, severity? }`)

### 5) Window semantics (inclusive/exclusive) (LOCKED)
Semua window menggunakan aturan:
- `window_start <= checked_at < window_end`
Contoh: window 09:00–09:10 berarti 09:00:00 inclusive sampai 09:10:00 exclusive.

### 6) Single-source PLAN vs CONFIRM (LOCKED)
- CONFIRM tidak boleh menghitung ulang `plan_entry/stop/tp` dari live.
- Semua field PLAN di CONFIRM harus copy dari output PLAN.
- Perubahan yang diizinkan hanya pada field CONFIRM: `decision`, `eligible_now`, `next_check_at`, `reasons`, dan `execution(tranches)`.

### 7) Stale / unsynced snapshot detector (LOCKED)
Tujuan: mencegah keputusan berdasarkan data live yang belum sinkron (panel price vs order book beda refresh).

Definisi:
- `last` diambil dari panel harga.
- `bid`/`ask` diambil dari order book level-1.
- Snapshot dianggap **stale** jika salah satu kondisi berikut terjadi:
  - `last > ask * (1 + CF_STALE_TOL_PCT)`  (last terlalu tinggi di atas ask)
  - `last < bid * (1 - CF_STALE_TOL_PCT)`  (last terlalu rendah di bawah bid)
  - `checked_at` lebih tua dari `CF_MAX_SNAPSHOT_AGE_SEC` (jika timestamp live tersedia)
Aksi:
- Jika stale → `decision=DELAY`, `eligible_now=false`, reason `CF_LIVE_SNAPSHOT_STALE`, dan wajib re-check pada window berikutnya.

Default:
- `CF_STALE_TOL_PCT = 0.003` (0.3%)
- `CF_MAX_SNAPSHOT_AGE_SEC = 30`

### 8) Retry budget & cooldown (LOCKED)
Tujuan: membatasi loop delay yang tidak berujung.

Definisi:
- `retry_count` dihitung per ticker per hari (dalam satu sesi CONFIRM).
- Jika `decision=DELAY` maka `retry_count += 1`.
- Jika `retry_count > CF_MAX_RETRY_WINDOWS` → `decision=REJECT`, `eligible_now=false`, reason `CF_MAX_RETRY_REACHED`.

Cooldown:
- Setelah DELAY, `next_check_at` harus minimal `checked_at + CF_RETRY_COOLDOWN_SEC`.

Default:
- `CF_RETRY_COOLDOWN_SEC = 30`

## 1) Input utama (source of truth)

### 1.1 Input PLAN (wajib)
Simpan **utuh** JSON output Watchlist (PLAN) sebagai `plan_json`.

Minimal PLAN yang dipakai CONFIRM:
- `meta.trade_date`, `meta.policy`, `meta.flags`, `meta.reasons`
- `groups.top_picks[]`, `groups.secondary[]`, `groups.watch_only[]`, `groups.avoid[]`
- `recommendations[]` (jika ada)

Mapping (LOCKED):
- `entry_trigger` = `TickerPlan.plan.entry`
- `setup_type` = `TickerPlan.setup_type`
- `plan_stop` = `TickerPlan.plan.stop`
- `plan_tp1` = `TickerPlan.plan.tp1`
- `plan_rr_est` = `TickerPlan.plan.rr_est`
- `plan_r` = `TickerPlan.plan.r`
- Mini execution hint:
  - `mini_tranches_pct` dipakai untuk Top Picks/Secondary (pre-open hint)
  - `recommendations.tranches` dipakai sebagai sumber lots (Mode B / capital ada)

### 1.2 Input LIVE (manual dari Ajaib)
Target: input yang **pasti ada di layar** Ajaib dan cukup untuk cek kelayakan entry.

**Level 1 (minimum, wajib):**
Per ticker:
- `checked_at` (timestamp WIB)
- `last`
- `bid`
- `ask`
- `open`
- `prev_close_live` (optional, audit-only)**Level 2 (disarankan):**
- `high`, `low`, `vol`

Jika field Level 1 tidak lengkap → ticker dianggap **NOT_ELIGIBLE** dengan reason `CF_LIVE_INPUT_MISSING`.

---

## 2) Timing windows & guards (LOCKED defaults)

CONFIRM membutuhkan timing/guards untuk menilai “boleh eksekusi sekarang?”. Angka di bawah adalah **default policy-level** (bukan per ticker), supaya implementasi tidak liar.

### 2.1 Default entry/avoid windows (WIB)

Semua window memakai format `HH:MM-HH:MM` dan **inclusive start, exclusive end**.

- **WS/DS/PT**:
  - `entry_windows`: `["09:20-10:15", "13:35-14:15"]`
  - `avoid_windows`: `["09:00-09:20", "11:30-13:30", "15:15-16:00"]`

- **IL**:
  - `entry_windows`: `["09:05-09:45", "13:35-14:10"]`
  - `avoid_windows`: `["09:00-09:05", "11:30-13:30", "15:00-16:00"]`

- **NO_TRADE**:
  - `trade_disabled = true` (selalu)

Catatan:
- CONFIRM **boleh** dijalankan kapan saja (mis. 09:10), tapi `in_entry_window` akan false jika belum masuk window.

### 2.2 Default guards (policy-level)

Semua nilai persentase adalah fraction (0.01 = 1%).

- **WS**:
  - `max_chase_pct = 0.010`
  - `gap_up_block_pct = 0.015`
  - `spread_max_pct = 0.006`

- **DS**:
  - `max_chase_pct = 0.008`
  - `gap_up_block_pct = 0.012`
  - `spread_max_pct = 0.006`

- **PT**:
  - `max_chase_pct = 0.012`
  - `gap_up_block_pct = 0.018`
  - `spread_max_pct = 0.008`

- **IL**:
  - `max_chase_pct = 0.006`
  - `gap_up_block_pct = 0.010`
  - `spread_max_pct = 0.010`  *(lebih toleran spread, tapi chase lebih ketat)*

- **NO_TRADE**:
  - tidak relevan (trade_disabled)

Override (LOCKED):
- Jika PLAN ticker punya flag `GAP_RISK_HIGH` atau `CA_EVENT_NEAR` → turunkan agresivitas:
  - `max_chase_pct = min(max_chase_pct, 0.006)`
  - `gap_up_block_pct = min(gap_up_block_pct, 0.010)`

---

## 3) Universe CONFIRM (ticker mana yang dievaluasi)

Default (LOCKED):
1) Evaluasi semua ticker di `recommendations[]` (paling actionable).
2) Jika `recommendations=[]`, evaluasi `groups.top_picks` lalu `groups.secondary` (untuk default pick).
3) `watch_only/avoid` boleh dievaluasi jika user minta, tapi default **tidak** (biar ringan).

CONFIRM **tidak pernah** mengambil ticker di luar PLAN.

---

## 4) Per-ticker evaluation (LOCKED)

Semua rule di bawah menghasilkan:
- `eligible_now` (bool)
- `reasons[]` (reason code)
- `computed{...}` (angka audit)

### 4.1 Preconditions

Jika salah satu true → `eligible_now=false`:
- `meta.flags` mengandung `EOD_NOT_READY` → reason ``
- `entry_trigger` null → reason ``
- live input L1 missing → reason `CF_LIVE_INPUT_MISSING`

### 4.2 In-window check

- `in_entry_window = checked_at ∈ entry_windows AND checked_at ∉ avoid_windows`

Jika false → `eligible_now=false`, reason ``.

### 4.3 Trade disabled

- `trade_disabled = (policy == NO_TRADE)` atau (opsional) PLAN flag `TRADE_DISABLED_TODAY`

Jika true → `eligible_now=false`, reason ``.

### 4.4 Chase check (anti “ngejar”)

Definisi (LOCKED):
- `chase_pct = max(0, (last - entry_trigger) / entry_trigger)`
- `chase_ok = (last <= entry_trigger * (1 + max_chase_pct))`

Jika `chase_ok=false` → `eligible_now=false`, reason `CF_CHASE_BLOCK`.

Catatan:
- Jika `last < entry_trigger`, chase_pct=0 (bukan negative).

### 4.5 Gap-up block (hari eksekusi)

Definisi (LOCKED):
- `gap_pct = (open - prev_close_plan) / prev_close_plan`
- `gap_ok = (gap_pct <= gap_up_block_pct)`

Jika `gap_ok=false` → `eligible_now=false`, reason `CF_GAP_UP_BLOCK`.

### 4.6 Spread proxy (quality gate)

Definisi (LOCKED):
- `spread_pct = (ask - bid) / mid`
- `spread_ok = (spread_pct <= spread_max_pct)`

Jika `spread_ok=false` → `eligible_now=false`, reason `CF_SPREAD_TOO_WIDE`.

### 4.7 Keputusan akhir

`eligible_now = in_entry_window && !trade_disabled && chase_ok && gap_ok && spread_ok`

---

## 5) Default pick (1 ticker) (LOCKED)

Tujuan: UI/opsional automation membutuhkan satu “default next action”.

Rule (LOCKED):
- Kandidat default diambil dari urutan:
  1) `recommendations[]` sesuai urutan output PLAN
  2) fallback `groups.top_picks`
  3) fallback `groups.secondary`
- Pilih ticker pertama dengan `eligible_now=true`.
- Jika tidak ada → `default_recommendation=null`.

CONFIRM **tidak** boleh promote ticker dari `watch_only/avoid` jadi default kecuali user eksplisit minta.

---

## 6) Output schema (CONFIRM)

### 6.1 Live Check input (disimpan)

```json
{
  "checked_at": "2026-01-27T09:37:00+07:00",
  "tickers": [
    {
      "ticker_code": "JPFA",
      "last": 1235,
      "bid": 1230,
      "ask": 1235,
      "open": 1220,
      "prev_close_plan": 1210,
      "high": 1245,
      "low": 1215,
      "vol": 12000000
    }
  ]
}
```

### 6.2 Execution Check output (hasil evaluasi)

```json
{
  "checked_at": "2026-01-27T09:37:00+07:00",
  "policy": "WEEKLY_SWING",
  "trade_date": "2026-01-26",
  "plan_ref": { "strategy_run_id": "SR-20260126-WEEKLY_SWING" },
  "results": [
    {
      "ticker_code": "JPFA",
      "eligible_now": true,
      "reasons": [],
      "plan": { "entry_trigger": 1230, "stop": 1180, "tp1": 1390, "rr_est": 1.3, "setup_type": "PULLBACK" },
      "computed": { "gap_pct": 0.0083, "spread_pct": 0.0040, "chase_pct": 0.0041 },
      "live": { "last": 1235, "bid": 1230, "ask": 1235, "open": 1220, "prev_close_plan": 1210 },
      "recommended_orders": [
        {
          "n": 1,
          "action": "PLACE_LIMIT",
          "recommended_limit_price": 1235,
          "plan_limit_price": 1230,
          "plan_price_cap": 1235,
          "lots": 10,
          "reasons": [{"code":"CF_PRICE_AT_ASK1_WITHIN_CAP","message":"<message>"}],
          "inputs_used": { "ask_best": 1235, "spread_pct": 0.0040, "snapshot_age_sec": 3 }
        }
      ]
    }
  ],
  "default_recommendation": { "ticker_code": "JPFA", "why": "eligible_now && first_in_priority_order" }
}
```

---

## 7) Reason codes (CONFIRM) (LOCKED, single-source)

Semua reason code CONFIRM harus berasal dari daftar ini (anti typo/duplikasi).

Input & integrity:
- `CF_PLAN_INPUT_MISSING`: field PLAN minimum missing/null (prev_close_plan/plan_entry/plan_price_cap/etc)
- `CF_LIVE_INPUT_MISSING`: field live minimum missing/null
- `CF_LIVE_BOOK_INVALID`: bid/ask invalid (<=0 / ask<bid / mid<=0)
- `CF_LIVE_SNAPSHOT_STALE`: snapshot live tidak sinkron / terlalu tua

Entry guards:
- `CF_CHASE_BLOCK`: ask_best > plan_price_cap (over cap)
- `CF_BREAKOUT_TOO_EXTENDED`: breakout sudah terlalu jauh di atas plan_entry band
- `CF_GAP_UP_BLOCK`: gap-up terlalu besar vs prev_close_plan
- `CF_SPREAD_TOO_WIDE`: spread_pct melebihi batas policy
- `CF_BOOK_TOO_THIN`: depth tidak memadai (jika depth dipakai)
- `CF_LIQUIDITY_DRY`: value/vol live terlalu rendah (opsional guard)

Price intent (audit):
- `CF_PRICE_AT_ASK1_WITHIN_CAP`
- `CF_PRICE_CLAMPED_TO_PLAN_LIMIT`
- `CF_PRICE_CLAMPED_TO_CAP`

Control flow:
- `CF_MAX_RETRY_REACHED`: retry DELAY melebihi batas; no entry hari ini
- `CF_TRANCHE_SKIPPED`: tranche N di-skip karena rule (mis. follow-through fail)

---

## 8) Implementasi (ringkas)

- Ambil PLAN (watchlist output) → simpan sebagai `strategy_run.plan_json`.
- Saat intraday, user input live (Ajaib) → simpan `snapshot_json`.
- Jalankan evaluator deterministik:
  - map PLAN → `entry_trigger`
  - load policy defaults (windows + guards)
  - hitung computed metrics
  - set eligible_now + reasons
- Simpan `result_json` (execution check output).
- Jangan pernah mutasi PLAN.
## 9) Penyimpanan (DB) (disarankan)

Tujuan: membandingkan PLAN vs CONFIRM lintas hari tanpa mengubah PLAN.

### 9.1 `watchlist_strategy_runs`
Simpan output EOD (PLAN).
Kolom minimal:
- `id`
- `trade_date`
- `exec_date`
- `policy`
- `plan_json` (JSON lengkap output watchlist)
- `created_at`

### 9.2 `watchlist_strategy_checks`
Simpan hasil CONFIRM (intraday).
Kolom minimal:
- `id`
- `strategy_run_id` (FK)
- `checked_at`
- `snapshot_json` (Live Check input)
- `result_json` (Execution Check output)
- `created_at`

### 9.3 (Opsional) `watchlist_scorecards`
Simpan metrik hasil (setelah horizon strategy) untuk evaluasi performa.
Kolom minimal:
- `id`
- `strategy_run_id`
- `ticker_code`
- `side` (BUY/SELL)
- `entry_price`, `exit_price`, `pnl_pct`
- `outcome_code` (WIN/LOSS/FLAT/OPEN)
- `evaluated_at`

## Policy Overrides (LOCKED)

Tujuan: membuat CONFIRM tetap deterministik tapi lebih sesuai karakter tiap policy.
Kontrak: rumus & urutan gates **tetap sama**; yang boleh berbeda hanya nilai parameter (angka) per policy.

### Precedence (LOCKED)
1) Jika `policy` memiliki override untuk sebuah parameter → gunakan override itu.
2) Jika tidak ada override → gunakan `Parameter defaults (LOCKED)` (global).

### Override-able parameters (LOCKED)
- `CF_SPREAD_MAX_PCT`
- `CF_MAX_CHASE_PCT`
- `CF_BREAKOUT_BAND_PCT`
- `CF_GAP_UP_BLOCK_PCT`
- `CF_MAX_RETRY_WINDOWS`

### Overrides per policy (LOCKED)

Nilai di bawah ini dipilih agar:
- **IL** paling ketat (intraday cepat, anti spread/anti chase)
- **PT** ketat untuk entry discipline (anti overextended)
- **WS/DS** moderat (masih disiplin, tapi tidak seketat IL)

| Policy | CF_SPREAD_MAX_PCT | CF_MAX_CHASE_PCT | CF_BREAKOUT_BAND_PCT | CF_GAP_UP_BLOCK_PCT | CF_MAX_RETRY_WINDOWS |
|---|---:|---:|---:|---:|---:|
| INTRADAY_LIGHT | 0.005 | 0.008 | 0.003 | 0.025 | 1 |
| WEEKLY_SWING   | 0.006 | 0.010 | 0.004 | 0.030 | 2 |
| DIVIDEND_SWING | 0.006 | 0.010 | 0.004 | 0.030 | 2 |
| POSITION_TRADE | 0.006 | 0.008 | 0.003 | 0.030 | 2 |
| NO_TRADE       | (n/a) | (n/a) | (n/a) | (n/a) | (n/a) |

Catatan NO_TRADE (LOCKED):
- NO_TRADE tidak melakukan eksekusi, jadi CONFIRM hanya monitoring; overrides tidak dipakai.

### Data live input (optional depth, recommended) (LOCKED)
Selain input minimum (`open`, `last`, `bid`, `ask`, `volume/value`, `checked_at`), operator **boleh** memasukkan depth agar keputusan lebih stabil dari fluktuasi best price.

Format depth (pilih salah satu, LOCKED):
- Top-3: `bid1,bid2,bid3` + `bid_lots1,bid_lots2,bid_lots3` dan `ask1,ask2,ask3` + `ask_lots1,ask_lots2,ask_lots3`
- Top-5: `bid1..bid5` + `bid_lots1..bid_lots5` dan `ask1..ask5` + `ask_lots1..ask_lots5`

Kontrak snapshot (LOCKED):
- Semua angka depth harus berasal dari **refresh yang sama** dengan `last/open`.
- Jika tidak yakin sinkron → gunakan stale detector; hasilnya harus `DELAY`.

Kontrak input bid/ask vs depth (LOCKED):
- Jika operator mengisi **depth** (Top-3/Top-5), maka `bid_best` dan `ask_best` **wajib** diambil dari `bid1` dan `ask1` (derived).
  Jangan input `bid_best/ask_best` terpisah (untuk menghindari mismatch).
- Jika operator **tidak** mengisi depth, maka wajib mengisi `bid_best` dan `ask_best`.

### Derived book metrics dari depth (LOCKED)
Jika depth tersedia, hitung metrik berikut (deterministik):

Best:
- `bid_best = bid1`
- `ask_best = ask1`

Cumulative lots:
- `cum_bid_lots_N = sum(bid_lots1..bid_lotsN)`
- `cum_ask_lots_N = sum(ask_lots1..ask_lotsN)`

VWAP level-1..N (opsional, stabil untuk mid):
- `bid_vwap_N = sum(bid_i * bid_lots_i) / cum_bid_lots_N`
- `ask_vwap_N = sum(ask_i * ask_lots_i) / cum_ask_lots_N`
- `mid_vwap_N = (bid_vwap_N + ask_vwap_N)/2`

Spread untuk guards (LOCKED):
- Jika depth tersedia → gunakan `mid_vwap_N` sebagai denominator spread:
  - `spread_pct = (ask_best - bid_best) / mid_vwap_N`
- Jika depth tidak tersedia → fallback ke mid best:
  - `mid = (bid_best + ask_best)/2`
  - `spread_pct = (ask_best - bid_best)/mid`

### Depth guards (optional, LOCKED)
Tujuan: hindari entry pada order book yang tipis (risk slippage tinggi), terutama untuk IL.

Jika depth tersedia:
- `cum_bid_lots_N >= CF_MIN_CUM_BID_LOTS_N` dan `cum_ask_lots_N >= CF_MIN_CUM_ASK_LOTS_N`
Jika gagal:
- `decision=DELAY`, `eligible_now=false`, reason `CF_BOOK_TOO_THIN`

Default thresholds (LOCKED, berlaku jika guard diaktifkan):
- `CF_MIN_CUM_BID_LOTS_3 = 2_000`
- `CF_MIN_CUM_ASK_LOTS_3 = 2_000`
- `CF_MIN_CUM_BID_LOTS_5 = 3_000`
- `CF_MIN_CUM_ASK_LOTS_5 = 3_000`

Catatan (LOCKED):
- Depth guards bersifat **optional**. Jika operator tidak input depth → guard ini tidak dievaluasi.
- Jika diaktifkan, depth guards dievaluasi setelah `CF_LIVE_SNAPSHOT_STALE` dan sebelum `CF_SPREAD_TOO_WIDE`.

### Policy-specific enablement (LOCKED)
Depth guards dianjurkan **aktif** untuk:
- `INTRADAY_LIGHT` (default: ON jika depth tersedia)

Untuk policy lain:
- `WEEKLY_SWING`, `DIVIDEND_SWING`, `POSITION_TRADE` default: OFF (evaluasi hanya spread/chase/gap), kecuali operator ingin mengaktifkan.
