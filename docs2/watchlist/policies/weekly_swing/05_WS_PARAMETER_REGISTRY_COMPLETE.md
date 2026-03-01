# 05 — Parameter Registry (Complete) — WS_EOD_PLAN_CONFIRM

## Purpose
Daftar parameter WS yang **exhaustive**: semua key yang boleh dipakai runtime oleh code. Jika code butuh parameter, key **wajib** ada di dok ini **dan** ada di `params_json` (paramset).

## Prerequisites
### Weekly Swing
- `04_WS_PARAMSET_JSON_CONTRACT.md`

## Inputs
- `params_json` WS

## Process
Gunakan registry ini sebagai sumber kebenaran definisi per key.

### A. Meta & data contract
- `data_contract.required_sources` (DET/ACTIVE)
- `data_contract.required_fields.*` (DET/ACTIVE)
- `data_contract.disabled_fields` (DET+MAN/ACTIVE)

### B. Data readiness & outlier
- `data_readiness.min_coverage_ratio` (DET/ACTIVE, locked)
- `data_readiness.min_history_days` (DET/ACTIVE)
- `data_readiness.max_missing_bar_days_60d` (MAN/ACTIVE, bt_target=true)
- `data_readiness.reject_if_eod_incomplete` (DET/ACTIVE)
- `data_readiness.outlier_ruleset.value.enabled` (DET+MAN/ACTIVE)
- `data_readiness.outlier_ruleset.value.max_abs_return_1d_pct` (MAN/ACTIVE, bt_target=true)
- `data_readiness.outlier_ruleset.value.max_high_low_range_1d_pct` (MAN/ACTIVE, bt_target=true)

### C. Liquidity
- `liquidity.min_dv20_idr` (MAN/ACTIVE, bt_target=true)
- `liquidity.dv20_strong_idr` (MAN/ACTIVE, bt_target=true)
- `liquidity.exclude_tickers` (MAN/ACTIVE)

### D. Risk & stops
- `risk.min_atr14_pct` (MAN/ACTIVE, bt_target=true)
- `risk.max_atr14_pct` (MAN/ACTIVE, bt_target=true)
- `risk.atr_ideal_low` (MAN/ACTIVE, bt_target=true)
- `risk.atr_ideal_high` (MAN/ACTIVE, bt_target=true)
- `risk.stop_mode` (DET+MAN/ACTIVE)
- `risk.stop_atr_mult` (MAN/ACTIVE, bt_target=true)
- `risk.min_rr` (MAN/ACTIVE, bt_target=true)

### E. Setup
- `setup.roc_lo` (MAN/ACTIVE, bt_target=true)
- `setup.roc_hi` (MAN/ACTIVE, bt_target=true)
- `setup.mom_roc20_soft_min` (MAN/ACTIVE, bt_target=true)
- `setup.bo_trigger_mode` (DET/ACTIVE)
- `setup.bo_near_below_pct` (MAN/ACTIVE, bt_target=true)
- `setup.bo_max_ext_pct` (MAN/ACTIVE, bt_target=true)

> Catatan: baris `setup.mom_roc20_soft_min + provenance + rationale + kapan diubah` dan duplikasi key `setup.mom_roc20_soft_min (MAN/TEMP, ...)` dihapus karena bukan key dan bikin ambigu.

### F. Scoring
- `scoring.combine_mode` (DET/ACTIVE)
- `scoring.weights.value.{momentum,breakout,volume,risk}` (MAN/ACTIVE, bt_target=true)

### G. Grouping (dynamic selection)
- `grouping.secondary_target` (MAN/ACTIVE)
- `grouping.top_picks_target` (MAN/ACTIVE)
- `grouping.secondary_min_score_q` (BT/ACTIVE, bt_target=true)
- `grouping.top_min_score_q` (BT/ACTIVE, bt_target=true)
- `grouping.grouping_mode` (DET/ACTIVE)
- `grouping.sort_keys` (DET/ACTIVE, locked)
- `grouping.rounding_mode` (DET/ACTIVE, locked)

