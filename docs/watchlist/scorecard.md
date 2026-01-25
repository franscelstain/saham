# Execution & Evaluation Workflow (EOD → Eksekusi → Scorecard)

Dokumen ini menjelaskan **langkah kerja end-to-end** untuk memakai output Watchlist (EOD) sebagai **rencana eksekusi**, lalu melakukan **cek live** (manual input dari Ajaib) dan menghitung **indikator keberhasilan**.

> Prinsip utama: **Watchlist = Plan Generator (EOD)**.  
> Eksekusi & evaluasi **terpisah modulnya**, tapi **terhubung** lewat `strategy_run` yang disimpan.

---

## 0) Terminologi

- **Strategy / Policy**: satu pendekatan (contoh: `WEEKLY_SWING`, `DIVIDEND_SWING`, `INTRADAY_LIGHT`, `POSITION_TRADE`, `NO_TRADE`).
- **Strategy Run (EOD Plan)**: hasil EOD untuk satu policy pada satu tanggal, berisi kandidat ticker + rules entry/slices/guards.
- **Live Check**: pengecekan real-time saat jam eksekusi (manual input data dari Ajaib).
- **Scorecard**: ringkasan metrik keberhasilan (feasible / fill / outcome).

---

## 1) Batasan & Tujuan Output

### 1.1 Watchlist bukan listing semua ticker
Output watchlist yang dipublish harus berupa **kandidat**:
- `top_picks`: prioritas eksekusi.
- `secondary`: kandidat cadangan untuk eksekusi (fallback).
- `watch_only`: kandidat monitoring (posisi existing / near-eligible / guard situasional).

Ticker yang tidak relevan **DROP** (tidak dipublish) walaupun diproses internal.

### 1.2 Default 1 rekomendasi
Default rekomendasi **boleh** ditetapkan dari:
- `top_picks[0]` yang **feasible_now == true**, atau
- fallback ke `secondary` (ranking tertinggi yang feasible_now).

---

## 2) Arsitektur Modul (Disarankan)

### 2.1 Watchlist (EOD)
**Tugas**: membentuk *plan*.
- Input: data market EOD + indikator + status harian.
- Output: `strategy_runs[]` (per policy) berisi kandidat + aturan.

### 2.2 Execution Check (Intraday)
**Tugas**: validasi plan vs kondisi live.
- Input: `strategy_run` + snapshot live dari Ajaib (manual).
- Output: `eligible_now / feasible_now`, alasan, rekomendasi default 1.

### 2.3 Scorecard (After market / T+N)
**Tugas**: menilai kualitas plan.
- Input: `strategy_run` + log live checks + hasil harian (high/low/close) atau hasil transaksi.
- Output: metrik `feasible_rate`, `fill_rate`, `outcome_rate`.

---

## 3) Data yang Harus Disimpan (EOD Plan)

Simpan **per policy** (satu `strategy_run` per policy per trade_date).

### 3.1 Wajib disimpan (minimum)
- `trade_date` (tanggal basis EOD)
- `exec_date` (hari eksekusi; default = next trading day)
- `policy`
- `groups.top_picks[]`, `groups.secondary[]`, `groups.watch_only[]`
- Per ticker kandidat:
  - `ticker`
  - `score` dan `rank` (ordering deterministik)
  - `entry` (trigger / band)
  - `timing.entry_windows[]`
  - `timing.avoid_windows[]`
  - `timing.trade_disabled` + alasan/reasons
  - `slices` + `slice_pct` (kalau digunakan)
  - `guards` yang berlaku (contoh: gap_up_block_pct, max_chase_pct)
  - `reason_codes[]` (audit kenapa masuk group)

### 3.2 Disarankan (untuk audit)
- `meta.generated_at` (RFC3339, WIB)
- `meta.data_coverage` / `meta.signal_age_days`
- `allocations` (budget/slice budget per ticker bila ada)

---

## 4) Data Live yang Diambil dari Ajaib (Manual Input)

Target: input yang **pasti ada di layar** Ajaib, dan cukup untuk cek kelayakan entry.

