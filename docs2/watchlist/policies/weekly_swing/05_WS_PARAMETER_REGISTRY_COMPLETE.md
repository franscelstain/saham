# 05 — Parameter Registry (Complete) — WS_EOD_PLAN_CONFIRM

## Purpose
Daftar parameter WS yang **exhaustive**: semua yang boleh dipakai code. Jika code butuh parameter, harus ada di sini dan ada di params_json.

## Prerequisites
### Weekly Swing
04_WS_PARAMSET_JSON_CONTRACT.md

## Inputs
- params_json WS

## Process
Gunakan registry ini sebagai sumber kebenaran definisi per key.

### A. Meta & data contract
- meta.dv20_unit (DET/ACTIVE)
- meta.cost_model (MAN/ACTIVE)
- data_contract.required_sources (DET/ACTIVE)
- data_contract.required_fields.* (DET/ACTIVE)
- data_contract.disabled_fields (DET+MAN/ACTIVE)

### B. Data readiness & outlier
- data_readiness.min_coverage_ratio (DET/ACTIVE, locked)
- data_readiness.min_history_days (DET/ACTIVE)
- data_readiness.max_missing_bar_days_60d (MAN/TEMP, bt_target=true)
- data_readiness.reject_if_eod_incomplete (DET/ACTIVE)
- data_readiness.outlier_ruleset.value.enabled (DET+MAN/ACTIVE)
- data_readiness.outlier_ruleset.value.max_abs_return_1d_pct (MAN/ACTIVE, bt_target=true)
- data_readiness.outlier_ruleset.value.max_high_low_range_1d_pct (MAN/ACTIVE, bt_target=true)

### C. Liquidity
- liquidity.min_dv20_idr (MAN/ACTIVE, bt_target=true)
- liquidity.dv20_strong_idr (MAN/ACTIVE, bt_target=true)
- liquidity.exclude_tickers (MAN/ACTIVE)

### D. Risk & stops
- risk.min_atr14_pct (MAN/ACTIVE, bt_target=true)
- risk.max_atr14_pct (MAN/ACTIVE, bt_target=true)
- risk.atr_ideal_low (MAN/ACTIVE, bt_target=true)
- risk.atr_ideal_high (MAN/ACTIVE, bt_target=true)
- risk.stop_mode (DET+MAN/ACTIVE)
- risk.stop_atr_mult (MAN/ACTIVE, bt_target=true)
- risk.min_rr (MAN/ACTIVE, bt_target=true)

### E. Setup
- setup.roc_lo (MAN/ACTIVE, bt_target=true)
- setup.roc_hi (MAN/ACTIVE, bt_target=true)
- setup.mom_roc20_soft_min (MAN/TEMP, bt_target=true)
- setup.bo_trigger_mode (DET/ACTIVE)
- setup.bo_near_below_pct (MAN/ACTIVE, bt_target=true)
- setup.bo_max_ext_pct (MAN/ACTIVE, bt_target=true)

### F. Scoring
- scoring.combine_mode (DET/ACTIVE)
- scoring.weights.value.{momentum,breakout,volume,risk} (MAN/ACTIVE, bt_target=true)

### G. Grouping (dynamic selection)
- secondary_target (MAN/ACTIVE)
- top_picks_target (MAN/ACTIVE)
- secondary_min_score_q (BT/ACTIVE, bt_target=true)
- top_min_score_q (BT/ACTIVE, bt_target=true)
- grouping.grouping_mode (DET/ACTIVE)
- grouping.sort_keys (DET/ACTIVE, locked)
- grouping.rounding_mode (DET/ACTIVE, locked)
- grouping.display_caps.value.* (MAN/ACTIVE)

### H. Plan levels
- plan_levels.entry_mode (DET+MAN/ACTIVE)
- plan_levels.entry_band_pct (MAN/ACTIVE, bt_target=true)

### I. No-trade
- no_trade.min_eligible_count (MAN/ACTIVE, bt_target=true)
- no_trade.no_trade_hides_all (DET/ACTIVE, locked)

### J. Confirm overlay
- confirm_overlay.enabled (DET/ACTIVE)
- confirm_overlay.snapshot_max_age_sec (DET/ACTIVE)
- confirm_overlay.max_drift_from_entry_pct (MAN/ACTIVE, bt_target=true)
- confirm_overlay.spread_max_pct (MAN/ACTIVE, bt_target=true)

### K. Hash contract (reproducibility)
- hash_contract.order_by (DET/ACTIVE, locked)
- hash_contract.scales (DET/ACTIVE, locked)
- hash_contract.null_handling (DET/ACTIVE, locked)

## Outputs
- Registry parameter WS yang menjadi sumber kebenaran.

## Change triggers (aturan kapan parameter harus diubah)
Parameter **tidak** diubah karena “feeling”. Ubah hanya jika ada sinyal objektif berikut:

