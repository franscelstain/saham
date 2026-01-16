# MARKET_DATA_FAILURE_PLAYBOOK.md — SOP Saat Market Data Bermasalah
*(Tujuan: fitur tidak rusak diam-diam, cepat terdeteksi, cepat pulih, dan tetap audit-able)*

Dokumen ini adalah **playbook operasional**. Saat Market Data bermasalah, ini jadi panduan:
- cara mendeteksi,
- cara mengklasifikasi severity,
- langkah investigasi (urutannya),
- tindakan pemulihan (tanpa merusak data),
- dan keputusan kapan harus “tahan canonical” vs lanjut.

Playbook ini sengaja tidak mengunci implementasi teknis, tapi mendefinisikan **aksi yang harus terjadi** agar sistem stabil.

---

## 0) Prinsip Operasional (Jangan Dilanggar)

1) **Lebih baik tidak update canonical daripada update salah.**  
   RAW boleh masuk dengan flag, canonical jangan kalau masih ragu.

2) **Setiap fallback, missing, disagreement, dan reject quality gate harus terlihat di ringkasan run.**  
   Tanpa ringkasan yang bisa dibaca manusia, masalah akan terpendam.

3) **Semua keputusan yang mengubah canonical harus punya jejak.**  
   “Kenapa canonical berubah?” harus bisa dijawab.

4) **Cutoff itu aturan keras.**  
   Jangan pernah memasukkan candle “hari ini” sebelum cutoff.

---

## 1) Klasifikasi Severity (Agar Keputusan Tidak Random)

### S0 — Informational
Contoh:
- 1–2 ticker gagal fetch karena simbol tidak dikenal.
- Disagreement kecil yang tidak melewati threshold.
Aksi:
- catat, masuk backlog untuk perbaikan, tidak perlu tindakan cepat.

### S1 — Minor
Contoh:
- fallback terjadi tapi coverage tetap tinggi.
- missing data pada 1–3 ticker di trading day.
Aksi:
- lakukan retry terarah (subset), validasi hasil, pastikan canonical aman.

### S2 — Major
Contoh:
- provider utama down / rate-limited berat.
- missing data pada banyak ticker trading day.
- disagreement major melonjak.
Aksi:
- aktifkan fallback massal (AUTO), atau tahan canonical dan jadwalkan rerun setelah stabil.

### S3 — Critical
Contoh:
- canonical terkontaminasi partial day / timezone shift.
- banyak bar invalid lolos ke canonical (quality gate bocor).
- mapping symbol salah menyebabkan ticker tertukar.
Aksi:
- **freeze canonical** (hentikan update),
- lakukan rollback/rebuild canonical dari RAW yang benar,
- audit dampak ke compute-eod/watchlist/portfolio.

---

## 2) Sinyal Deteksi (Yang Harus Selalu Dicek Setelah Run)

Setelah setiap run, cek minimal ini:

1) **Effective date range**  
   Pastikan end date sesuai cutoff.

2) **Coverage**  
   - berapa % ticker punya bar untuk trading day terbaru?
   - berapa ticker missing?

3) **Fallback rate**  
   - berapa % ticker pakai provider utama vs fallback?
   - alasan fallback dominan apa?

4) **Invalid/reject count**  
   - berapa bar gagal hard quality gate?
   - apakah lonjakan abnormal?

5) **Disagreement major count**  
   - berapa bar berbeda signifikan antar sumber?

Jika salah satu metrik melonjak, anggap run bermasalah meski “secara teknis sukses”.

---

## 3) Prosedur Investigasi Cepat (Urutan Wajib)

Urutan ini mencegah kamu buang waktu di tempat yang salah.

### Step 1 — Validasi aturan waktu (cutoff & timezone)
Pertanyaan:
- Run dilakukan jam berapa WIB?
- End date yang dipakai apa?
- Ada indikasi bar “hari ini” masuk sebelum cutoff?

Jika iya → langsung klasifikasi minimal **S3** (karena ini merusak indikator).

### Step 2 — Bedakan “non-trading day” vs “missing trading day”
Pertanyaan:
- Tanggal bermasalah itu trading day?
- Kalau bukan trading day, tidak perlu panik.

Jika trading day dan banyak missing → lanjut Step 3.

