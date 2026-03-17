# 05 — Parameter Registry (Complete) — Weekly Swing

## Purpose

Dokumen ini adalah registry canonical key WS yang diizinkan. Dokumen ini bukan validator runtime dan bukan prosedur promotion.

Daftar parameter WS yang **exhaustive**: semua key yang boleh dipakai runtime oleh code. Jika code butuh parameter, key **wajib** ada di file ini **dan** ada di params_json (paramset).

## Prerequisites
### Weekly Swing
- [`04_WS_PARAMSET_JSON_CONTRACT.md`](04_WS_PARAMSET_JSON_CONTRACT.md)

## Inputs
- params_json WS

## Process
Gunakan registry ini sebagai sumber kebenaran definisi per key.

### A. Meta & data contract
- `data_contract.required_sources` (DET/ACTIVE)
- `data_contract.required_fields` (DET/ACTIVE)
- `data_contract.disabled_fields` (DET+MAN/ACTIVE)

### B. Data readiness & outlier
- `data_readiness.min_coverage_ratio` (DET/ACTIVE, locked)
- `data_readiness.min_history_days` (DET/ACTIVE)
- `data_readiness.max_missing_bar_days_60d` (MAN/ACTIVE, bt_target=true)
- `data_readiness.reject_if_eod_incomplete` (DET/ACTIVE)
- `data_readiness.outlier_ruleset` (DET+MAN/ACTIVE)
- `data_readiness.outlier_ruleset.value.enabled` (DET+MAN/ACTIVE)
- `data_readiness.outlier_ruleset.value.max_abs_return_1d_pct` (MAN/ACTIVE, bt_target=true)
- `data_readiness.outlier_ruleset.value.max_high_low_range_1d_pct` (MAN/ACTIVE, bt_target=true)

### C. Liquidity
- `liquidity.min_dv20_idr` (MAN/ACTIVE, bt_target=true)
- `liquidity.dv20_strong_idr` (MAN/ACTIVE, bt_target=true)
- `liquidity.exclude_tickers` (MAN/ACTIVE)

### C1. Volume confirmation
- `volume.min_vol_ratio` (MAN/ACTIVE, bt_target=true)

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
- `scoring.weights` (MAN/ACTIVE, bt_target=true)
- `scoring.weights.value.momentum` (MAN/ACTIVE, bt_target=true)
- `scoring.weights.value.breakout` (MAN/ACTIVE, bt_target=true)
- `scoring.weights.value.volume` (MAN/ACTIVE, bt_target=true)
- `scoring.weights.value.risk` (MAN/ACTIVE, bt_target=true)

### G. Grouping (dynamic selection)
- `grouping.secondary_target` (MAN/ACTIVE)
- `grouping.top_picks_target` (MAN/ACTIVE)
- `grouping.secondary_min_score_q` (BT/ACTIVE, bt_target=true)
- `grouping.top_min_score_q` (BT/ACTIVE, bt_target=true)
- `grouping.grouping_mode` (DET/ACTIVE)
- `grouping.sort_keys` (DET/ACTIVE, locked)
- `grouping.rounding_mode` (DET/ACTIVE, locked)
- `grouping.min_count_overrides` (MAN/ACTIVE)

### H. Plan levels
- `plan_levels.entry_mode` (DET+MAN/ACTIVE)
- `plan_levels.entry_band_pct` (MAN/ACTIVE, bt_target=true)

### I. No-trade
- `no_trade.min_eligible_count` (MAN/ACTIVE, bt_target=true)
- `no_trade.no_trade_hides_all` (DET/ACTIVE, locked, fixed=true)

### J. Confirm overlay
- `confirm_overlay.snapshot_max_age_sec` (DET/ACTIVE, locked, fixed=900)
- `confirm_overlay.max_drift_from_entry_pct` (MAN/ACTIVE, bt_target=true)

### K. Hash contract (reproducibility)
- `hash_contract.version` (DET/ACTIVE, locked)
- `hash_contract.order_by` (DET/ACTIVE, locked)
- `hash_contract.scales` (DET/ACTIVE, locked)
- `hash_contract.scales.value.close_price_dp` (DET/ACTIVE, locked)
- `hash_contract.scales.value.hh20_dp` (DET/ACTIVE, locked)
- `hash_contract.scales.value.roc20_dp` (DET/ACTIVE, locked)
- `hash_contract.scales.value.atr14_pct_dp` (DET/ACTIVE, locked)
- `hash_contract.scales.value.dv20_idr_dp` (DET/ACTIVE, locked)
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
   - Tindakan: sesuaikan guard `risk.max_atr14_pct` atau `scoring.weights.value.risk`.

