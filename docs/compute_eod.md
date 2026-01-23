# Compute EOD — Explained (Untuk Pemula & Developer)

## 1. Apa itu Compute EOD?

**Compute EOD (End Of Day)** adalah proses yang dijalankan **setelah bursa tutup** untuk:

1. Membaca data harga harian (OHLC & volume)
2. Menghitung indikator teknikal
3. Menentukan **jenis sinyal** dan **kondisi volume**
4. Menyimpan hasilnya ke database

### Kontrak Input (Wajib)
- Compute EOD membaca OHLC dari **CANONICAL output Market Data** (lihat MARKET_DATA.md bagian RAW vs CANONICAL), bukan dari RAW.
- Compute EOD hanya memproses tanggal yang dianggap **trading day** (berdasarkan market calendar).
- `trade_date` yang dihitung harus mengikuti **effective date** Market Data (aturan cutoff), bukan "today" sebelum cutoff.


### Kontrak Rolling Window (Trading Days, bukan kalender)
- Semua indikator berbasis window (MA/RSI/ATR/vol_sma20/vol_ratio) dihitung dari **N trading days yang valid**, bukan “mundur 20 tanggal kalender”.
- Jika window tidak lengkap (kurang data canonical trading day), maka:
  - indikator terkait boleh **NULL**
  - **jangan** keluarkan decision yang mengesankan “Layak/Strong”
  - wajib tulis warning ke log domain `compute_eod` (biar ketahuan kualitas data)

### Corporate Actions (split) — aturan supaya indikator tidak palsu
- Jika terdeteksi/ada event split (atau discontinuity suspected dari Market Data):
  - canonical harus dibenahi/adjust dahulu (rebuild canonical)
  - lalu recompute EOD untuk range terdampak
- Compute EOD tidak boleh “menghaluskan” split diam-diam; lebih baik flag + stop rekomendasi.



Compute EOD **TIDAK**:
- melakukan beli / jual
- menentukan entry intraday
- mengeksekusi trading

Compute EOD menghasilkan indikator (fakta), sinyal/volume (interpretasi), dan decision (rekomendasi EOD berbasis rule). decision bukan eksekusi order

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

Compute EOD menghasilkan data di tabel:

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

### 3.2 Kontrak Output (tabel `ticker_indicators_daily`) — dipakai Watchlist & Portfolio

Compute EOD menulis **1 baris per (ticker_id, trade_date)** ke tabel `ticker_indicators_daily` (unik `uq_ind_daily_ticker_date`).

Kolom yang paling penting (ringkas):

**Snapshot EOD (untuk audit/debug)**
- `open, high, low, close` = harga real close hari itu (dari canonical).
- `basis_used` = `close | adj_close` (basis yang dipakai untuk indikator).
- `price_used` = harga yang dipakai untuk MA/RSI (hasil basis policy).
- `volume` = volume canonical (unit internal).

**Indikator**
- `ma20, ma50, ma200` (rolling SMA, basis: `price_used`)
- `rsi14` (Wilder RSI 14, basis: `price_used`)
- `atr14` (Wilder ATR 14, basis: **real** high/low/close)
- `support_20d`, `resistance_20d` (rolling 20D, **exclude today**)
- `vol_sma20`, `vol_ratio` (exclude today)

**Klasifikasi**
- `signal_code`, `volume_label_code`, `decision_code`
- `signal_first_seen_date`, `signal_age_days` (streak / umur sinyal)

Catatan:
- Skor (`score_*`) boleh diisi belakangan (tidak wajib untuk kontrak ComputeEOD v1.0), tapi kolomnya sudah tersedia.

---

### 3.3 Price Basis Policy (konsisten lintas domain)

Tujuan: indikator tidak “palsu” saat ada corporate action (split/discontinuity) dan tetap audit-able.

Aturan yang dipakai aplikasi (`PriceBasisPolicy`):
- Untuk indikator (MA/RSI):  
  - kalau `adj_close` tersedia dan > 0 → pakai `adj_close` (`basis_used = adj_close`, `price_used = adj_close`)
  - kalau tidak → pakai `close` (`basis_used = close`, `price_used = close`)
- Untuk trading/portfolio valuation harian: **selalu pakai `close` real** (bukan `adj_close`), kecuali kamu memang menerapkan *fully-adjusted valuation* (tidak disarankan sebelum CA pipeline matang).

---

### 3.4 Definisi Perhitungan (yang harus sama persis supaya hasil tidak mismatch)