### Step 3 — Apakah provider down / rate limit?
Indikator:
- error HTTP melonjak,
- timeout melonjak,
- response kosong massal,
- fallback rate melonjak.

Jika iya → lanjut prosedur Provider Down.

### Step 4 — Apakah ada masalah normalisasi (symbol, volume unit, precision)?
Indikator:
- harga/volume tidak masuk akal untuk banyak ticker,
- ticker tertentu tiba-tiba punya range harga ekstrem (heuristic),
- banyak reject karena open/close di luar high/low.

Jika iya → prosedur Normalisasi.

### Step 5 — Apakah kualitas data vendor bermasalah (stale/outlier)?
Indikator:
- series tidak bergerak (stale),
- outlier spike pada subset ticker,
- disagreement major naik tapi hanya pada 1 sumber.

Jika iya → prosedur Data Quality Vendor.

---

## 4) Playbook Kasus Utama

### Case A — Provider Down / Rate Limited (S2)
Gejala:
- provider utama gagal massal
- fallback melonjak atau semua gagal

Tindakan:
1) Pastikan cutoff benar (jangan sampai panik lalu memasukkan partial).
2) Aktifkan mode fallback AUTO dengan priority list.
3) Jalankan retry dengan throttling lebih konservatif.
4) Jika fallback tersedia dan lolos quality gate:
   - canonical boleh dibangun dari fallback,
   - tapi flag “FALLBACK_USED” di canonical.
5) Jika fallback juga tidak stabil:
   - simpan RAW yang ada,
   - **tahan canonical update** untuk tanggal itu,
   - rerun nanti.

Keputusan cepat:
- Jika coverage canonical < threshold (misal < 95% untuk trading day) → tahan canonical.

---

### Case B — Partial Day Masuk Canonical (S3 Critical)
Gejala:
- ada bar untuk “today” padahal run sebelum cutoff
- indikator berubah drastis antara siang dan malam

Tindakan:
1) Freeze canonical update untuk tanggal “today”.
2) Hapus/rollback canonical untuk tanggal tersebut (sesuai mekanisme yang aman).
3) Pastikan RAW tetap ada untuk audit.
4) Perbaiki guardrail:
   - canonical builder harus reject bar “today” sebelum cutoff (hard rule).
5) Rebuild canonical hanya setelah cutoff atau ke “yesterday”.

Catatan:
- Ini harus dianggap critical karena merusak compute-eod dan watchlist.

---

### Case C — Timezone Shift (Tanggal Geser) (S3 Critical)
Gejala:
- gap/hole tanggal
- bar duplikat atau “double day”
- trade_date tidak sinkron dengan kalender trading day

Tindakan:
1) Freeze canonical.
2) Ambil sampel beberapa ticker dan cek:
   - timestamp mentah vs trade_date hasil normalisasi.
3) Perbaiki normalisasi timezone:
   - trade_date harus definisi WIB.
4) Rebuild canonical dari RAW yang sudah dinormalisasi ulang.
5) Verifikasi dengan:
   - jumlah trading days dalam range,
   - keselarasan dengan market calendar.

---

### Case D — Symbol Mapping Salah / Ticker Tertukar (S3 Critical)
Gejala:
- beberapa ticker tiba-tiba punya harga yang mustahil (BBCA jadi 200, dst)
- disagreement major masif tapi “aneh”
- pola data dua ticker seperti tertukar

Tindakan:
1) Freeze canonical.
2) Identifikasi subset ticker yang terdampak.
3) Audit RAW:
   - sumber symbol apa yang dipakai provider?
4) Perbaiki mapping tunggal:
   - jangan ada mapping tersebar.
5) Rebuild canonical untuk ticker terdampak pada range yang salah.
6) Jalankan sanity check:
   - range harga wajar per ticker,
   - continuity check.

---

### Case E — Volume Unit Salah (lot vs shares) (S2 Major → bisa jadi S3 kalau masuk canonical lama)
Gejala:
- volume tiba-tiba 100x lebih besar/kecil
- sinyal volume burst jadi banjir palsu

Tindakan:
1) Pastikan definisi unit volume internal.
2) Audit provider mana yang beda unit.
3) Perbaiki konversi di normalizer provider tersebut.
4) Rebuild canonical untuk range terdampak.
5) Jalankan ulang compute-eod untuk range terdampak (kalau indikator bergantung volume).