3) **Regime likuiditas berubah** (DET/MAN)
   - DV20 median universe turun sehingga coverage eligible jatuh drastis, atau sebaliknya terlalu longgar.
   - Tindakan: sesuaikan `liquidity.min_dv20_idr`.

4) **Data quality issues** (DET)
   - Missing/aneh/0 pada field wajib meningkat.
   - Tindakan: perketat readiness rules atau stop run (fail_code), bukan “menurunkan cutoff”.

## Update method (wajib)

Aturan perubahan status paramset dan prosedur promote ACTIVE tetap dimiliki oleh `20_WS_CANONICAL_PARAMSET_PROCEDURES.md`.

- Origin **BT**: hanya boleh diubah via proses backtest calibration + promote ke paramset baru.
- Origin **DET**: perubahan harus disertai reasoning tertulis (prinsip pasar) dan dites pada window historis minimum.
- Origin **MAN**: perubahan boleh manual, tapi wajib tercatat (who/when/why) dan menghasilkan paramset baru.

### Rule (LOCKED): BT origin must be proven
Parameter boleh origin=BT hanya jika tercakup pada [`14_WS_BT_COVERAGE_MATRIX_LOCKED.md`](14_WS_BT_COVERAGE_MATRIX_LOCKED.md).
Jika tidak, origin wajib MAN/DET sampai coverage valid.

## Namespace rules (LOCKED)
Catatan (LOCKED): di dokumen, a.b.c adalah notasi referensi; JSON paramset tetap nested (`{a:{b:{c:...}}}`).

Untuk Weekly Swing, namespace parameter bersifat tunggal dan canonical. Code wajib membaca key canonical saja:
- Alias (key alternatif) dilarang di runtime.
- Key lama di dokumen legacy dianggap salah dan harus dinormalisasi.

## Registry normalization rules (LOCKED)
- Registry ini hanya boleh mendaftarkan **key canonical yang benar-benar ada** pada struktur JSON paramset.
- Shorthand seperti BAD EXAMPLE (NOT A KEY): a.<b,c> atau wildcard semu seperti `a.*` **dilarang** di registry utama karena bukan key JSON nyata.
- Jika sebuah node adalah audit object, registry wajib mendaftarkan node base-nya dan, bila perlu, child key canonical satu per satu.
- Jika sebuah field adalah array scalar, registry hanya boleh mendaftarkan node array-nya sendiri; elemen array bukan key terpisah.
- Setiap penambahan key baru wajib dilakukan serentak di: contract JSON, registry ini, validator spec, contoh paramset, dan contract tests.

## Per-Parameter Definitions (LOCKED)

### A. Meta & data contract

- `data_contract.required_sources` (array<string>) — daftar sumber data yang wajib tersedia untuk run.
  - Origin: DET
  - Alasan: mencegah PLAN dibuat dari input parsial/acak.
  - Kapan diubah: bila pipeline/sumber data berubah.
  - Cara ubah: ubah di paramset + jalankan contract tests.

- `data_contract.required_fields` (object) — container daftar field wajib per source (base audit node).
  - Origin: DET
  - Alasan: paramset menyimpan node base sebagai object audit `{ value, origin, ... }`, jadi base key wajib terdaftar agar registry benar-benar exhaustive.
  - Kapan diubah: hanya jika struktur required_fields berubah (breaking).
  - Cara ubah: update paramset + update contract tests.

- `data_contract.required_fields.value` (array<string>) — daftar field minimum yang wajib ada pada dataset WS.
  - Origin: DET
  - Alasan: memastikan guards, scoring, dan plan levels tidak dihitung dari field kosong/hilang.
  - Kapan diubah: bila formula indikator berubah atau field baru menjadi wajib secara resmi.
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

- `data_readiness.outlier_ruleset` (object) — container aturan outlier.
  - Origin: DET+MAN
  - Alasan: node base wajib ada karena paramset menyimpan audit object di base key (`{ value, origin, ... }`).
  - Kapan diubah: jarang; berubah hanya jika struktur outlier ruleset berubah (breaking).
  - Cara ubah: update paramset + contract tests.

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
  - Alasan: blacklist operasional (trading suspend/halt, UMA/anomali harga-volume, corporate action yang mengganggu sinyal seperti split/rights/dividen, atau data vendor tidak stabil).
  - Kapan diubah: saat ada case khusus.
  - Cara ubah: update paramset (promote) + catat who/when/why.

