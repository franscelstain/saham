# 09 — Dynamic Selection (Deterministic) — Weekly Swing

Dokumen ini mengunci **cara selection** Weekly Swing (EOD) untuk membentuk:
TOP_PICKS, SECONDARY, WATCH_ONLY, dan AVOID.

Kontrak utama:
- Sistem selection **hanya satu**: **Qualified Pools + Quantile Cutoff (BT) + target dinamis**.
- Setiap kandidat yang tampil wajib punya **reason codes** yang ada di dictionary (`watchlist_reason_codes`).

## Normative Summary (LOCKED)

Bagian ini bersifat **normatif (mengikat)**. Bagian ilustrasi (jika ada) bersifat **non-normatif**. Jika terjadi konflik, yang dipakai adalah aturan normatif.

Aturan yang dikunci:
- Sistem selection **hanya satu**: **Qualified Pools + Cutoff Quantile (BT) + target dinamis**. Tidak ada metode selection lain.
- Urutan pipeline **wajib**: **Eligible → Score → Cutoff quantile → Qualified pools → Target dinamis → Final mapping**.
- **Qualified pool wajib** untuk masuk **TOP_PICKS** dan **SECONDARY**.
- **Target dinamis** boleh bernilai **0** dan kondisi ini **wajib tercatat**.
- Ranking final mengikuti `grouping.sort_keys` (LOCKED) dengan urutan exact:
  1. `score_total DESC`
  2. `score_breakout DESC`
  3. `score_momentum DESC`
  4. `dv20_idr DESC`
  5. `atr14_pct ASC`
  6. `ticker_id ASC`
- Reason codes **wajib** menggunakan dictionary reason codes (tidak boleh membuat code baru di output):
  - dipilih (TOP_PICKS/SECONDARY): `WS_SEL_PCT`
  - eligible tapi tersembunyi karena cutoff/target: `WS_HID_PCT`
  - tersembunyi karena cap: `WS_HID_CAP`
- Stop condition **NO_TRADE** menghasilkan PLAN kosong pada output API/UI; alasan run-level disimpan sebagai `fail_code`.

## LOCKED — NO_TRADE Gate (non-ambiguous)

NO_TRADE adalah run PLAN yang **tidak menghasilkan daftar ticker untuk ditampilkan** di API/UI (`items = []`).
Ada **dua** penyebab yang dibedakan oleh `meta.fail_reason_codes`:

1) **MIN_ELIGIBLE**
- Kondisi: `eligible_total < ws.filters.min_eligible_count`
- Output wajib:
  - `meta.fail_code = "NO_TRADE"`
  - `meta.fail_reason_codes = ["WS_NO_TRADE_MIN_ELIGIBLE"]`
  - `items = []`

2) **ALL_FILTERED**
- Kondisi: `eligible_total >= ws.filters.min_eligible_count` namun setelah guard + selection + display rules tidak ada satupun item yang lolos untuk ditampilkan (mis. seluruh candidate ter-hide/terfilter).
- Output wajib:
  - `meta.fail_code = "NO_TRADE"`
  - `meta.fail_reason_codes = ["WS_NO_TRADE_ALL_FILTERED"]`
  - `items = []`

Tambahan wajib (LOCKED):
- `meta.plan_hash` dihitung dari canonical payload kosong `[]` (lihat schema runtime).

Auditability wajib tersimpan di `run_metrics_json`: cutoff hari ini, target dinamis, ukuran pool, dan `data_batch_hash` (lihat [`07_WS_REASON_CODES_AND_HASH.md`](07_WS_REASON_CODES_AND_HASH.md)).



## LOCKED — Group Semantics Mapping (non-ambiguous)

Mapping `group_semantic` untuk setiap ticker **wajib deterministik** dan mengikuti urutan prioritas berikut (higher wins):

1) **AVOID (guard fail / block)**
   - Jika `pass_guard = false` ⇒ `group_semantic = AVOID`.
   - Reason code wajib salah satu (sesuai penyebab pertama yang ditemukan, urutan evaluasi sesuai Step 2 di [`08_WS_PLAN_ALGORITHM.md`](08_WS_PLAN_ALGORITHM.md)):
     - `WS_LIQ_FAIL` (dv20_idr < min_dv20_idr)
     - `WS_ATR_LOW`  (atr14_pct < min_atr14_pct)
     - `WS_ATR_HIGH` (atr14_pct > max_atr14_pct)
     - `WS_VOLR_FAIL` (vol_ratio < min_vol_ratio)