1) **Drift performa** (BT):
   - Win-rate top picks turun di bawah ambang minimal yang disepakati selama N minggu berturut-turut, atau
   - Avg return net top picks turun signifikan vs baseline backtest 2 tahun.
   → tindakan: re-calibration backtest, update paramset (BT).

2) **Regime volatilitas berubah** (DET/MAN):
   - ATR14_pct median index/market naik/turun melewati band normal historis.
   → tindakan: sesuaikan guard `max_atr14_pct` atau risk weight.

3) **Regime likuiditas berubah** (DET/MAN):
   - DV20 median universe turun sehingga coverage eligible jatuh drastis, atau sebaliknya terlalu longgar.
   → tindakan: sesuaikan `min_dv20_idr`.

4) **Data quality issues** (DET):
   - Missing/aneh/0 pada field wajib meningkat.
   → tindakan: perketat readiness rules atau stop run (fail_code), bukan “menurunkan cutoff”.

## Update method (wajib)
- Origin **BT**: hanya boleh diubah via proses backtest calibration + persisten ke paramset baru.
- Origin **DET**: perubahan harus disertai reasoning tertulis (prinsip pasar) dan dites pada window historis minimum.
- Origin **MAN**: perubahan boleh manual, tapi wajib tercatat (who/when/why) dan menghasilkan paramset baru.

### Cutoff quantile (BT)
- `top_min_score_q`
  - Definisi: quantile cutoff untuk TOP_PICKS (`top_cutoff_today`).
  - Origin: **BT**
  - Alasan: menjaga kualitas picks saat distribusi skor berubah antar hari.
  - Kapan diubah: hanya lewat recalibration backtest (lihat drift performa).

- `secondary_min_score_q`
  - Definisi: quantile cutoff untuk SECONDARY (`secondary_cutoff_today`).
  - Alasan: memfilter kandidat “positif tipis” agar SECONDARY tetap bermakna.
  - Kapan diubah: hanya lewat recalibration backtest.

### Target (MAN v0)
- `top_picks_target`
  - Definisi: batas maksimum TOP_PICKS (base target).
  - Origin: **MAN**
  - Alasan: menjaga output fokus dan stabil.
  - Kapan diubah: jika scope universe berubah besar atau kebutuhan kapasitas output berubah.

- `secondary_target`
  - Definisi: batas maksimum SECONDARY (base target).
  - Alasan: menyediakan kandidat cadangan tanpa memaksa TOP.
  - Kapan diubah: jika kebutuhan coverage berubah.

Catatan implementasi:
- Sistem menghitung `top_picks_target_dynamic` dan `secondary_target_dynamic` per-run (deterministik) dan menyimpannya di `run_metrics_json`.
- Nilai dinamis boleh 0 hanya jika stop condition run = TRUE (NO_TRADE).

## Namespace rules (LOCKED)

Catatan (LOCKED): di dokumen, `a.b.c` adalah notasi referensi; JSON paramset tetap nested (`{a:{b:{c:...}}}`).

Untuk Weekly Swing, **namespace parameter bersifat tunggal dan canonical**. Code **wajib** membaca key canonical saja.
- Alias (key alternatif) **tidak boleh** dipakai runtime.
- Jika suatu key pernah disebut dengan nama lain di dokumen lama, itu dianggap kesalahan dokumen dan harus dinormalisasi ke canonical.

## Per-Parameter Definitions (LOCKED)

### Cutoff quantile (BT) (LOCKED)

- `top_min_score_q` (0..1) — quantile cutoff untuk TOP_PICKS.  
  Origin: **BT**.  
  Alasan: menjaga kualitas picks dengan cutoff berbasis distribusi `score_total` eligible hari itu (robust terhadap skala skor harian).  
  Kapan diubah: bila evaluasi BT 2 tahun menunjukkan trade-off return/win-rate memburuk secara konsisten.  
  Cara ubah: jalankan kalibrasi BT → pilih param_id terbaik → promote ke paramset.

- `secondary_min_score_q` (0..1) — quantile cutoff untuk SECONDARY.  
  Origin: **BT**.  
  Alasan: membentuk pool SECONDARY yang masih punya kualitas minimum, tanpa memaksakan kuota.  
  Kapan diubah: sama seperti `top_min_score_q`.  
  Cara ubah: sama seperti `top_min_score_q`.

Catatan implementasi (LOCKED):
- Cutoff harian dihitung dari distribusi `score_total` pada **eligible pool** hari itu.
- Jika eligible pool terlalu kecil untuk menghitung quantile (lihat `no_trade.min_eligible_count`), berlaku aturan NO_TRADE/DEGRADE sesuai Doc 05.

### Base targets (MAN) → per-run dynamic targets (DET) (LOCKED)

