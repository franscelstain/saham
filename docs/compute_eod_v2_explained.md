# Compute EOD v2 — Explained (Untuk Pemula & Developer)

Dokumen ini menjelaskan **Compute EOD v2** dengan bahasa sederhana.
Tujuannya supaya:
- mudah dipahami walau baru belajar,
- tidak salah tafsir istilah teknikal,
- dan konsisten dengan Sprint 1–3 yang sudah kita bangun.

---

## 1. Apa itu Compute EOD?

**Compute EOD (End Of Day)** adalah proses yang dijalankan **setelah bursa tutup** untuk:

1. Membaca data harga harian (OHLC & volume)
2. Menghitung indikator teknikal
3. Menentukan **jenis sinyal** dan **kondisi volume**
4. Menyimpan hasilnya ke database

Compute EOD **TIDAK**:
- melakukan beli / jual
- menentukan entry intraday
- mengeksekusi trading

Compute EOD hanya menyediakan **fakta teknikal**, bukan keputusan.

---

## 2. Kenapa Compute EOD Penting?

Tanpa Compute EOD:
- Watchlist tidak tahu kondisi teknikal sebenarnya
- Sistem tidak bisa membedakan sinyal baru vs sinyal lama
- Entry bisa telat (mengejar harga)
- Expiry filter tidak bisa bekerja

Dengan Compute EOD:
- Semua keputusan berbasis data
- Watchlist jadi konsisten & repeatable
- Sistem tidak bergantung feeling

---

## 3. Data yang Dihasilkan Compute EOD

Compute EOD v2 menghasilkan data di tabel:

`ticker_indicators_daily`

### 3.1 Indikator Teknikal

Indikator adalah **alat ukur**, bukan sinyal beli.

| Indikator | Fungsi Sederhana |
|---------|------------------|
| MA20 / MA50 / MA200 | Melihat arah trend |
| RSI | Melihat apakah harga sudah terlalu tinggi |
| ATR | Mengukur jarak stop loss wajar |
| Support / Resistance | Level penting harga |

> Catatan pemula:  
> Indikator **tidak menyuruh beli**, hanya membantu membaca kondisi.

---

## 4. Signal (Signal Code)

### Apa itu Signal?
**Signal** menjawab pertanyaan:
> "Secara chart, kejadian teknikal apa yang sedang terjadi?"

Signal **BUKAN keputusan beli**.

### Contoh Signal (Pattern)
- Base / Sideways
- Early Uptrend
- Accumulation
- Breakout
- Strong Breakout
- Pullback Healthy
- Distribution
- Climax / Euphoria
- False Breakout

Signal disimpan sebagai:
- `signal_code` (angka)
- ditampilkan ke user lewat mapping label

---

## 5. Volume Label (Volume Label Code)

### Apa itu Volume Label?
Volume label menjawab:
> "Tenaga pasar hari ini kuat atau lemah?"

Volume **bukan arah**, tapi **kekuatan**.

### Contoh Volume Label
- Dormant (mati)
- Ultra Dry
- Quiet
- Normal
- Early Interest
- Volume Burst / Accumulation
- Strong Burst / Breakout
- Climax / Euphoria

> Volume besar ≠ aman  
> Volume kecil ≠ jelek

Volume hanya memberi konteks.

---

## 6. Decision (Decision Code)

Decision adalah **kesimpulan EOD** berbasis rule sistem.

Decision menjawab:
> "Dengan rule watchlist, ini layak dipertimbangkan atau tidak?"

Contoh Decision:
- False Breakout / Batal
- Hindari
- Hati-hati
- Perlu Konfirmasi
- Layak Beli

Decision ini **bukan eksekusi**, hanya rekomendasi EOD.

---

## 7. Signal Age (Umur Sinyal)

### Kenapa Signal Age Penting?
Sinyal yang sama bisa bertahan beberapa hari.

Contoh masalah:
- Breakout terjadi 3 hari lalu
- Harga sudah naik jauh
- Tapi sistem masih menandai "Layak Beli"

Signal age mencegah entry yang telat.

---

### 7.1 signal_first_seen_date

Tanggal **pertama kali sinyal muncul**.

Digunakan untuk:
- audit
- debugging
- referensi historis

---

### 7.2 signal_age_days

Umur sinyal dalam hitungan hari berturut-turut.

Aturan sederhana:

- Hari pertama sinyal muncul → age = 0
- Hari kedua sinyal sama → age = 1
- Hari ketiga sinyal sama → age = 2

Jika sinyal berubah:
- age di-reset ke 0

---

## 8. Contoh Perhitungan Signal Age

### Kasus 1 — Sinyal Konsisten
| Tanggal | Signal | Age |
|------|--------|-----|
| 7 Jan | Breakout | 0 |
| 8 Jan | Breakout | 1 |
| 9 Jan | Breakout | 2 |

### Kasus 2 — Sinyal Berubah
| Tanggal | Signal | Age |
|------|--------|-----|
| 7 Jan | Breakout | 0 |
| 8 Jan | Breakout | 1 |
| 9 Jan | Pullback | 0 |

---

## 9. Hubungan Compute EOD dengan Watchlist

- Watchlist **tidak menghitung indikator**
- Watchlist **hanya membaca hasil Compute EOD**
- Expiry filter memakai `signal_age_days`
- Trade plan memakai ATR & struktur harga

Compute EOD = **fondasi data**  
Watchlist = **alat seleksi**

---

## 10. Kesimpulan Penting (Ringkas)

- Compute EOD bukan trading
- Signal ≠ Decision
- Volume ≠ Arah
- Signal age mencegah entry telat
- Expiry hanya valid jika signal age benar

Jika Compute EOD salah → semua di atasnya ikut salah.

---

Dokumen ini adalah referensi tetap.
Jika kamu ragu arti suatu istilah di code, kembali ke dokumen ini.