### H. Plan levels
- `plan_levels.entry_mode` (DET+MAN/ACTIVE)
- `plan_levels.entry_band_pct` (MAN/ACTIVE, bt_target=true)

### I. No-trade
- `no_trade.min_eligible_count` (MAN/ACTIVE, bt_target=true)
- `no_trade.no_trade_hides_all` (DET/ACTIVE, locked)

### J. Confirm overlay
- `confirm_overlay.snapshot_max_age_sec` (DET+MAN/ACTIVE)
- `confirm_overlay.max_drift_from_entry_pct` (MAN/ACTIVE, bt_target=true)
- `confirm_overlay.spread_max_pct` (MAN/ACTIVE, bt_target=true)

### K. Hash contract (reproducibility)
- `hash_contract.order_by` (DET/ACTIVE, locked)
- `hash_contract.scales` (DET/ACTIVE, locked)
- `hash_contract.null_handling` (DET/ACTIVE, locked)

## Outputs
- Registry parameter WS yang menjadi sumber kebenaran.

## Change triggers (aturan kapan parameter harus diubah)
Parameter **tidak** diubah karena “feeling”. Ubah hanya jika ada sinyal objektif berikut:

1) **Drift performa** (BT)
   - Win-rate top picks turun di bawah ambang minimal yang disepakati selama N minggu berturut-turut, atau
   - Avg return net top picks turun signifikan vs baseline backtest 2 tahun.
   - Tindakan: re-calibration backtest, update paramset (BT).

2) **Regime volatilitas berubah** (DET/MAN)
   - ATR14_pct median index/market naik/turun melewati band normal historis.
   - Tindakan: sesuaikan guard `risk.max_atr14_pct` atau `scoring.weights...risk`.

3) **Regime likuiditas berubah** (DET/MAN)
   - DV20 median universe turun sehingga coverage eligible jatuh drastis, atau sebaliknya terlalu longgar.
   - Tindakan: sesuaikan `liquidity.min_dv20_idr`.

4) **Data quality issues** (DET)
   - Missing/aneh/0 pada field wajib meningkat.
   - Tindakan: perketat readiness rules atau stop run (fail_code), bukan “menurunkan cutoff”.

## Update method (wajib)
- Origin **BT**: hanya boleh diubah via proses backtest calibration + promote ke paramset baru.
- Origin **DET**: perubahan harus disertai reasoning tertulis (prinsip pasar) dan dites pada window historis minimum.
- Origin **MAN**: perubahan boleh manual, tapi wajib tercatat (who/when/why) dan menghasilkan paramset baru.

### Rule (LOCKED): BT origin must be proven
Parameter boleh origin=BT hanya jika tercakup pada `13_WS_BT_COVERAGE_MATRIX_LOCKED.md`.
Jika tidak, origin wajib MAN/DET sampai coverage valid.

## Namespace rules (LOCKED)
Catatan (LOCKED): di dokumen, `a.b.c` adalah notasi referensi; JSON paramset tetap nested (`{a:{b:{c:...}}}`).

Untuk Weekly Swing, namespace parameter bersifat tunggal dan canonical. Code wajib membaca key canonical saja:
- Alias (key alternatif) dilarang di runtime.
- Key lama di dokumen legacy dianggap salah dan harus dinormalisasi.

## Per-Parameter Definitions (LOCKED)

### A. Meta & data contract

- `data_contract.required_sources` (array<string>) — daftar sumber data yang wajib tersedia untuk run.
  - Origin: DET
  - Alasan: mencegah PLAN dibuat dari input parsial/acak.
  - Kapan diubah: bila pipeline/sumber data berubah.
  - Cara ubah: ubah di paramset + jalankan contract tests.