- `top_picks_target` (integer >= 0) — base target untuk TOP_PICKS.  
  Origin: **MAN**.  
  Alasan: mengontrol densitas output agar tidak overload; nilai final tetap diturunkan dinamis per-run (DET).  
  Kapan diubah: bila output terlalu sedikit/terlalu banyak secara konsisten dalam beberapa minggu, atau kapasitas eksekusi user berubah.  
  Cara ubah: ubah nilai di paramset (promote paramset baru).

- `secondary_target` (integer >= 0) — base target untuk SECONDARY.  
  Origin: **MAN**.  
  Alasan/Kapan/Cara ubah: sama seperti `top_picks_target`.

Catatan implementasi (LOCKED):
- Per-run dihitung:
  - `top_picks_target_dynamic`
  - `secondary_target_dynamic`
- Target dinamis **boleh 0** bila eligible pool kecil atau cutoff membuat qualified pool kosong.
- Target dinamis tidak boleh “memaksa isi”; kalau qualified pool kosong, group bisa kosong.

### Data coverage gate (DET) (LOCKED)

- `data_readiness.min_coverage_ratio` (0..1) — batas minimal coverage data EOD untuk universe hari itu.  
  Origin: **DET** (locked constant via paramset untuk audit).  
  Alasan: mencegah PLAN dibangun dari data EOD yang tidak lengkap sehingga output menyesatkan.  
  Kapan diubah: bila pipeline data berubah dan coverage realistis bergeser, atau jika audit menunjukkan false abort/false pass.  
  Cara ubah: ubah nilai di paramset dan jalankan contract tests.

### Confirm overlay thresholds (MAN) (LOCKED)

- `confirm_overlay.snapshot_max_age_sec` (integer > 0) — batas usia snapshot runtime untuk CONFIRM; lebih tua ⇒ reason `WS_STALE`.  
  Origin: **MAN**.  
  Alasan: menghindari keputusan berbasis data runtime yang sudah tidak relevan.  
  Kapan diubah: bila frekuensi update snapshot di produksi berubah, atau latensi broker/ingest berubah.  
  Cara ubah: ubah di paramset.

- `confirm_overlay.max_drift_from_entry_pct` (0..1) — drift max dari `entry_ref` (PLAN) ke mid-price runtime; lebih jauh ⇒ `WS_DRIFT_FAR`.  
  Origin: **MAN** (bisa dipromote ke BT bila ada data).  
  Alasan: membatasi eksekusi ketika harga sudah “lari” jauh dari entry plan.  
  Kapan diubah: bila volatilitas market regime berubah atau hasil eksekusi menunjukkan terlalu sering “ketinggalan”/terlalu sering “false delay”.  
  Cara ubah: ubah di paramset.

- `confirm_overlay.spread_max_pct` (0..1) — spread max; lebih lebar ⇒ `WS_SPR_WIDE`.  
  Origin: **MAN**.  
  Alasan: spread lebar adalah sinyal likuiditas buruk saat runtime.  
  Kapan diubah: bila karakter spread pasar berubah.  
  Cara ubah: ubah di paramset.

Bagian ini mengunci definisi, asal-usul (BT/DET/MAN), alasan, dan kapan harus diubah untuk **setiap parameter yang benar-benar dipakai** oleh PLAN/CONFIRM.

Format: `key` — definisi | origin | alasan | kapan diubah | cara ubah

- `input_window.eod_asof` — Tanggal EOD yang dipakai untuk PLAN (asof_eod_date). | **DET** | Menjamin PLAN snapshot berbasis EOD dan deterministik. | Jika sumber EOD berubah atau ada penyesuaian kalender pasar. | Otomatis oleh pipeline compute-eod; tidak diubah manual per run.
- `liquidity.min_dv20_idr` — Batas minimum likuiditas (DV20 dalam IDR) untuk eligible. | **BT/MAN** | Mengurangi ticker iliquid yang rawan slippage/manipulasi; nilai ideal dari BT, bisa ditetapkan manual. | Jika pasar berubah (likuiditas umum naik/turun) atau hasil BT menunjukkan over/under filtering. | Update via paramset promote; catat alasan + hasil evaluasi.
- `risk.min_atr14_pct` — Batas bawah ATR14% untuk eligible (hindari terlalu 'mati'). | **BT/MAN** | Menghindari ticker tanpa pergerakan cukup untuk target swing. | Jika win-rate turun karena pergerakan kurang atau volatilitas pasar menurun. | Update via paramset promote.
- `risk.max_atr14_pct` — Batas atas ATR14% untuk eligible (hindari terlalu liar). | **BT/MAN** | Mengurangi risiko gap/spike berlebihan. | Jika banyak peluang bagus terbuang atau drawdown naik. | Update via paramset promote.

## Next
### Weekly Swing
- 06_WS_PARAMSET_VALIDATOR_SPEC.md