### C1. Volume confirmation
- `volume.min_vol_ratio` (number >= 0) — batas minimum `vol_ratio` untuk lolos guard volume.
  - Origin: MAN/ACTIVE, bt_target=true
  - Rationale: konfirmasi bahwa pergerakan harga didukung volume; mencegah sinyal lemah pada volume kering.
  - Change triggers: recalibrate BT 2y; perubahan karakter volume pasar.

### D. Risk & stops

- `risk.min_atr14_pct` (0..1) — batas bawah ATR14% untuk eligible.
  - Origin: MAN/BT
  - Unit: fraction (0..1); contoh 0.02 = 2% dari harga.
  - Forbidden: input percent-point (contoh 1.0 = 1%) — ini dianggap salah unit dan harus FAIL di validator.
  - Alasan: hindari ticker “mati” yang sulit capai target swing.
  - Kapan diubah: saat volatilitas market turun / win-rate turun karena move kecil.
  - Cara ubah: update paramset (BT/MAN).

- `risk.max_atr14_pct` (0..1) — batas atas ATR14% untuk eligible.
  - Origin: MAN/BT
  - Unit: fraction (0..1); contoh 0.02 = 2% dari harga.
  - Forbidden: input percent-point (contoh 1.0 = 1%) — ini dianggap salah unit dan harus FAIL di validator.
  - Alasan: hindari ticker terlalu liar (gap/spike).
  - Kapan diubah: saat terlalu banyak false reject atau drawdown naik.
  - Cara ubah: update paramset (BT/MAN).

- `risk.atr_ideal_low` (0..1) — batas bawah “ideal ATR band”.
  - Origin: MAN/BT
  - Unit: fraction (0..1); contoh 0.02 = 2% dari harga.
  - Forbidden: input percent-point (contoh 1.0 = 1%) — ini dianggap salah unit dan harus FAIL di validator.
  - Alasan: scoring/ranking bisa menganggap ATR mendekati band ini lebih sehat.
  - Kapan diubah: saat regime ATR bergeser.
  - Cara ubah: update paramset (BT/MAN).

- `risk.atr_ideal_high` (0..1) — batas atas “ideal ATR band”.
  - Origin: MAN/BT
  - Unit: fraction (0..1); contoh 0.02 = 2% dari harga.
  - Forbidden: input percent-point (contoh 1.0 = 1%) — ini dianggap salah unit dan harus FAIL di validator.
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

- `scoring.weights` (object) — container bobot scoring.
  - Origin: MAN/BT
  - Alasan: node base wajib ada karena paramset menyimpan audit object pada base key (`value` berisi map bobot).
  - Kapan diubah: saat komposisi bobot berubah (mis. tambah/remove komponen scoring) atau hasil BT/kalibrasi berubah.
  - Cara ubah: update paramset (BT/MAN) + contract tests.

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

- `grouping.grouping_mode` (LOCKED enum: `QUALIFIED_POOLS_QUANTILE_CUTOFF`) — mode pembentukan grup.
  - Origin: DET
  - Alasan: selection Weekly Swing hanya menggunakan qualified pools + cutoff quantile + target dinamis.
  - Kapan diubah: hanya jika desain selection policy berubah secara breaking.
  - Cara ubah: ubah registry + validator + contract tests secara serempak.

- `grouping.sort_keys` (LOCKED array<string>) — urutan kunci sort untuk ranking final.
  - Exact order:
    1. `score_total_desc`
    2. `score_breakout_desc`
    3. `score_momentum_desc`
    4. `dv20_idr_desc`
    5. `atr14_pct_asc`
    6. `ticker_id_asc`
  - Origin: DET
  - Alasan: determinisme ranking (anti “reranking” tidak sengaja).
  - Kapan diubah: hanya saat desain ranking berubah (breaking change).
  - Cara ubah: ubah registry + validator + hash_contract + contract tests secara serempak.

- `grouping.rounding_mode` (enum: `FLOOR` | `ROUND` | `CEIL`, LOCKED) — aturan pembulatan target dinamis.
  - Origin: DET
  - Alasan: determinisme jumlah picks per grup.
  - Kapan diubah: hanya jika aturan pembulatan policy berubah (breaking change).
  - Cara ubah: update dok LOCKED + update validator + update contract tests + promote paramset baru.

- `grouping.min_count_overrides` (object) — override minimum count per group (opsional; default `{}`).
  - Origin: MAN
  - Alasan: disediakan untuk kasus operasional tertentu tanpa mengubah algoritma grouping.
  - Kapan diubah: saat butuh override minimum count (mis. force minimal TOP/SECONDARY dalam kondisi tertentu).
  - Cara ubah: update paramset + contract tests.