---

### Case F — Outlier / Glitch Harga Ekstrem (S2 Major)
Gejala:
- harga spike gila pada subset ticker
- banyak reject atau banyak sinyal teknikal abnormal

Tindakan:
1) Cek apakah ada corporate action hint (split, reverse, dll).
2) Jika tidak ada CA hint:
   - bar tersebut harus ditahan dari canonical (hard/soft rule sesuai threshold).
3) Simpan RAW + flag “OUTLIER”.
4) Jika ada sumber lain untuk tanggal itu:
   - canonical ambil sumber lain (priority + quality gate).
5) Jika semua sumber outlier:
   - tahan canonical untuk ticker itu saja,
   - masuk daftar investigasi/manual review.

---

### Case G — Data Stale (S2 Major)
Gejala:
- candle terbaru tidak muncul padahal trading day
- series tidak berubah beberapa hari
- provider mengulang data lama

Tindakan:
1) Verifikasi trading day.
2) Jalankan lookback import (5–10 trading days) untuk ticker terdampak.
3) Bandingkan dengan sumber lain:
   - jika sumber lain punya data baru → provider stale, turunkan prioritas sementara.
4) Canonical:
   - jangan pakai bar yang stale sebagai “hari terbaru” jika jelas salah.
5) Masukkan flag “STALE_PROVIDER” untuk sumber tersebut.

---

### Case H — Banyak Missing Data pada Trading Day (S2 Major)
Gejala:
- coverage turun signifikan
- missing tersebar luas

Tindakan:
1) Cek kalender + cutoff.
2) Cek provider down/rate limit.
3) Jalankan retry batch:
   - subset missing dulu (hemat waktu).
4) Jika masih missing:
   - fallback mode,
   - jika fallback sukses → build canonical,
   - jika tidak → tahan canonical dan rerun setelah stabil.

---

## 5) Keputusan “Tahan Canonical” (Gating Rules)

Canonical update untuk trading day terbaru harus **ditahan** jika salah satu kondisi:
- run dilakukan sebelum cutoff tapi mencoba memasukkan today
- coverage < threshold (misal 95% ticker untuk trading day)
- invalid hard rejects melonjak di atas threshold
- timezone shift terdeteksi
- mapping error terindikasi
- disagreement major melonjak drastis (indikasi data tidak stabil)

Saat canonical ditahan:
- RAW tetap disimpan,
- run summary harus menandai status: `CANONICAL_HELD`.

---

## 6) Pemulihan: Rebuild, Backfill, dan Dampak ke Modul Lain

### 6.1 Kapan perlu rebuild canonical?
- aturan priority berubah,
- provider baru masuk,
- bug normalisasi/quality gate diperbaiki,
- ada insiden S3.

### 6.2 Kapan perlu rerun compute-eod?
Jika canonical berubah pada periode tertentu dan indikator dihitung dari harga/volume:
- rerun compute-eod untuk range tersebut agar sinyal konsisten.

### 6.3 Backfill strategy yang aman
- Jangan backfill masif saat jam sibuk.
- Backfill dilakukan bertahap (per ticker/per range) dan selalu melalui RAW → canonical.
- Hasil backfill harus melewati quality gate yang sama.

---

## 7) Postmortem Minimal (Supaya Masalah Tidak Ulang)

Setelah insiden S2/S3, catat:
1) Root cause: provider, normalisasi, cutoff, mapping, dll.
2) Dampak: tanggal & ticker mana, modul mana terpengaruh.
3) Fix: guardrail apa yang ditambahkan.
4) Prevent: metrik/alert apa yang ditambah agar terdeteksi lebih cepat.

---

## 8) Checklist Harian Operator (Ringkas tapi Wajib)

Setiap hari setelah run:
- [ ] effective_end_date benar (cutoff OK)
- [ ] coverage trading day terbaru di atas threshold
- [ ] fallback rate normal
- [ ] invalid rejects normal
- [ ] disagreement major normal
- [ ] missing trading day tidak melonjak
- [ ] canonical status: updated / held (jelas)

---

## 9) Prinsip Penutup
Market Data yang “tahan produksi” bukan yang tidak pernah gagal, tapi yang:
- gagal dengan cara yang **terlihat**,
- bisa **pulih cepat**,
- dan tidak mencemari canonical diam-diam.

---