2) **WATCH_ONLY (forced)**
   - Jika `pass_guard = true` tapi kena forced rule (lihat Step 5 di [`08_WS_PLAN_ALGORITHM.md`](08_WS_PLAN_ALGORITHM.md)) ⇒ `group_semantic = WATCH_ONLY` (forced).
   - Reason code wajib:
     - `WS_FW_EXT` jika breakout extended (close terlalu jauh di atas hh20)
     - `WS_FW_RR_LOW` jika rr < min_rr
     - `WS_FW_RR_INV` jika rr invalid / tidak bisa dihitung

3) **Selection result (qualified pools + targets)**
   - Jika `pass_guard = true` dan tidak forced:
     - Masuk final picks pool:
       - `TOP_PICKS` ⇒ reason `WS_SEL_PCT`
       - `SECONDARY` ⇒ reason `WS_SEL_PCT`
     - Eligible tapi tidak terpilih karena cutoff/target ⇒ `WATCH_ONLY` dengan reason `WS_HID_PCT`
     - Eligible tapi tidak terpilih karena cap/run-capped ⇒ `WATCH_ONLY` dengan reason `WS_HID_CAP`

Catatan (LOCKED):
- Reason codes **tidak boleh membuat code baru**; semua code harus ada di dictionary `watchlist_reason_codes` (lihat [`db/REASON_CODES_SEED.sql`](db/REASON_CODES_SEED.sql)).
- Jika beberapa kondisi berlaku, gunakan prioritas di atas dan **jangan** override `AVOID` atau forced WATCH_ONLY oleh selection.

## Purpose & invariants (LOCKED)

### Stop conditions (LOCKED)

Tujuan stop conditions adalah mencegah PLAN dibangun dari data yang tidak layak, dan memastikan perilaku NO_TRADE **konsisten** dengan eksekusi canonical.

### 1) Coverage gate (hard fail)

Jika `data_readiness.reject_if_eod_incomplete = true` dan `coverage_ratio < data_readiness.min_coverage_ratio` ⇒ **FAILED RUN** (tidak ada PLAN dihasilkan).
- `coverage_ratio` dihitung sebagai: `eligible_with_complete_required_fields / eligible_total`.
- Coverage gate ini adalah “hard fail” untuk menjaga kualitas output.

### 2) Minimum eligible gate (NO_TRADE)

Jika `eligible_total < no_trade.min_eligible_count` ⇒ hasil group semua kosong dan run ditandai **NO_TRADE**.
- Tidak ada fallback/forcing picks saat gate ini gagal.

Catatan (LOCKED):
- Tidak ada parameter tambahan di luar kontrak paramset yang boleh dipakai di policy ini.

## Prerequisites
### Weekly Swing
08_WS_PLAN_ALGORITHM.md

## Market regime gate (LOCKED)

Policy **Weekly Swing** tidak memakai market regime gate.
Jika ingin gate berbasis kondisi pasar, buat policy terpisah dengan kontrak+validator+backtest terpisah.

## Pipeline selection (linear)
Urutan berikut **wajib** dan menjadi acuan implementasi:

1) **Eligible pool**
   - Mulai dari universe WS.
   - Terapkan guardrails: likuiditas (dv20_idr), volatilitas (atr14_pct), dan exclude_tickers; lalu terapkan `data_ready`.
   - Hasil: `eligible_pool`.

2) **Score**
   - Hitung `score_total` hanya untuk `eligible_pool`.
   - `score_total` berada di [0..1].

3) **Cutoff quantile (BT)**
   - Hitung cutoff dinamis harian dari distribusi `score_total`:
     - `top_cutoff_today = quantile(score_total, grouping.top_min_score_q.value)`
     - `secondary_cutoff_today = quantile(score_total, grouping.secondary_min_score_q.value)`
   - `grouping.top_min_score_q` dan `grouping.secondary_min_score_q` berasal dari backtest (BT).