- `data_contract.required_fields.*` (array<string> atau map) — daftar field wajib per source.
  - Origin: DET
  - Alasan: memastikan indikator/score tidak dihitung dari field kosong/0.
  - Kapan diubah: bila formula indikator berubah atau field baru jadi wajib.
  - Cara ubah: ubah di paramset + jalankan contract tests.

- `data_contract.disabled_fields` (array<string>) — field yang sengaja diabaikan walau ada.
  - Origin: DET+MAN
  - Alasan: mitigasi field bermasalah tanpa ubah schema besar.
  - Kapan diubah: saat incident data atau perbaikan field selesai.
  - Cara ubah: ubah di paramset + jalankan contract tests.

### B. Data readiness & outlier

- `data_readiness.reject_if_eod_incomplete` (bool) — jika TRUE, run gagal bila EOD batch tidak lengkap.
  - Origin: DET
  - Alasan: PLAN harus deterministik berbasis EOD yang utuh.
  - Kapan diubah: hanya bila SOP operasi mengizinkan “degraded run”.
  - Cara ubah: ubah di paramset + jalankan contract tests.

- `data_readiness.min_coverage_ratio` (0..1) — batas minimal coverage data EOD untuk universe hari itu.
  - Origin: DET
  - Alasan: mencegah PLAN dibangun dari data EOD yang tidak lengkap sehingga output menyesatkan.
  - Kapan diubah: bila pipeline data berubah dan coverage realistis bergeser, atau audit menunjukkan false abort/false pass.
  - Cara ubah: ubah nilai di paramset + jalankan contract tests.

- `data_readiness.min_history_days` (integer > 0) — minimum jumlah hari history agar indikator/rolling metrics stabil.
  - Origin: DET
  - Alasan: tanpa history minimal, indikator rolling tidak valid/berisik → PLAN menyesatkan.
  - Kapan diubah: bila daftar indikator/rolling window berubah (mis. window lebih panjang).
  - Cara ubah: ubah nilai di paramset + jalankan contract tests.

- `data_readiness.max_missing_bar_days_60d` (integer ≥ 0) — maksimum jumlah hari missing bar dalam rolling 60 hari; lebih dari ini → ticker ditolak.
  - Origin: MAN/BT
  - Alasan: membuang ticker dengan data bolong yang bikin indikator dan score tidak reliable.
  - Kapan diubah: setelah hasil kalibrasi BT 2Y / audit data quality (false reject/false pass).
  - Cara ubah: ubah nilai di paramset + jalankan contract tests.

- `data_readiness.outlier_ruleset.value.enabled` (bool) — aktif/nonaktif filter outlier.
  - Origin: DET+MAN
  - Alasan: mekanisme deterministik; toggle bisa manual untuk operasi/insiden data.
  - Kapan diubah: hanya bila ada kebutuhan operasi (insiden data) atau kebijakan berubah.
  - Cara ubah: ubah nilai di paramset + jalankan contract tests.

- `data_readiness.outlier_ruleset.value.max_abs_return_1d_pct` (0..1) — batas maksimum |return 1D|.
  - Origin: MAN/BT
  - Alasan: mencegah spike ekstrem/korup masuk scoring.
  - Kapan diubah: bila audit menunjukkan false positive/false negative, atau setelah recalibrate BT 2Y.
  - Cara ubah: ubah nilai di paramset + jalankan contract tests.

- `data_readiness.outlier_ruleset.value.max_high_low_range_1d_pct` (0..1) — batas maksimum range (high-low) 1D.
  - Origin: MAN/BT
  - Alasan: mencegah candle range ekstrem yang merusak indikator/score.
  - Kapan diubah: sama seperti `max_abs_return_1d_pct`.
  - Cara ubah: sama seperti `max_abs_return_1d_pct`.

### C. Liquidity

- `liquidity.min_dv20_idr` (integer >= 0) — batas minimum DV20 (IDR) untuk eligible.
  - Origin: MAN/BT
  - Alasan: buang ticker iliquid (spread/slippage tinggi).
  - Kapan diubah: saat regime likuiditas berubah atau BT menunjukkan over/under filtering.
  - Cara ubah: update paramset (BT: via calibration; MAN: promote paramset baru).

