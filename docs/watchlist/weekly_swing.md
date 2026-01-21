# Policy: WEEKLY_SWING

Dokumen ini adalah **single source of truth** untuk policy ini.
Semua angka/threshold dan reason codes UI untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, schema output, namespace reason codes, tick rounding) ada di `WATCHLIST.md`.

---

**Tujuan:** ambil move mingguan yang realistis, bukan “tebak puncak”.

**A. Data dependency (wajib):**
- `ticker_ohlc_daily` canonical (trade_date = effective_end_date)
- `ticker_indicators_daily`: MA20/50/200, RSI14, vol_sma20, vol_ratio, signal_code, signal_age_days
- fitur murah: `atr14`, `atr_pct`, `gap_pct`, `dv20`, candle flags

**B. Candidate gates (deterministik):**
- Likuiditas: `dv20` minimal (mis. bucket A/B). Bucket C boleh masuk kandidat, tapi **bukan recommended**.
- Volatilitas: `atr_pct` maksimal (saham terlalu liar → turunkan confidence / NO TRADE).
  - **Default weekly swing (bisa di-config):**
    - `atr_pct <= 0.07` → ok untuk recommended
    - `0.07 < atr_pct <= 0.10` → kandidat boleh, **recommended turun** (size_multiplier max 0.8)
    - `atr_pct > 0.10` → default `trade_disabled` (kecuali ada override manual)
- Data window lengkap: indikator inti tidak boleh NULL.
- Corporate action suspected/unadjusted → skip.

**C. Entry window (EOD → waktu eksekusi):**
- Default: pakai engine di Bagian 8 (window + avoid windows).
- Tambahan weekly swing:
  - **Senin–Selasa:** boleh entry agresif (breakout confirm / pullback buy)
  - **Rabu:** entry selektif; prefer pullback, hindari chasing
  - **Kamis:** entry hanya kalau setup “sangat kuat” (High confidence) dan risk kecil
  - **Jumat:** default NO NEW ENTRY (kecuali signal baru + risk rendah)


**C.1 Anti-chasing & gap guard (WEEKLY_SWING wajib, deterministik)**
Karena watchlist EOD-only, guard ini mencegah entry “ketinggalan kereta” dan jebakan gap.
- **Gap-up block (pre-buy checklist + reason code):** jika saat eksekusi harga/open gap-up > `max_gap_up_pct` dari `prev_close` → **NO ENTRY**, tunggu pullback.
  - Default: `max_gap_up_pct = min(0.025, 1.0*atr_pct)`.
- **Distance-to-trigger block (berbasis EOD):** untuk breakout, jika `close > trigger + 0.5*ATR14` → jangan entry breakout; ubah jadi `PULLBACK_LIMIT` atau `WATCH_ONLY`.
- **Late-week entry penalty:** Kamis/Jumat, kalau bukan High confidence → downgrade menjadi `WATCH_ONLY`.
- Semua guard di atas harus memunculkan reason codes: `GAP_UP_BLOCK`, `CHASE_BLOCK_DISTANCE_TOO_FAR`, `LATE_WEEK_ENTRY_PENALTY`.

**D. Expiry (setup kadaluarsa):**
- Breakout/strong burst: valid maksimal 1–2 trading days setelah sinyal muncul.
- Pullback/continuation: valid 2–4 trading days.
- Aturan praktis: `signal_age_days > 3` → turun confidence; `> 5` → bukan recommended.

**E. Exit & lifecycle rules (WEEKLY_SWING wajib, bukan opsional):**
- **Hard SL:** selalu ada (ATR/support/MA). Saat entry terjadi, SL **tidak boleh NULL**. (lihat Bagian 20)
- **Partial take:** default ambil parsial di TP1 (contoh 40%–60%), sisanya ke TP2/trailing.
- **Break-even rule:** setelah TP1 hit, naikkan SL minimal ke `entry` (atau `entry + 0.2R` untuk cover fee).
- **Time stop (deterministik):**
  - Setelah **2 trading days** dari entry: jika belum mencapai `+0.5R` → `REDUCE 50%` atau `EXIT` (tergantung market regime).
  - Setelah **3 trading days** dari entry: jika masih “flat” / gagal follow-through → `EXIT`.
- **Max holding (deterministik):**
  - Breakout/Strong burst: max **2–3** trading days.
  - Pullback/Continuation: max **3–5** trading days.
  - Reversal: max **2–4** trading days.
- **Friday exit bias:** Jumat siang/sore default **prioritas exit** untuk posisi yang belum hit TP1 (hindari nyangkut). Reason: `FRIDAY_EXIT_BIAS`.
- **Weekend rule:** default **hindari carry over weekend** kecuali `confidence=High` + trend kuat + SL sudah naik (BE/trailing). Reason: `WEEKEND_RISK_BLOCK`.

**F. Algoritma ringkas (deterministik, step-by-step)**
1) Tentukan `effective_trade_date` dari Market Data (cutoff-aware) → ini jadi `trade_date` watchlist.
2) Ambil fitur per ticker untuk `trade_date` dari `ticker_indicators_daily` (plus fitur murah: atr/gap/dv20/candle).
3) Jalankan **Hard Filters** (Bagian 5.1). Jika gagal → drop.
4) Jalankan **Soft Filters** (Bagian 5.2). Jika kena → turunkan confidence/score.
5) Hitung **watchlist_score** (Bagian 6). Wajib simpan breakdown reason codes.
6) Tentukan `setup_type` (Bagian 7).
7) Tentukan `entry_windows` & `avoid_windows` (Bagian 8) + day-of-week adjustment.
8) Ranking: pilih Top Picks (Bagian 10).
9) Tentukan `buy_mode` dan `% alloc` (Bagian 19).
10) Untuk recommended pick: hitung `trade_plan` (Bagian 20) + sizing lots (Bagian 21).
11) Simpan output ke tabel watchlist (Bagian 12) + log summary per hari.

**G. Anti-bias penting:**
- Jangan “memaksa” selalu ada BUY. Kalau market regime risk-off atau kualitas data jelek → `NO_TRADE`.
- Kalau indikator window tidak lengkap (Compute EOD mengembalikan NULL), watchlist harus downgrade/skip.
