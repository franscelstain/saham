# Watchlist Pre-Open Specification (Sprint 0)

## 1. Tujuan Watchlist
Watchlist berfungsi sebagai **Pre-Open Selector** yang digunakan pada rentang waktu **08:45–08:55** sebelum bursa dibuka.

Tujuan utama:
- Menyediakan **daftar kandidat saham EOD yang ketat dan layak**
- Memberikan **panduan beli (entry guidance)**, bukan sinyal beli otomatis
- Membantu pengambilan keputusan **tanpa intraday noise**
- Keputusan akhir tetap berada di tangan user

Watchlist **BUKAN**:
- Intraday screener
- Auto trading
- Alat penentu exit

Intraday digunakan untuk **EXIT** dan ditempatkan di menu **Portfolio**.

---

## 2. Filter Keras (Wajib Lolos)
Jika salah satu filter gagal → saham **TIDAK masuk Watchlist**.

### 2.1 Trend
- Close > MA20 > MA50 > MA200

### 2.2 RSI
- RSI ≤ 70
- Ideal: 40–65
- 66–70 masih toleransi

### 2.3 Likuiditas
- Memenuhi minimum volume / value (ditentukan di config)

### 2.4 Expiry
- Masih dalam window strategi (mis. ≤ X hari sejak sinyal EOD)

---

## 3. Klasifikasi Setup
Digunakan untuk menjelaskan **kondisi saham**, bukan untuk intraday timing.

### Status Setup
- SETUP_OK → layak dipertimbangkan
- SETUP_CONFIRM → perlu kehati-hatian
- SETUP_AVOID → tidak direkomendasikan

Status ini **tidak menentukan beli sekarang**.

---

## 4. Struktur Output Watchlist

### 4.1 All Candidates
- Target jumlah: 10–30 saham
- Semua kandidat sudah lolos filter keras

### 4.2 Recommended Picks
- Subset dari All Candidates
- Jumlah: 1–3 saham
- Default rekomendasi: **1 saham unggulan**

---

## 5. Ranking & Prioritas

### 5.1 Setup Score
- Berdasarkan score_total EOD
- Menggambarkan kualitas teknikal setup

### 5.2 Action Rank
- Digunakan untuk urutan final di UI
- Berdasarkan kelayakan eksekusi pre-open
- Saham dengan risiko tinggi dapat turun peringkat meskipun setup score tinggi

UI **WAJIB** menggunakan action_rank, bukan setup_score mentah.

---

## 6. Entry Guidance (Panduan Beli)

Watchlist memberikan **saran**, bukan perintah.

### 6.1 Entry Mode
- PREOPEN_LIMIT  
  Boleh pasang antrian limit sebelum open
- WAIT_OPEN_CONFIRM  
  Disarankan menunggu konfirmasi setelah open (09:05–09:20)

### 6.2 Guidance Checklist
Contoh:
- Jika gap > harga maksimal → SKIP
- Jika open spike lalu rejection → tunggu / hindari
- Jangan kejar harga

---

## 7. Trade Plan (Per Kandidat)
Setiap kandidat harus memiliki:
- Entry price
- Stop Loss (SL)
- Take Profit 1 (TP1)
- Take Profit 2 (TP2)
- Break Even (BE)
- Risk Reward (RR)

Semua harga:
- Sudah tick-rounded (aturan IDX)
- Sudah memperhitungkan fee

---

## 8. Saran Strategi Pembelian
Watchlist boleh menyarankan:
- 1x entry (default)
- 2x entry (split) jika kondisi mendukung

Lot sizing dihitung berdasarkan:
- Buying power
- Risk per trade

---

## 9. Status yang DIIZINKAN di Watchlist
Hanya status berikut yang boleh muncul:

- SETUP_OK
- SETUP_CONFIRM
- SETUP_AVOID
- EXPIRED
- WAIT_EOD_INDICATORS

Status intraday seperti:
- STALE_INTRADAY
- BUY_PULLBACK
- WAIT_PULLBACK

❌ **TIDAK BOLEH ADA di Watchlist Pre-Open**

---

## 10. Prinsip UI
- Tidak ada auto-refresh intraday
- Watchlist bersifat statis (refresh manual)
- Klik row menampilkan detail & guidance lengkap
- Watchlist adalah alat bantu keputusan, bukan sinyal otomatis

---

## 11. Prinsip Arsitektur
- Watchlist hanya menggunakan data EOD
- Intraday hanya digunakan di Portfolio
- Logic trading mengikuti SRP
- Output Watchlist konsisten dan dapat dipakai ulang

---

## 12. Scope Sprint Selanjutnya
Sprint 0 selesai jika dokumen ini disepakati.

Sprint berikutnya:
- Sprint 1: Data & DTO
- Sprint 2: Filter keras & Setup
- Sprint 3: Trade Plan
- Sprint 4: Entry Guidance
- Sprint 5: Ranking & Picks
- Sprint 6: UI Pre-Open
