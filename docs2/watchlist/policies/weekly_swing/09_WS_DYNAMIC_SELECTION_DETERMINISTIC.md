# 09 — Dynamic Selection (Deterministic) — Weekly Swing

Dokumen ini mengunci **cara selection** Weekly Swing (EOD) untuk membentuk:
TOP_PICKS, SECONDARY, WATCH_ONLY, dan AVOID.

Kontrak utama:
- Sistem selection **hanya satu**: **Qualified Pools + Quantile Cutoff (BT) + target dinamis**.
- Setiap kandidat yang tampil wajib punya **reason codes** yang ada di dictionary (`watchlist_reason_codes`).

## Normative Summary (LOCKED)

Bagian ini bersifat **normatif (mengikat)**; contoh bersifat **non-normatif (ilustrasi)**. Jika terjadi konflik, yang dipakai adalah aturan normatif.

Aturan yang dikunci:
- Sistem selection **hanya satu**: **Qualified Pools + Cutoff Quantile (BT) + target dinamis**. Tidak ada metode selection lain.
- Urutan pipeline **wajib**: **Eligible → Score → Cutoff quantile → Qualified pools → Target dinamis → Final mapping**.
- **Qualified pool wajib** untuk masuk **TOP_PICKS** dan **SECONDARY**.
- **Target dinamis** boleh bernilai **0** (contoh: stop condition / pool kosong) dan kondisi ini **wajib tercatat**.
- Ranking dan tie-break:
  - sort utama: `score_total DESC`
  - tie-break: `ticker_id ASC`
- Reason codes **wajib** menggunakan dictionary reason codes (tidak boleh membuat code baru di output):
  - dipilih (TOP_PICKS/SECONDARY): `WS_SEL_PCT`
  - eligible tapi tersembunyi karena cutoff/target: `WS_HID_PCT`
  - tersembunyi karena cap: `WS_HID_CAP`
- Stop condition **NO_TRADE** menghasilkan PLAN kosong; alasan disimpan sebagai `fail_code` + `held_reason`.
- Auditability wajib tersimpan di `run_metrics_json`: cutoff hari ini, target dinamis, ukuran pool, dan `data_batch_hash` (lihat `07_WS_REASON_CODES_AND_HASH.md`).

## Purpose & invariants (LOCKED)

### Stop conditions (LOCKED)

Tujuan stop conditions adalah mencegah PLAN dibangun dari data yang tidak layak, dan memastikan perilaku NO_TRADE **konsisten** dengan eksekusi canonical.

### 1) Coverage gate (abort)

Jika `data_readiness.reject_if_eod_incomplete = true` dan `coverage_ratio < data_readiness.min_coverage_ratio` ⇒ **ABORT RUN** (tidak ada PLAN dihasilkan).
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
   - Terapkan guardrails (likuiditas/volatilitas/dll) + `data_ready`.
   - Hasil: `eligible_pool`.

2) **Score**
   - Hitung `score_total` hanya untuk `eligible_pool`.
   - `score_total` berada di [0..1].

3) **Cutoff quantile (BT)**
   - Hitung cutoff dinamis harian dari distribusi `score_total`:
     - `top_cutoff_today = quantile(score_total, top_min_score_q)`
     - `secondary_cutoff_today = quantile(score_total, secondary_min_score_q)`
   - `top_min_score_q` dan `secondary_min_score_q` berasal dari backtest (BT).

4) **Qualified pools**
   - `top_pool = { ticker ∈ eligible_pool | score_total >= top_cutoff_today }`
   - `secondary_pool = { ticker ∈ eligible_pool | score_total >= secondary_cutoff_today } - top_pool`
   - Jika pool kosong karena data/coverage buruk, evaluasi stop condition (NO_TRADE).

## Qualified pools — details

Ranking `score_total` **tidak boleh** langsung mengisi TOP_PICKS. Ada tahap “qualified pool” agar sistem tidak memaksakan rekomendasi.

### A) Eligible pool
Ticker diproses jika `data_ready=TRUE` dan lolos guardrails (likuiditas/volatilitas/partisipasi).

### B) Score pool
Untuk semua ticker di eligible pool, hitung `score_total` (0..1).

### C) Cutoff dinamis (min_score harian) berbasis quantile
Agar kualitas tetap terjaga pada hari “skor rendah” maupun “skor tinggi”, cutoff untuk TOP/SECONDARY dihitung **dinamis** dari distribusi `score_total` hari itu.

Parameter (asal-usul: **BT** via kalibrasi backtest 2 tahun):
- `top_min_score_q` (0..1)
- `secondary_min_score_q` (0..1)

Definisi:
- `top_cutoff_today = quantile(score_total eligible_pool, top_min_score_q)`
- `secondary_cutoff_today = quantile(score_total eligible_pool, secondary_min_score_q)`

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

### F) Pool bisa 0 walaupun ada skor tinggi
Jika `top_pool` kosong (mis. cutoff tinggi + core signals tidak terpenuhi), TOP_PICKS bisa 0. Ini valid untuk menjaga kualitas.

## Target dinamis (LOCKED)
Paramset menyimpan base target (MAN):
- `top_picks_target`
- `secondary_target`

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
- Ambil `top_picks_target_dynamic` ticker teratas dari `top_pool` (sort by `score_total DESC`, tie-break `ticker_id ASC`).
- Beri decision/group: `TOP_PICKS`
- Reason code:
  - `WS_SEL_PCT` (dipilih karena lolos cutoff quantile dan masuk peringkat).

### B) SECONDARY
- Ambil `secondary_target_dynamic` ticker teratas dari `secondary_pool` (sort rule sama).
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
- Jika stop condition (NO_TRADE) → semua group kosong; simpan `held_reason` dan `fail_code`.

## Outputs & audit (LOCKED)
Wajib tersimpan untuk setiap PLAN run:
- `top_cutoff_today`, `secondary_cutoff_today`
- `*_target_dynamic`
- ukuran pool (`eligible_count`, `top_pool_count`, `secondary_pool_count`)
- `data_batch_hash` (lihat Doc 07)
Semua diletakkan di `watchlist_plan_runs.run_metrics_json`.

## Failure modes & stop condition
Stop condition (NO_TRADE) harus eksplisit dan tercatat, contoh:
- data EOD belum lengkap / batch invalid,
- coverage eligible terlalu kecil,
- market check gagal.

Jika stop condition aktif:
- `top_picks_target_dynamic=0`, `secondary_target_dynamic=0`,
- hasil PLAN kosong, dan reason disimpan sebagai fail code + held reason.

## Next
### Weekly Swing
- 10_WS_CONFIRM_OVERLAY.md
### Reference
- `03_WS_DATA_MODEL_MARIADB.md` untuk schema persistence / output table mapping,
- `05_WS_PARAMETER_REGISTRY_COMPLETE.md` untuk registry,
- `06_WS_PARAMSET_VALIDATOR_SPEC.md` untuk validator,
- `07_WS_REASON_CODES_AND_HASH.md` untuk data_batch_hash canonical,
- `12_WS_CONTRACT_TEST_CHECKLIST.md` untuk checklist.
- `_refs/WS_WORKED_EXAMPLE_E2E.md`
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`