- `liquidity.dv20_strong_idr` (integer >= 0) — DV20 “kuat” untuk menandai kandidat sangat likuid.
  - Origin: MAN/BT
  - Alasan: dipakai untuk reason/info dan/atau bonus kualitas likuiditas (jika policy memakai).
  - Kapan diubah: saat distribusi DV20 universe bergeser.
  - Cara ubah: update paramset + contract tests.

- `liquidity.exclude_tickers` (array<string>) — daftar ticker yang dikecualikan manual.
  - Origin: MAN
  - Alasan: blacklist operasional (suspend, anomali, corporate action, dll).
  - Kapan diubah: saat ada case khusus.
  - Cara ubah: update paramset (promote) + catat who/when/why.

### D. Risk & stops

- `risk.min_atr14_pct` (0..1) — batas bawah ATR14% untuk eligible.
  - Origin: MAN/BT
  - Alasan: hindari ticker “mati” yang sulit capai target swing.
  - Kapan diubah: saat volatilitas market turun / win-rate turun karena move kecil.
  - Cara ubah: update paramset (BT/MAN).

- `risk.max_atr14_pct` (0..1) — batas atas ATR14% untuk eligible.
  - Origin: MAN/BT
  - Alasan: hindari ticker terlalu liar (gap/spike).
  - Kapan diubah: saat terlalu banyak false reject atau drawdown naik.
  - Cara ubah: update paramset (BT/MAN).

- `risk.atr_ideal_low` (0..1) — batas bawah “ideal ATR band”.
  - Origin: MAN/BT
  - Alasan: scoring/ranking bisa menganggap ATR mendekati band ini lebih sehat.
  - Kapan diubah: saat regime ATR bergeser.
  - Cara ubah: update paramset (BT/MAN).

- `risk.atr_ideal_high` (0..1) — batas atas “ideal ATR band”.
  - Origin: MAN/BT
  - Alasan: komplementer `atr_ideal_low`.
  - Kapan diubah: sama seperti `risk.atr_ideal_low`.
  - Cara ubah: update paramset (BT/MAN).

- `risk.stop_mode` (enum: `ATR` | `BAND` | `OFF`) — cara menentukan stop level di PLAN.
  - Origin: DET+MAN
  - Alasan: mode harus eksplisit supaya backtest/PLAN konsisten.
  - Kapan diubah: bila strategi stop berubah.
  - Cara ubah: ubah di paramset + jalankan contract tests.

- `risk.stop_atr_mult` (number > 0) — multiplier ATR untuk stop jika `stop_mode=ATR`.
  - Origin: MAN/BT
  - Alasan: mengontrol jarak stop agar sesuai karakter weekly swing.
  - Kapan diubah: saat stopout terlalu tinggi atau reward/risk memburuk.
  - Cara ubah: update paramset (BT/MAN).

- `risk.min_rr` (number >= 0) — RR minimum untuk kandidat (target vs stop).
  - Origin: MAN/BT
  - Alasan: menjaga trade setup tidak “jelek dari awal”.
  - Kapan diubah: saat win-rate tinggi tapi return kecil (atau sebaliknya).
  - Cara ubah: update paramset (BT/MAN).

### E. Setup

- `setup.roc_lo` (number) — batas bawah ROC untuk kandidat momentum minimum.
  - Origin: MAN/BT
  - Alasan: filter kandidat yang momentum-nya terlalu lemah.
  - Kapan diubah: saat market sideways panjang atau filter terlalu ketat.
  - Cara ubah: update paramset (BT/MAN).

- `setup.roc_hi` (number) — batas atas ROC untuk menghindari chase berlebihan (overextended).
  - Origin: MAN/BT
  - Alasan: weekly swing butuh space; terlalu tinggi rawan retrace.
  - Kapan diubah: saat terlalu banyak kandidat bagus terbuang / banyak false chase.
  - Cara ubah: update paramset (BT/MAN).