**Definisi `quantile()` (LOCKED, deterministic):**
- Input: daftar nilai `score_total` dari `eligible_pool` (N buah), dengan setiap item punya `ticker_id`.
- Jika `N=0` (eligible_pool kosong) ⇒ `top_cutoff_today` dan `secondary_cutoff_today` = `NULL` dan run wajib menghasilkan status `NO_TRADE`.
- Urutkan ascending dengan kunci deterministik: `(score_total ASC, ticker_id ASC)`.
- Untuk `q` di [0..1], definisikan indeks diskrit:
  - `k = ceil(q * N)` dengan batas `k ∈ [1..N]` (jika `q=0` ⇒ `k=1`; jika `q=1` ⇒ `k=N`).
- Output: `quantile = score_total` pada posisi ke-`k` (1-based) dari list terurut.
- Ini setara dengan **percentile_disc**. Tidak boleh pakai interpolasi (`percentile_cont`).

4) **Qualified pools**
   - `top_pool = { ticker ∈ eligible_pool | score_total >= top_cutoff_today }`
   - `secondary_pool = { ticker ∈ eligible_pool | score_total >= secondary_cutoff_today } - top_pool`
      - Jika pool kosong, evaluasi stop condition sesuai penyebabnya: `FAILED` untuk data/coverage failure, `NO_TRADE` untuk run valid tanpa kandidat yang layak.

## Qualified pools — details

Ranking `score_total` **tidak boleh** langsung mengisi TOP_PICKS. Ada tahap “qualified pool” agar sistem tidak memaksakan rekomendasi.

### A) Eligible pool
Ticker diproses jika `data_ready=TRUE` dan lolos guardrails (likuiditas/volatilitas/partisipasi).

### B) Score pool
Untuk semua ticker di eligible pool, hitung `score_total` (0..1).

### C) Cutoff dinamis (min_score harian) berbasis quantile
Agar kualitas tetap terjaga pada hari “skor rendah” maupun “skor tinggi”, cutoff untuk TOP/SECONDARY dihitung **dinamis** dari distribusi `score_total` hari itu.

Parameter (asal-usul: **BT** via kalibrasi backtest 2 tahun):
- `grouping.top_min_score_q` (0..1)
- `grouping.secondary_min_score_q` (0..1)

Definisi:
- `top_cutoff_today = quantile(score_total eligible_pool, grouping.top_min_score_q.value)`
- `secondary_cutoff_today = quantile(score_total eligible_pool, grouping.secondary_min_score_q.value)`

Cutoff harian ini disimpan di `watchlist_plan_runs.run_metrics_json` untuk audit.

### D) Qualified pools
- `top_pool = eligible_pool` dengan syarat:
  - `score_total >= top_cutoff_today`, dan
  
- `secondary_pool = eligible_pool` dengan syarat:
  - `score_total >= secondary_cutoff_today`.

### E) Selection by ranking + target dinamis
- TOP_PICKS:
  - urutkan `top_pool` by `score_total DESC` (tie-breaker deterministik),
  - ambil `min(top_picks_target_dynamic, |top_pool|)`.
- SECONDARY:
  - bentuk kandidat: `secondary_pool` dikurangi ticker yang sudah masuk TOP,
  - urutkan sama,
  - ambil `min(secondary_target_dynamic, |pool|)`.
- WATCH_ONLY:
  - eligible tetapi tidak masuk TOP/SECONDARY (karena kalah cutoff atau kalah kuota),
  - termasuk `score_total == 0` **hanya jika** `eligible=TRUE` dan `data_ready=TRUE`.
- AVOID:
  - fail guardrails berat atau data tidak siap (reason code wajib).

### F) Pool boleh 0 walaupun ada ticker skor tinggi
Jika `top_pool` kosong (misalnya cutoff tinggi + core signals tidak terpenuhi), maka `TOP_PICKS` boleh bernilai 0. Ini valid untuk menjaga kualitas.

## Target dinamis (LOCKED)
Paramset menyimpan base target (MAN):
- `grouping.top_picks_target`
- `grouping.secondary_target`

Saat PLAN run, sistem menurunkan target dinamis per-run dan menyimpan ke `run_metrics_json`:
- `top_picks_target_dynamic`
- `secondary_target_dynamic`

Aturan:
- Jika stop condition (NO_TRADE) → semua target dinamis = 0.
- Jika tidak stop dan `top_pool` tidak kosong → `top_picks_target_dynamic >= 1`.
- `*_target_dynamic <= base_target` dan `<= pool_size`.