### 4.1 Minimal (Level 1 — cukup untuk “eligible sekarang?”)
Per ticker:
- `checked_at` (jam WIB saat kamu cek)
- `last` (harga terakhir)
- `bid` (best bid)
- `ask` (best ask)
- `open` (harga pembukaan hari ini)
- `prev_close` (penutupan kemarin)

> Kalau Ajaib tidak menampilkan `prev_close`, ambil dari ringkasan chart 1D / data close kemarin.

### 4.2 Disarankan (Level 2 — lebih akurat)
Tambahan:
- `high` dan `low` (range harian)
- `vol` (volume hari ini)

### 4.3 Intraday-heavy (Level 3 — untuk fill slice / outcome intraday)
Tambahan:
- snapshot berkala (mis. setiap 5–15 menit) untuk ticker yang sedang dipantau,
- atau setidaknya `high/low` update per jam check.

---

## 5) Aturan Evaluasi Live (Feasible / Eligible)

Evaluasi ini dilakukan **per ticker**, lalu diringkas **per strategy**.

### 5.1 In-window check
- `in_entry_window = now ∈ timing.entry_windows AND now ∉ timing.avoid_windows`

### 5.2 Trade disabled
- Jika `timing.trade_disabled == true` → default `eligible_now = false`  
  *kecuali* policy memang mode `CARRY_ONLY` dan ticker `has_position == true`.

### 5.3 Chase check (harga sudah “lari”)
- `chase_ok = last <= entry_trigger * (1 + max_chase_pct)`
- Jika `chase_ok == false` → `eligible_now = false` (avoid entry chasing)

### 5.4 Gap-up block check (hari eksekusi)
- `gap_pct = (open - prev_close) / prev_close`
- Jika `gap_pct > gap_up_block_pct` → `eligible_now = false`

### 5.5 Spread proxy (eksekusi jelek)
- `spread_pct = (ask - bid) / last`
- Jika `spread_pct > spread_max_pct` → `eligible_now = false` atau downgrade (tergantung policy)

### 5.6 Keputusan akhir (per ticker)
- `eligible_now = in_entry_window && !trade_disabled && chase_ok && gap_ok && spread_ok`

Catatan:
- Untuk policy `NO_TRADE`, `top_picks=[]` dan `secondary=[]` selalu; hanya `watch_only` terbatas.

---

## 6) Metrik Keberhasilan

### 6.1 Feasible Rate (real-time)
Definisi: dari kandidat yang dievaluasi, berapa yang `eligible_now == true` pada jam check.

- Per strategy:
  - `feasible_rate = eligible_true / evaluated_candidates`
- Per hari:
  - agregasi dari beberapa checkpoint (mis. 09:20, 10:00, 13:40)

### 6.2 Fill Rate (hari itu)
Definisi: seberapa banyak slice entry yang benar-benar “kesentuh” oleh harga.

Butuh minimal:
- `high/low` harian atau intraday range.

Contoh:
- slices=3 → entry1/entry2/entry3
- `fill_rate = filled_slices / total_slices`

### 6.3 Outcome Rate (horizon strategy)
Definisi: plan menghasilkan hasil sesuai rule exit (TP/SL/time stop).

Butuh:
- log transaksi atau data high/low/close hingga exit horizon.

---

## 7) Checklist Operasional Harian (Ringkas & Realistis)

### 7.1 Malam (setelah EOD)
1) Jalankan Watchlist EOD
2) Pastikan output publish **kandidat saja** (cap top/secondary/watch_only)
3) Simpan `strategy_runs` ke DB (payload JSON)

### 7.2 Hari eksekusi (intraday)
Lakukan 2–3 checkpoint saja (contoh):
- 09:20 (open window)
- 10:00 (konfirmasi)
- 13:40 (session 2)

Di setiap checkpoint:
1) Ambil data Ajaib per ticker kandidat (minimal level 1)
2) Jalankan **Execution Check**
3) Ambil default 1 rekomendasi (yang feasible_now dan ranking tertinggi)
4) Simpan `strategy_check` (snapshot + hasil)

