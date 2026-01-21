# Policy: POSITION_TRADE

Dokumen ini adalah **single source of truth** untuk policy ini.
Semua angka/threshold dan reason codes UI untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, schema output, namespace reason codes, tick rounding) ada di `WATCHLIST.md`.

---

**Tujuan:** ride trend besar, bukan trading mingguan.

**Gates:**
- Trend quality tinggi (MA alignment kuat + signal continuation)
- Market regime risk-on
- Exit lebih longgar (ATR-based, trailing)

### 2.7 Urutan pemilihan policy (deterministik)
Agar hasil watchlist konsisten:
1) Jika market regime = risk-off → `NO_TRADE` (kecuali ada policy khusus hedge, kalau nanti ada)
2) Jika ada event dividen valid + lulus gates → `DIVIDEND_SWING`
3) Jika snapshot intraday tersedia + setup EOD kuat → `INTRADAY_LIGHT` (opsional)
4) Default → `WEEKLY_SWING`
5) Jika trend super kuat & kamu pilih mode long horizon → `POSITION_TRADE`

> Ini urutan default. Kalau nanti kamu mau mengunci “mode” (mis. minggu ini hanya weekly swing), tinggal override di layer config/preset UI.



---