Jika ada `min-count overrides`, override hanya boleh:
- menaikkan target (hingga batas pool) saat coverage bagus,
- atau menurunkan target menjadi 0 saat stop condition.

## Final mapping: group semantics + reason codes (LOCKED)

### A) TOP_PICKS
- Ambil `top_picks_target_dynamic` ticker teratas dari `top_pool` menggunakan `grouping.sort_keys` (LOCKED).
- Beri decision/group: `TOP_PICKS`
- Reason code:
  - `WS_SEL_PCT` (dipilih karena lolos cutoff quantile dan masuk peringkat).

### B) SECONDARY
- Ambil `secondary_target_dynamic` ticker teratas dari `secondary_pool` menggunakan `grouping.sort_keys` (LOCKED).
- Group: `SECONDARY`
- Reason code:
  - `WS_SEL_PCT`

### C) WATCH_ONLY
- Kandidat eligible yang tidak masuk TOP/SECONDARY.
- Group: `WATCH_ONLY`
- Reason codes (minimal satu):
  - `WS_HID_PCT` (eligible tapi tidak masuk cutoff/pool terpilih atau kalah peringkat karena target).
  - Jika ada batas kuota/hard cap per bucket: `WS_HID_CAP` (jika benar-benar terkena cap).

### D) AVOID / NO_TRADE
- Jika gagal guardrails/data_ready → `AVOID` dengan reason sesuai dictionary.
- Jika stop condition (`NO_TRADE`) → output API/UI tidak menampilkan kandidat; persistence audit tetap tersimpan, dan run menyimpan `fail_code`.

## Outputs & audit (LOCKED)
Wajib tersimpan untuk setiap PLAN run:
- `top_cutoff_today`, `secondary_cutoff_today`
- `*_target_dynamic`
- ukuran pool (`eligible_count`, `top_pool_count`, `secondary_pool_count`)
- `data_batch_hash` (lihat [`07_WS_REASON_CODES_AND_HASH.md`](07_WS_REASON_CODES_AND_HASH.md))
- Semua diletakkan di `watchlist_plan_runs.run_metrics_json`.

## Failure modes & stop condition

### FAILED stop condition
Kondisi berikut adalah hard-fail dan wajib menghasilkan `run_status = FAILED`:
- data EOD belum lengkap / batch invalid,
- coverage eligible terlalu kecil.

Jika FAILED aktif:
- `top_picks_target_dynamic=0`, `secondary_target_dynamic=0`,
- hasil PLAN tidak ditampilkan,
- `fail_code` run-level **wajib** diisi.

### NO_TRADE stop condition
Kondisi berikut valid menghasilkan `run_status = NO_TRADE`:
- `eligible_total < no_trade.min_eligible_count`,
- setelah filter/scoring/guardrail dijalankan tidak ada kandidat yang layak.

Jika NO_TRADE aktif:
- `top_picks_target_dynamic=0`, `secondary_target_dynamic=0`,
- output API/UI tidak menampilkan kandidat,
- persistence audit tetap menyimpan item dengan `display_bucket = HIDE`,
- `fail_code` run-level **wajib** diisi.

## Next
### Weekly Swing
- 10_WS_CONFIRM_OVERLAY.md
### Reference
- [`03_WS_DATA_MODEL_MARIADB.md`](03_WS_DATA_MODEL_MARIADB.md) untuk schema persistence / output table mapping,
- [`05_WS_PARAMETER_REGISTRY_COMPLETE.md`](05_WS_PARAMETER_REGISTRY_COMPLETE.md) untuk registry,
- [`06_WS_PARAMSET_VALIDATOR_SPEC.md`](06_WS_PARAMSET_VALIDATOR_SPEC.md) untuk validator,
- [`07_WS_REASON_CODES_AND_HASH.md`](07_WS_REASON_CODES_AND_HASH.md) untuk data_batch_hash canonical,
- [`13_WS_CONTRACT_TEST_CHECKLIST.md`](13_WS_CONTRACT_TEST_CHECKLIST.md) untuk checklist.
- [`_refs/WS_WORKED_EXAMPLE_E2E.md`](_refs/WS_WORKED_EXAMPLE_E2E.md)
- [`_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`](_refs/WS_FAILURE_BEHAVIOR_MATRIX.md)