#### A) Rolling window = trading days
- Lookback untuk ambil data canonical harus berbasis **trading days** (market calendar), bukan kalender.
- Konfigurasi yang mengendalikan ini:
  - `TRADE_LOOKBACK_DAYS` (default 260)
  - `TRADE_EOD_WARMUP_EXTRA_TRADING_DAYS` (default 60)

#### B) SMA (MA20/50/200)
- SMA N dihitung dari **N trading bars terakhir** dari `price_used`.
- Rounding output: `round(., 4)`.

#### C) RSI Wilder 14
- RSI memakai metode **Wilder smoothing** (bukan simple average).
- Input harga: `price_used`.
- Rounding output: `round(., 2)` (sesuai tipe kolom DB `decimal(6,2)`).

#### D) ATR Wilder 14
- ATR memakai metode **Wilder**.
- Input: **real** `high, low, close` (bukan `adj_close`).
- Rounding output: `round(., 4)`.

#### E) Support/Resistance 20D (exclude today)
- `support_20d` = **min(low)** dari **20 trading days sebelum trade_date**.
- `resistance_20d` = **max(high)** dari **20 trading days sebelum trade_date**.
- “Exclude today” itu wajib supaya levelnya tidak bias oleh candle hari ini.

#### F) Volume SMA20 & Volume Ratio (exclude today)
- `vol_sma20` = rata-rata volume dari **20 trading days sebelum trade_date**.
- `vol_ratio` = `today_volume / vol_sma20` (dibulatkan `round(., 4)`).
- Jika `vol_sma20` belum tersedia → `vol_ratio = NULL`.

---

### 3.5 Guardrails kualitas data (wajib agar output tidak menipu)

#### A) Invalid bar pada trade_date → skip / flag
Jika canonical bar untuk `trade_date` invalid (harga null/<=0, high<low, volume null/negatif), bar **tidak boleh ikut rolling**.

#### B) Insufficient window → indikator NULL + keputusan dipaksa aman
Jika ada indikator penting yang masih NULL karena history kurang:
- emit warning ke log domain `compute_eod`
- dan keputusan **tidak boleh terlihat “Layak/Perlu Konfirmasi”**  
  Implementasi memaksa:
  - jika `decision_code >= 4` → set jadi `decision_code = 2 (Hindari)`

#### C) Corporate action hint/event pada trade_date → STOP rekomendasi
Jika canonical menyertakan `ca_event` atau `ca_hint` pada `trade_date`:
- output bar netral: indikator NULL
- `decision_code = 2 (Hindari)`
- action yang benar: **rebuild canonical range** dulu, lalu rerun compute-eod untuk range itu.

---

### 3.6 Mapping Code (supaya UI & downstream tidak salah tafsir)

#### Signal Code (`signal_code`)
0 Unknown  
1 Base / Sideways  
2 Early Uptrend  
3 Accumulation  
4 Breakout  
5 Strong Breakout  
6 Breakout Retest  
7 Pullback Healthy  
8 Distribution  
9 Climax / Euphoria  
10 False Breakout  

#### Volume Label Code (`volume_label_code`)
1 Dormant  
2 Ultra Dry  
3 Quiet  
4 Normal  
5 Early Interest  
6 Volume Burst / Accumulation  
7 Strong Burst / Breakout  
8 Climax / Euphoria  

#### Decision Code (`decision_code`)
1 False Breakout / Batal  
2 Hindari  
3 Hati-hati  
4 Perlu Konfirmasi  
5 Layak Beli  

> Kontrak penting: Decision sudah mengandung override anti-kontradiksi (mis. false breakout, climax).

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

Signal Retest vs age: 
Signal age saat ini dihitung dari signal_code (pattern).
Kalau suatu hari ingin “Breakout Retest” benar-benar butuh state “pernah breakout sebelumnya”, kamu perlu jelaskan bahwa:
- Retest idealnya berbasis state/history, bukan cuma heuristik 1 hari.

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

definisi vol_ratio dan “prev 20 hari”
- vol_ratio = volume_today / avg_volume_prev_20 (exclude today)
- vol_sma20 dihitung dari 20 hari sebelumnya (tidak termasuk hari ini)
- vol_ratio = volume / vol_sma20
- volume_label mapping berdasarkan threshold ratio

---

## 6. Decision (Decision Code)

Decision adalah **kesimpulan EOD** berbasis rule sistem. Decision sudah memasukkan konteks Signal + Volume (override) supaya tidak kontradiksi (false breakout / climax / distribution).

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