### H. Plan levels

- `plan_levels.entry_mode` (LOCKED enum: `BREAKOUT`) — mode pembentukan entry band.
  - Origin: DET
  - Alasan: Weekly Swing menggunakan entry band breakout yang diturunkan menjadi `entry_ref`, `entry_band_low`, dan `entry_band_high`.
  - Kapan diubah: hanya jika desain PLAN berubah secara breaking.
  - Cara ubah: ubah registry + validator + active paramset + algorithm docs secara serempak.

- `plan_levels.entry_band_pct` (0..1) — lebar band entry ±% dari `entry_ref` pada mode `BREAKOUT`.
  - Origin: MAN/BT
  - Alasan: memberi toleransi entry (weekly swing tidak harus presisi 1 tick).
  - Kapan diubah: jika terlalu sering drift/delay atau terlalu longgar.
  - Cara ubah: update paramset (BT/MAN) + contract tests.

### I. No-trade

- `no_trade.min_eligible_count` (integer >= 1) — minimum jumlah eligible ticker agar run dianggap valid.
  - Origin: MAN/BT
  - Alasan: jika universe terlalu kecil, output jadi noise dan menipu.
  - Kapan diubah: bila universe berubah besar atau data readiness sering reject.
  - Cara ubah: update paramset + contract tests.

- `no_trade.no_trade_hides_all` (bool, LOCKED) — jika TRUE, NO_TRADE menyembunyikan semua grup output.
  - Fixed value: true
  - Origin: DET
  - Alasan: menghindari user salah eksekusi saat kondisi data/market tidak layak.
  - Kapan diubah: hanya jika UX policy berubah (breaking change).
  - Cara ubah: update dok LOCKED + update validator + update contract tests + promote paramset baru (bukan sekadar tweak).

### J. Confirm overlay

- `confirm_overlay.snapshot_max_age_sec` (integer, LOCKED) — batas usia snapshot runtime; lebih tua → `WS_STALE`.
  - Fixed value: 900 (15 menit)
  - Origin: DET
  - Alasan: TTL adalah batas validitas minimum untuk mencegah CONFIRM memakai snapshot basi.
  - Kapan diubah: hanya jika policy WS mengubah definisi “fresh snapshot” (breaking change).
  - Cara ubah: update dok LOCKED + update contract tests + promote paramset baru (bukan sekadar tweak). 

- `confirm_overlay.max_drift_from_entry_pct` (0..1) — drift max dari `entry_ref` ke `last_price`; lebih jauh → `WS_DRIFT_FAR`.
  - Origin: MAN/BT
  - Alasan: mencegah buy saat harga sudah lari.
  - Kapan diubah: regime volatilitas berubah atau false delay terlalu sering.
  - Cara ubah: update paramset (BT/MAN).

### K. Hash contract (reproducibility)

- `hash_contract.version` (integer, LOCKED) — versi kontrak canonical hash.
  - Origin: DET
  - Alasan: memastikan perubahan struktur hashing bisa terdeteksi dan di-audit (anti silent change).
  - Kapan diubah: hanya jika payload canonical hash berubah (breaking change).
  - Cara ubah: update dok LOCKED + update validator + update contract tests + promote paramset baru.

- `hash_contract.order_by` (enum/string, LOCKED) — strategi urutan canonical untuk hashing.
  - Origin: DET
  - Alasan: memastikan hash stabil lintas runtime/DB ordering.
  - Kapan diubah: jika schema output/hash input berubah (breaking change).
  - Cara ubah: update dok LOCKED + update validator + update contract tests + promote paramset baru.
  - Nilai ACTIVE yang diizinkan saat ini: `ticker_id_asc`.

- `hash_contract.scales` (map<string,number>, LOCKED) — skala/rounding angka sebelum hashing.
  - Origin: DET
  - Alasan: mencegah drift hash karena floating noise.
  - Kapan diubah: bila precision angka berubah di output (breaking change).
  - Cara ubah: update dok LOCKED + update validator + update contract tests + promote paramset baru.

- `hash_contract.scales.value.close_price_dp` (integer >= 0, LOCKED) — decimal places untuk close_price sebelum hashing.
  - Origin: DET
  - Alasan: stabilisasi hash untuk harga.
  - Kapan diubah: jika precision close_price di output berubah (breaking).
  - Cara ubah: update dok LOCKED + validator + tests + promote paramset baru.

