# Policy: DIVIDEND_SWING

Dokumen ini adalah **single source of truth** untuk policy ini.
Semua angka/threshold dan reason codes UI untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, schema output, namespace reason codes, tick rounding) ada di `WATCHLIST.md`.

---

**Tujuan:** dapat dividen tanpa bunuh diri karena gap risk.

**A. Data tambahan yang dibutuhkan (kalau belum ada, lihat Bagian 11):**
- `dividend_calendar` (ticker, ex_date, pay_date, dividend_amount, yield_est)
- flag corporate action/split adjusted

**B. Gates khusus:**
- Wajib **likuid** (dv20 bucket A)
- Hindari saham dengan `atr_pct` tinggi (gap risk besar)
- Market regime minimal neutral (risk-off → NO TRADE)

**C. Timing:**
- Entry ideal: H-3 sampai H-1 ex-date (bukan H0). Hindari entry mepet close jika spread jelek.
- Exit rule:
  - Conservative: exit H-1 / H0 (sebelum ex-date) jika target tercapai.
  - Hold-through: tahan sampai ex-date hanya jika trend kuat + risk rendah.

**D. Algoritma ringkas (deterministik)**
1) Ambil event `ex_date` untuk 7–14 hari ke depan.
2) Filter ticker event:
   - yield_est memadai (opsional) dan **likuid** (dv20 bucket A)
   - atr_pct tidak liar
3) Jika market regime risk-off → `NO_TRADE` (policy batal).
4) Entry window default mengikuti Bagian 8, tapi tambah rule:
   - hindari entry mepet close (match risk)
   - hindari entry H0 (ex-date) kecuali super liquid + follow-through
5) Buat trade plan:
   - SL lebih ketat (gap risk)
   - TP lebih konservatif (karena tujuan event-driven)
6) Exit:
   - default: exit H-1/H0 bila target tercapai
   - hold-through hanya jika trend kualitas tinggi (MA alignment + signal continuation)

**E. Catatan penting:** dividend policy butuh data event; tanpa itu jangan dipaksakan (lebih baik tidak aktif daripada salah).
