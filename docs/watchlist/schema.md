# Watchlist Scorecard Schema (TradeAxis) — Detailed Column Semantics

Dokumen ini menjelaskan schema yang dipakai fitur **Watchlist Scorecard**: penyimpanan **plan EOD**, **checkpoint intraday** (manual/otomatis), dan **rekap scorecard** untuk evaluasi strategi.

Fokus dokumen ini bukan hanya “arti kolom”, tapi juga:
- **Sumber data** (job/command/user input)
- **Aturan hitung/derivasi**
- **Invariants** (harus selalu benar)
- **Kapan di-update** (EOD vs intraday vs after-close)
- **Kegunaan praktis** (UI/report/debug)

---

## Prinsip desain

- **Plan deterministik**: output Watchlist EOD disimpan sebagai snapshot (`watchlist_strategy_runs.payload_json`) supaya evaluasi bisa diulang (replay) walau logic berubah.
- **Checkpoint granular**: setiap kali kamu cek (09:20/10:00/13:40) simpan snapshot & hasil evaluasi (`watchlist_strategy_checks`).
- **Rekap cepat**: scorecard agregat per policy/hari disimpan terpisah (`watchlist_scorecards`) untuk dashboard dan analisis.

---

# 1) `watchlist_strategy_runs`

**Fungsi:** menyimpan **snapshot plan EOD** per `policy` untuk satu `trade_date` yang akan dieksekusi pada `exec_trade_date`.

**Sumber data:** dibuat otomatis saat Watchlist menghasilkan output final (EOD publish), idealnya di step yang sama dengan publish JSON.

**Kapan update:** sekali per policy per hari (idempotent).

### Kolom inti

- `id` (PK)
  - Identifier run.

- `trade_date` (date)
  - Tanggal basis EOD (hari sinyal dihitung & rekomendasi dibuat).

- `exec_trade_date` (date)
  - Tanggal eksekusi target (umumnya next trading day).

- `policy_code` (varchar(32))
  - Kode policy/strategi (mis. `WEEKLY_SWING`, `DIVIDEND_SWING`, dll).

- `policy_version` (varchar(32), nullable)
  - Versi dokumen/engine policy saat run dibuat (untuk perbandingan lintas versi).

- `source` (varchar(32), nullable)
  - Asal data (contoh: `canonical`, `fallback`, `mixed`).

- `payload_json` (json/jsonb/longtext)
  - Snapshot output plan policy tersebut.
  - **Minimal wajib memuat**:
    - `trade_date`, `exec_date`, `policy`
    - `groups.top_picks[]`, `groups.secondary[]`, `groups.watch_only[]`
    - per ticker: `entry`, `timing.entry_windows`, `timing.avoid_windows`, `guards`, `slices/slice_pct`, `reason_codes`

- `created_at`, `updated_at`

### Invariants (wajib benar)

- Unik: kombinasi `(trade_date, policy_code, source)` atau `(exec_trade_date, policy_code, source)` harus unik (pilih salah satu sebagai unique index).
- `payload_json.policy` harus sama dengan `policy_code`.
- `groups.top_picks`, `secondary`, `watch_only` hanya berisi **kandidat** (bukan semua ticker).

### Index yang disarankan
- Unique: `(trade_date, policy_code, source)`
- Query cepat:
  - `(exec_trade_date, policy_code)`
  - `(trade_date)`

### Contoh payload minimal
```json
{
  "trade_date": "2026-01-26",
  "exec_date": "2026-01-27",
  "policy": "WEEKLY_SWING",
  "meta": { "generated_at": "2026-01-26T20:15:00+07:00" },
  "groups": { "top_picks": [], "secondary": [], "watch_only": [] }
}
```

---

# 2) `watchlist_strategy_checks`

**Fungsi:** menyimpan hasil **cek intraday** terhadap `strategy_run` pada satu waktu (`checked_at`).  
Ini adalah *log* yang jadi sumber utama untuk menghitung **feasible_rate** (eligible_now rate), debugging, dan audit.

**Sumber data:**
- Manual input (Ajaib) → user memasukkan snapshot (last/open/prev_close/bid/ask).
- Otomatis (opsional) → job intraday hanya untuk kandidat.

**Kapan update:** banyak kali per hari (per checkpoint).

### Kolom inti

- `id` (PK)

- `strategy_run_id` (FK)
  - Relasi ke `watchlist_strategy_runs.id`.

- `checked_at` (timestamp)
  - Jam WIB ketika snapshot diambil.

- `phase` (varchar(24), nullable)
  - Label checkpoint, contoh:
    - `PREOPEN`
    - `IN_WINDOW`
    - `POSTWINDOW`
    - `EOD_CLOSE`

- `ticker_code` (varchar(16))
  - Ticker yang dicek.

- `snapshot_json` (json/jsonb)
  - Data observasi intraday (manual/otomatis). Minimal:
    - `last`, `open`, `prev_close`
    - ideal: `bid`, `ask`, `high`, `low`, `vol`

- `result_json` (json/jsonb)
  - Output evaluasi rule:
    - `eligible_now` (bool)
    - `blocked_by[]` (reason codes)
    - `computed.gap_pct`, `computed.spread_pct`, `computed.chase_pct`
    - `in_entry_window` (bool)

- `created_at`

### Invariants (wajib benar)

- `strategy_run_id` harus valid.
- `ticker_code` harus ada di salah satu group kandidat run (top/secondary/watch_only).
- `checked_at` harus berada pada `exec_trade_date` yang sama dengan run (kecuali phase `PREOPEN` yang boleh sebelum open).
- `result_json.eligible_now == true` hanya jika semua guard terpenuhi (window, gap, spread, chase, dsb).