- `setup.mom_roc20_soft_min` (number) — soft minimum ROC20 untuk scoring momentum (bukan hard reject jika policy begitu).
  - Origin: MAN/BT
  - Alasan: memberi gradien skor; tidak semua kandidat harus “perfect”.
  - Kapan diubah: setelah recalibration BT atau perubahan indikator momentum.
  - Cara ubah: update paramset (BT/MAN) + contract tests.

- `setup.bo_trigger_mode` (enum: `HH20` | `NEAR_HH20` | `OFF`) — mode trigger breakout.
  - Origin: DET
  - Alasan: definisi breakout wajib tunggal agar scoring konsisten.
  - Kapan diubah: bila definisi breakout policy diubah.
  - Cara ubah: ubah di paramset + contract tests.

- `setup.bo_near_below_pct` (0..1) — toleransi “near breakout” (di bawah HH).
  - Origin: MAN/BT
  - Alasan: weekly swing sering masuk sebelum tembus; tapi tetap dibatasi.
  - Kapan diubah: saat terlalu banyak false breakout / terlalu sedikit kandidat.
  - Cara ubah: update paramset (BT/MAN).

- `setup.bo_max_ext_pct` (0..1) — batas over-extended di atas level breakout.
  - Origin: MAN/BT
  - Alasan: hindari chase breakout yang sudah terlalu jauh.
  - Kapan diubah: saat market kencang (butuh longgar) atau banyak retrace (butuh ketat).
  - Cara ubah: update paramset (BT/MAN).

### F. Scoring

- `scoring.combine_mode` (enum: `WEIGHTED_MEAN` | `RULE_BASED`) — cara menggabungkan sub-score.
  - Origin: DET
  - Alasan: supaya score_total reproducible & anti-drift.
  - Kapan diubah: hanya jika desain scoring berubah.
  - Cara ubah: ubah di paramset + update doc + contract tests.

- `scoring.weights.value.momentum` (number >= 0) — bobot score momentum.
  - Origin: MAN/BT
  - Alasan: mengatur kontribusi momentum ke score_total.
  - Kapan diubah: via calibration BT atau tuning manual terukur.
  - Cara ubah: update paramset (BT/MAN).

- `scoring.weights.value.breakout` (number >= 0) — bobot score breakout.
  - Origin: MAN/BT
  - Alasan: mengatur kontribusi breakout.
  - Kapan diubah: via calibration BT atau tuning manual.
  - Cara ubah: update paramset (BT/MAN).

- `scoring.weights.value.volume` (number >= 0) — bobot score volume.
  - Origin: MAN/BT
  - Alasan: mengatur kontribusi konfirmasi volume.
  - Kapan diubah: via calibration BT atau tuning manual.
  - Cara ubah: update paramset (BT/MAN).

- `scoring.weights.value.risk` (number >= 0) — bobot score risk/quality.
  - Origin: MAN/BT
  - Alasan: menyeimbangkan return vs risiko.
  - Kapan diubah: saat drawdown/stopout memburuk atau peluang bagus terlalu banyak terbuang.
  - Cara ubah: update paramset (BT/MAN).

### G. Grouping (dynamic selection)

- `grouping.top_picks_target` (integer >= 0) — base target TOP_PICKS.
  - Origin: MAN
  - Alasan: kontrol output (tidak overload).
  - Kapan diubah: kebutuhan output berubah.
  - Cara ubah: promote paramset baru.

- `grouping.secondary_target` (integer >= 0) — base target SECONDARY.
  - Origin: MAN
  - Alasan: kandidat cadangan tanpa memaksa TOP.
  - Kapan diubah: kebutuhan coverage berubah.
  - Cara ubah: promote paramset baru.

- `grouping.top_min_score_q` (0..1) — quantile cutoff TOP_PICKS.
  - Origin: BT
  - Alasan: cutoff adaptif terhadap distribusi score harian.
  - Kapan diubah: hanya via recalibration BT.
  - Cara ubah: kalibrasi BT → promote paramset.