- `hash_contract.scales.value.hh20_dp` (integer >= 0, LOCKED) — decimal places untuk hh20 sebelum hashing.
  - Origin: DET
  - Alasan: stabilisasi hash untuk indikator hh20.
  - Kapan diubah: jika precision hh20 di output berubah (breaking).
  - Cara ubah: sama seperti close_price_dp.

- `hash_contract.scales.value.roc20_dp` (integer >= 0, LOCKED) — decimal places untuk roc20 sebelum hashing.
  - Origin: DET
  - Alasan: stabilisasi hash untuk indikator roc20.
  - Kapan diubah: jika precision roc20 di output berubah (breaking).
  - Cara ubah: sama seperti close_price_dp.

- `hash_contract.scales.value.atr14_pct_dp` (integer >= 0, LOCKED) — decimal places untuk atr14_pct sebelum hashing.
  - Origin: DET
  - Alasan: stabilisasi hash untuk indikator atr14_pct.
  - Kapan diubah: jika precision atr14_pct di output berubah (breaking).
  - Cara ubah: sama seperti close_price_dp.

- `hash_contract.scales.value.dv20_idr_dp` (integer >= 0, LOCKED) — decimal places untuk dv20_idr sebelum hashing.
  - Origin: DET
  - Alasan: stabilisasi hash untuk indikator dv20_idr.
  - Kapan diubah: jika precision dv20_idr di output berubah (breaking).
  - Cara ubah: sama seperti close_price_dp.

- `hash_contract.null_handling` (enum: `EXCLUDE_FROM_HASH_PAYLOAD`, LOCKED) — aturan null canonical sebelum hashing.
  - Origin: DET
  - Alasan: determinisme hash saat ada null.
  - Kapan diubah: bila policy null berubah (breaking change).
  - Cara ubah: update dok LOCKED + update validator + update contract tests + promote paramset baru.
  - Nilai ACTIVE yang diizinkan saat ini: `EXCLUDE_FROM_HASH_PAYLOAD`.

### L. Evaluation (backtest & OOS)

- `eval.min_trades_oos` (integer >= 0) — minimum jumlah trade di OOS agar proof tidak bias sample kecil.
  - Origin: DET
  - Alasan: mencegah OOS “lulus” karena trade terlalu sedikit.
  - Kapan diubah: bila rentang backtest dipersingkat/diperpanjang signifikan.
  - Cara ubah: update paramset + jalankan ulang OOS proof.

- `eval.min_trades` (integer >= 0) — minimum jumlah trade untuk evaluasi in-sample.
  - Origin: DET
  - Alasan: mencegah param menang karena sample kecil.
  - Kapan diubah: bila rentang backtest berubah signifikan.
  - Cara ubah: update paramset + rerun calibration.

- `eval.min_days_covered` (integer >= 0) — minimum jumlah hari trading yang tercakup dalam window evaluasi agar hasil tidak bias karena coverage parsial.
  - Origin: DET
  - Default: ceil(0.70 * total_trading_days_in_window)
  - Alasan: mencegah pemilihan param yang hanya “aktif” pada sebagian kecil hari dalam window.
  - Kapan diubah: jika definisi `days_covered` berubah atau window backtest dipersingkat/diperpanjang signifikan.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

- `eval.min_p25_ret_net_top` (decimal) — batas bawah percentile 25% return net untuk TOP bucket (downside bound sederhana).
  - Origin: DET
  - Default: -0.030000
  - Alasan: mencegah param terbaik yang avg bagus tapi downside terlalu dalam.
  - Kapan diubah: jika karakter volatilitas market berubah signifikan atau strategi ingin lebih agresif/defensif.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

- `eval.min_month_win_rate_min` (decimal 0..1) — batas minimal win_rate bulanan terendah (stability gate).
  - Origin: DET
  - Default: 0.450000
  - Alasan: mencegah param menang karena 1 periode ekstrem, tapi buruk di bulan lain.
  - Kapan diubah: jika window evaluasi bukan bulanan atau definisi period berubah.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

- `eval.min_month_avg_ret_net_min` (decimal) — batas minimal avg return net bulanan terendah (stability gate).
  - Origin: DET
  - Default: -0.010000
  - Alasan: membatasi kondisi terburuk per bulan agar param tidak memiliki “bulan jeblok” yang terlalu dalam.
  - Kapan diubah: jika strategi ingin lebih agresif/defensif atau definisi period berubah.
  - Cara ubah: update paramset evaluasi + jalankan ulang kalibrasi.

## Next
### Weekly Swing
- [`06_WS_PARAMSET_VALIDATOR_SPEC.md`](06_WS_PARAMSET_VALIDATOR_SPEC.md)
