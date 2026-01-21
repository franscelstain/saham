# Policy: INTRADAY_LIGHT

Dokumen ini adalah **single source of truth** untuk policy ini.
Semua angka/threshold dan reason codes UI untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, schema output, namespace reason codes, tick rounding) ada di `WATCHLIST.md`.

---

**Tujuan:** memperbaiki timing entry untuk setup EOD kuat tanpa membangun sistem intraday penuh.

**Syarat mutlak:** ada *opening range snapshot* (09:00–09:15/09:30) minimal berisi: open_range_high/low, volume_opening, gap_pct_real.

**Rule ringkas:**
- Setup dari EOD tetap sumber utama (breakout/pullback/continuation).
- Intraday dipakai hanya untuk:
  - konfirmasi breakout (break above opening range high)
  - menghindari fake move (break lalu balik di bawah range)
- Kalau snapshot tidak ada → policy ini **tidak boleh aktif**.

**Algoritma ringkas (deterministik)**
1) Pastikan snapshot tersedia untuk `trade_date` (kalau tidak → policy nonaktif).
2) Dari EOD: pilih kandidat setup kuat (score tinggi) yang biasanya masuk top picks.
3) Definisikan entry trigger intraday:
   - Breakout: buy hanya jika harga break **di atas** `opening_range_high` dan tidak langsung gagal (OR fail).
   - Pullback: buy hanya jika harga bertahan di atas OR mid / reclaim OR high (pilih 1 rule dan konsisten).
4) SL intraday tetap mengacu ke level plan (ATR/support) dari EOD, bukan dibuat random.
5) Jika tidak ada konfirmasi sampai akhir window → `NO_TRADE` untuk ticker itu.

**Batasan:** policy ini bukan scalping. Ini hanya “konfirmasi entry” agar mengurangi fake breakout.
