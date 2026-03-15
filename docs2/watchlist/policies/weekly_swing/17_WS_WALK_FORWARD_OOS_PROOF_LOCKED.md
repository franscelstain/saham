# 17 - WS Walk-Forward / Out-of-Sample Proof (LOCKED)

## Purpose (LOCKED)

Dokumen ini mengunci bukti OOS minimum yang wajib ada sebelum promote paramset WS.

Membuktikan bahwa parameter terbaik hasil kalibrasi tidak hanya menang di in-sample,
tetapi tetap perform di out-of-sample (OOS), sehingga layak dipromote menjadi candidate ACTIVE.

Kalibrasi dianggap tidak valid jika OOS proof tidak tersedia.

Rule (LOCKED):
Paramset hasil kalibrasi tidak boleh dipromote menjadi ACTIVE tanpa OOS proof yang lulus.
Tanpa OOS proof, hasil kalibrasi hanya boleh berstatus DRAFT.

## Prerequisites
### Weekly Swing
16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md

---

## Data window (LOCKED)
Backtest range ditentukan oleh (from_date, to_date) pada evaluasi.

Default split:
- In-sample (IS): 70% awal dari range
- Out-of-sample (OOS): 30% akhir dari range

Split harus berbasis urutan waktu (time series), bukan random split.

---

## Walk-forward protocol (LOCKED)

### Step 1: Calibrate on IS
- Jalankan backtest grid pada IS window.
- Pilih `param_id_best_is` dengan ranking policy + gating rules dari:
  [`16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`](16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md)

### Step 2: Evaluate on OOS
- Jalankan evaluasi pada OOS window menggunakan `param_id_best_is` TANPA re-tuning.
- Simpan hasilnya sebagai bukti OOS.
- Evidence dapat diringkas menggunakan format referensi pada [`_refs/WS_OOS_EVIDENCE_NOTE.md`](_refs/WS_OOS_EVIDENCE_NOTE.md), tetapi kewajiban bukti OOS tetap dikunci oleh dokumen ini.

### Step 3: Optional rolling walk-forward (recommended)

Rolling walk-forward adalah penguat bukti yang direkomendasikan, bukan syarat minimum promote kecuali kemudian dinyatakan LOCKED secara eksplisit.

Jika ingin lebih kuat, lakukan rolling window:
- Train 6 bulan → Test 3 bulan (rolling maju), ulang sampai akhir range.

Versi minimum yang wajib untuk validasi adalah split 70/30.

---

## OOS acceptance criteria (LOCKED)

Kriteria ini berlaku untuk proof minimum. Dokumen lain tidak boleh menurunkan threshold ini secara implisit.


A) Coverage
- picks_count_oos >= `ws.eval.min_trades_oos` (default 40 untuk 2 tahun split; documented)

B) Robust return
- avg_ret_net_top_oos > 0
- median_ret_net_top_oos >= 0 (minimal tidak negatif)

C) Stability
- month_win_rate_min_oos >= 0.45

D) Drawdown/downside bound
- p25_ret_net_top_oos >= -0.03 (atau threshold yang didokumentasi)

Jika gagal salah satu → param_id tidak boleh dipromote ACTIVE walaupun IS menang.

---

## Required artifacts (LOCKED)

Nama artefak fisik dapat bervariasi, tetapi shape evidence yang dipakai harus konsisten dan termasuk dalam artefak resmi yang diizinkan. Dokumen ini mengunci bukti minimum; allowlist artefak resminya tetap mengikuti manifest artefak WS.

Wajib ada 2 record evaluasi tersimpan:
1) IS eval: watchlist_bt_eval (from_date_is, to_date_is)
2) OOS proof: watchlist_bt_oos_eval_ws (from_date_oos, to_date_oos)

Output OOS wajib menyimpan:
- param_id_best_is
- metrik inti OOS
- link ke IS window
- policy_version / eval_model metadata

### eval_model format (LOCKED)
`eval_model` wajib merepresentasikan komponen perhitungan return secara eksplisit agar tidak menjadi label kosong.

Format minimum (LOCKED):
`ENTRY=ENTRY_RULE;EXIT=EXIT_RULE;HOLD=INT_DAYS;FEE=FEE_MODEL;SLIP=DECIMAL_PCT`

Enum minimum yang diizinkan:
- `ENTRY_RULE`: `NEXT_OPEN`
- `EXIT_RULE`: `STOP_TP_OR_TIME`, `TIME_ONLY`
- `FEE_MODEL`: `IDR_FIXED`, `IDR_TIERED`
- `SLIP`: angka desimal non-negatif dalam format string ringkas (contoh `0`, `0.001`)

Contoh valid:
- `ENTRY=NEXT_OPEN;EXIT=STOP_TP_OR_TIME;HOLD=5;FEE=IDR_FIXED;SLIP=0`
- `ENTRY=NEXT_OPEN;EXIT=TIME_ONLY;HOLD=5;FEE=IDR_TIERED;SLIP=0.001`

Catatan implementasi:
- Panjang `eval_model` harus cukup untuk menyimpan string canonical ini utuh; DDL backtest mengunci kolom sebagai `VARCHAR(96)`.

## Next
### Weekly Swing
- 18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md