### 7.3 Setelah close / besok pagi
1) Hitung scorecard (feasible + fill)
2) (Opsional) outcome jika sudah ada rule exit / transaksi.

---

## 8) Template JSON (untuk simpan & cek)

### 8.1 Strategy Run (disimpan dari EOD)
```json
{
  "trade_date": "2026-01-26",
  "exec_date": "2026-01-27",
  "policy": "WEEKLY_SWING",
  "meta": { "generated_at": "2026-01-26T20:15:00+07:00" },
  "groups": {
    "top_picks": [
      {
        "ticker": "JPFA",
        "score": 86,
        "rank": 1,
        "entry_trigger": 1230,
        "guards": { "max_chase_pct": 0.01, "gap_up_block_pct": 0.015, "spread_max_pct": 0.004 },
        "timing": { "trade_disabled": false, "entry_windows": ["09:20-10:15","13:35-14:15"], "avoid_windows": ["11:30-13:30","15:15-close"] },
        "slices": 2,
        "slice_pct": [0.6, 0.4],
        "reason_codes": ["WS_TREND_ALIGN_OK","WS_RR_OK","WS_LIQ_OK"]
      }
    ],
    "secondary": [],
    "watch_only": []
  }
}
```

### 8.2 Live Check (input manual dari Ajaib)
```json
{
  "checked_at": "2026-01-27T09:37:00+07:00",
  "tickers": [
    { "ticker": "JPFA", "last": 1235, "bid": 1230, "ask": 1235, "open": 1220, "prev_close": 1210, "high": 1245, "low": 1215, "vol": 12000000 }
  ]
}
```

### 8.3 Output Execution Check (hasil evaluasi)
```json
{
  "checked_at": "2026-01-27T09:37:00+07:00",
  "results": [
    {
      "ticker": "JPFA",
      "eligible_now": true,
      "flags": [],
      "computed": { "gap_pct": 0.0083, "spread_pct": 0.0040, "chase_pct": 0.0041 },
      "notes": "In-window, chase OK, gap OK"
    }
  ],
  "default_recommendation": { "ticker": "JPFA", "why": "eligible_now && rank=1" }
}
```

---

## 9) DB Tables (opsional tapi disarankan)

Jika kamu ingin audit lengkap dan bisa menghitung scorecard otomatis:

### 9.1 `watchlist_strategy_runs`
- `id` (uuid)
- `trade_date` (date)
- `exec_date` (date)
- `policy` (varchar)
- `payload_json` (jsonb / longtext)
- `created_at`

### 9.2 `watchlist_strategy_checks`
- `id` (uuid)
- `strategy_run_id` (uuid, FK)
- `checked_at` (timestamp)
- `snapshot_json` (jsonb)
- `result_json` (jsonb)
- `created_at`

### 9.3 (Opsional) `watchlist_scorecards`
- `strategy_run_id`
- `feasible_rate`
- `fill_rate`
- `outcome_rate`
- `notes`

---

## 10) Keputusan Desain Penting

### 10.1 Kenapa modul terpisah
- Menghindari output watchlist jadi “dumping ground”
- Memudahkan test (EOD plan deterministik, live check deterministik)
- Membuat evaluasi lebih adil (plan disimpan, live dibandingkan dengan plan)

### 10.2 Kalau ingin cek 1 ticker di luar strategi
Buat fitur/endpoint terpisah: **Single Ticker Evaluation**:
- input: ticker + asumsi policy template + data Ajaib
- output: eligible_now + alasan
- (opsional) simpan ke `ticker_check_logs`

---

## 11) Quick Start (paling cepat tanpa coding tambahan)
1) Simpan output EOD (strategy_run JSON) untuk hari ini
2) Besok saat eksekusi, input manual data Ajaib (level 1) untuk kandidat top+secondary
3) Hitung eligible_now per ticker dan pilih default 1
4) Simpan minimal: jam cek + last/open/prev_close/bid/ask + hasil eligible

Selesai.