- `grouping.secondary_min_score_q` (0..1) — quantile cutoff SECONDARY.
  - Origin: BT
  - Alasan: menjaga kualitas SECONDARY.
  - Kapan diubah: hanya via recalibration BT.
  - Cara ubah: kalibrasi BT → promote paramset.

- `grouping.grouping_mode` (enum: `QUANTILE_DYNAMIC` | `TARGET_ONLY`) — mode pembentukan grup.
  - Origin: DET
  - Alasan: supaya semantik grup konsisten lintas run.
  - Kapan diubah: jika policy grouping diubah.
  - Cara ubah: ubah di paramset + contract tests.

- `grouping.sort_keys` (array<string>) — urutan kunci sort untuk ranking final (LOCKED).
  - Origin: DET
  - Alasan: determinisme ranking (anti “reranking” tidak sengaja).
  - Kapan diubah: hanya saat desain ranking berubah (breaking change).
  - Cara ubah: ubah di paramset + update hash_contract + contract tests.

- `grouping.rounding_mode` (enum: `FLOOR` | `ROUND` | `CEIL`) — aturan pembulatan target dinamis (LOCKED).
  - Origin: DET
  - Alasan: determinisme jumlah picks per grup.
  - Kapan diubah: jika aturan pembulatan policy berubah.
  - Cara ubah: ubah di paramset + contract tests.

### H. Plan levels

- `plan_levels.entry_mode` (enum: `CLOSE_AS_REF` | `BAND` | `OFF`) — mode pembentukan `entry_ref`/band.
  - Origin: DET+MAN
  - Alasan: PLAN butuh level entry yang eksplisit untuk CONFIRM drift check.
  - Kapan diubah: jika definisi entry plan berubah.
  - Cara ubah: ubah di paramset + contract tests.

- `plan_levels.entry_band_pct` (0..1) — lebar band entry ±% dari `entry_ref` jika `entry_mode=BAND`.
  - Origin: MAN/BT
  - Alasan: memberi toleransi entry (weekly swing tidak harus presisi 1 tick).
  - Kapan diubah: jika terlalu sering drift/delay atau terlalu longgar.
  - Cara ubah: update paramset (BT/MAN) + contract tests.

### I. No-trade

- `no_trade.min_eligible_count` (integer >= 0) — minimum jumlah eligible ticker agar run dianggap valid.
  - Origin: MAN/BT
  - Alasan: jika universe terlalu kecil, output jadi noise dan menipu.
  - Kapan diubah: bila universe berubah besar atau data readiness sering reject.
  - Cara ubah: update paramset + contract tests.

- `no_trade.no_trade_hides_all` (bool) — jika TRUE, NO_TRADE menyembunyikan semua grup output (LOCKED).
  - Origin: DET
  - Alasan: menghindari user salah eksekusi saat kondisi data/market tidak layak.
  - Kapan diubah: hanya jika UX policy berubah (breaking change).
  - Cara ubah: ubah di paramset + contract tests.

### J. Confirm overlay

- `confirm_overlay.snapshot_max_age_sec` (integer > 0) — batas usia snapshot runtime; lebih tua → `WS_STALE`.
  - Origin: DET/MAN
  - Alasan: runtime CONFIRM harus memakai data segar.
  - Kapan diubah: jika frekuensi snapshot/latensi ingest berubah.
  - Cara ubah: update paramset.

- `confirm_overlay.max_drift_from_entry_pct` (0..1) — drift max dari `entry_ref` ke mid runtime; lebih jauh → `WS_DRIFT_FAR`.
  - Origin: MAN/BT
  - Alasan: mencegah buy saat harga sudah lari.
  - Kapan diubah: regime volatilitas berubah atau false delay terlalu sering.
  - Cara ubah: update paramset (BT/MAN).