### Index yang disarankan
- `(strategy_run_id, checked_at)`
- `(exec_trade_date)` via join atau denormalisasi (opsional)
- `(ticker_code, checked_at)` untuk audit per ticker

### Contoh snapshot/result
```json
// snapshot_json
{ "last": 1235, "open": 1220, "prev_close": 1210, "bid": 1230, "ask": 1235 }

// result_json
{ "eligible_now": true, "blocked_by": [], "computed": { "gap_pct": 0.0083, "spread_pct": 0.0040 } }
```

---

# 3) `watchlist_scorecards`

**Fungsi:** menyimpan **rekap agregat** hasil evaluasi untuk 1 `strategy_run` (atau per policy per hari).  
Ini adalah bahan dashboard “berapa % rekomendasi bisa dieksekusi” dan “berapa yang menyentuh entry slice”.

**Sumber data:** job/command `scorecard:compute` setelah close, atau periodik.

**Kapan update:** minimal sekali per run (bisa overwrite versi terbaru).

### Kolom inti

- `id` (PK)

- `strategy_run_id` (FK)
  - Relasi ke `watchlist_strategy_runs.id`.

- `metrics_json` (json/jsonb)
  - Struktur metrik (bebas tapi harus konsisten). Minimal v1:
    - `counts.candidates`
    - `counts.checked`
    - `counts.eligible_now`
    - `feasible_rate` (eligible_now/checked)
    - `fill_rate` (filled_slices/total_slices)
  - Opsional:
    - `outcome_rate` (butuh data exit/portfolio)

- `computed_at` (timestamp, nullable)
  - Kapan scorecard dihitung.

- `created_at`

### Invariants (wajib benar)

- `feasible_rate` harus konsisten dengan `counts`.
- `fill_rate` harus konsisten dengan definisi slice triggers.
- `metrics_json.policy` (bila disimpan) harus sama dengan policy dari run.

### Index yang disarankan
- Unique: `(strategy_run_id)` (satu scorecard per run; update overwrite)
- Query cepat: join run → filter `(exec_trade_date, policy_code)`

### Definisi metrik v1 (wajib konsisten)
- `feasible_rate = eligible_now_count / checked_candidates`
- `fill_rate = filled_slices / total_slices` (pakai high/low harian jika tidak ada urutan intraday)
- `outcome_rate` boleh `null` sampai ada data transaksi/exit.

---

## 4) Tabel terkait (existing) — konteks Watchlist & Scorecard

Bagian ini bukan tabel baru di scorecard, tapi sering dipakai sebagai input/pelengkap.

### 4.1 `watchlist_daily` + `watchlist_candidates`
**Fungsi:** penyimpanan output watchlist “operasional” untuk UI (daftar kandidat, ranking, alasan).  
`watchlist_strategy_runs` adalah snapshot policy-level; sementara `watchlist_daily/candidates` adalah bentuk operasional yang lebih granular.

**Sumber data:** EOD watchlist run.

### 4.2 `watchlist_intraday_snapshots`
**Fungsi:** (opsional) snapshot intraday otomatis untuk kandidat yang dipantau.
- Jika belum ada ingestion otomatis, kamu bisa skip tabel ini dan pakai manual input ke `watchlist_strategy_checks`.

### 4.3 `ticker_status_daily`
**Fungsi:** status harian ticker (suspensi, notasi khusus, warning, dll) sebagai guard/flag.
- Diisi otomatis dari ingestion status (atau manual seed jika sumber belum ada).

### 4.4 `ticker_dividend_events`
**Fungsi:** event dividen (cum/ex/record/payment) untuk policy `DIVIDEND_SWING`.
- Idealnya diisi otomatis dari source data dividen; manual input boleh sebagai seed.

### 4.5 `market_calendar` (kolom baru: `session_open_time`, `session_close_time`, `breaks_json`)
**Fungsi:** sumber “jam bursa” untuk menentukan entry/avoid windows secara konsisten.
- `breaks_json` menyimpan interval istirahat/auction (format JSON).

**Sumber data:** umumnya manual seed (kalender jarang berubah), lalu dipakai runtime.

---

## 5) Siapa yang mengisi apa (ringkas)

- `watchlist_strategy_runs` → **otomatis** saat EOD watchlist publish.
- `watchlist_strategy_checks` → **manual** (Ajaib) pada v1, bisa jadi **otomatis** jika ada intraday snapshot job.
- `watchlist_scorecards` → **otomatis** dari job compute (after close / besok pagi).
- `ticker_dividend_events`, `ticker_status_daily` → otomatis jika ada importer; kalau belum, bisa manual seed minimal.
- `market_calendar.*time/breaks_json` → biasanya **manual seed**.

---

## 6) Contract test checklist (schema-level)

Minimal checks yang wajib lulus:
- `watchlist_strategy_runs.payload_json` memuat `groups.top_picks|secondary|watch_only`.
- `watchlist_strategy_checks.ticker_code` selalu termasuk kandidat dari run.
- `watchlist_scorecards.metrics_json` punya `counts` dan `feasible_rate` konsisten.

---

## 7) Catatan implementasi (praktis)

- Untuk MariaDB: gunakan `LONGTEXT` untuk JSON bila tidak pakai JSON type.
- Pastikan semua JSON disimpan UTF‑8 tanpa BOM.
- Jangan simpan 900 ticker di watch_only; watchlist adalah kandidat, bukan universe dump.