- `confirm_overlay.spread_max_pct` (0..1) — spread max; lebih lebar → `WS_SPR_WIDE`.
  - Origin: MAN/BT
  - Alasan: spread lebar = likuiditas buruk saat runtime.
  - Kapan diubah: karakter spread berubah atau terlalu banyak false caution.
  - Cara ubah: update paramset (BT/MAN).

### K. Hash contract (reproducibility)

- `hash_contract.order_by` (array<string>) — urutan field untuk canonical hashing (LOCKED).
  - Origin: DET
  - Alasan: memastikan hash stabil lintas runtime/DB ordering.
  - Kapan diubah: jika schema output/hash input berubah (breaking change).
  - Cara ubah: ubah di paramset + update docs + contract tests.

- `hash_contract.scales` (map<string,number>) — skala/rounding angka sebelum hashing (LOCKED).
  - Origin: DET
  - Alasan: mencegah drift hash karena floating noise.
  - Kapan diubah: bila precision angka berubah di output.
  - Cara ubah: ubah di paramset + contract tests.

- `hash_contract.null_handling` (enum: `AS_NULL` | `AS_ZERO` | `DROP_FIELD`) — aturan null sebelum hashing (LOCKED).
  - Origin: DET
  - Alasan: determinisme hash saat ada null.
  - Kapan diubah: bila policy null berubah (breaking change).
  - Cara ubah: ubah di paramset + contract tests.

### L. Evaluation (backtest & OOS)

- `ws.eval.min_trades_oos` (integer >= 0) — minimum jumlah trade di OOS agar proof tidak bias sample kecil.
  - Origin: DET
  - Alasan: mencegah OOS “lulus” karena trade terlalu sedikit.
  - Kapan diubah: bila rentang backtest dipersingkat/diperpanjang signifikan.
  - Cara ubah: update paramset + jalankan ulang OOS proof.

- `ws.eval.min_trades` (integer >= 0) — minimum jumlah trade untuk evaluasi in-sample.
  - Origin: DET
  - Alasan: mencegah param menang karena sample kecil.
  - Kapan diubah: bila rentang backtest berubah signifikan.
  - Cara ubah: update paramset + rerun calibration.

- `ws.eval.min_days_covered` (integer >= 0) — minimum jumlah hari trading yang tercakup dalam window evaluasi agar hasil tidak bias karena coverage parsial.
  - Origin: DET
  - Default: ceil(0.70 * total_trading_days_in_window)
  - Alasan: mencegah pemilihan param yang hanya “aktif” pada sebagian kecil hari dalam window.
  - Kapan diubah: jika definisi `days_covered` berubah atau window backtest dipersingkat/diperpanjang signifikan.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

- `ws.eval.min_p25_ret_net_top` (decimal) — batas bawah percentile 25% return net untuk TOP bucket (downside bound sederhana).
  - Origin: DET
  - Default: -0.030000
  - Alasan: mencegah param terbaik yang avg bagus tapi downside terlalu dalam.
  - Kapan diubah: jika karakter volatilitas market berubah signifikan atau strategi ingin lebih agresif/defensif.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

- `ws.eval.min_month_win_rate_min` (decimal 0..1) — batas minimal win_rate bulanan terendah (stability gate).
  - Origin: DET
  - Default: 0.450000
  - Alasan: mencegah param menang karena 1 periode ekstrem, tapi buruk di bulan lain.
  - Kapan diubah: jika window evaluasi bukan bulanan atau definisi period berubah.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

- `ws.eval.min_month_avg_ret_net_min` (decimal) — batas minimal avg return net bulanan terendah (stability gate).
  - Origin: DET
  - Default: -0.010000
  - Alasan: membatasi kondisi terburuk per bulan agar param tidak memiliki “bulan jeblok” yang terlalu dalam.
  - Kapan diubah: jika strategi ingin lebih agresif/defensif atau definisi period berubah.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

## Next
### Weekly Swing
- `06_WS_PARAMSET_VALIDATOR_SPEC.md`