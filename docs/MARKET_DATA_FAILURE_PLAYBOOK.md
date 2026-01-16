# MARKET_DATA_FAILURE_PLAYBOOK.md — SOP Saat Market Data Bermasalah
*(Tujuan: cepat terdeteksi, cepat pulih, tidak mencemari canonical, dan stabil di production)*

Dokumen ini adalah playbook operasional. Saat Market Data bermasalah, ini jadi panduan:
- deteksi,
- klasifikasi severity,
- investigasi (urutannya),
- pemulihan yang aman,
- keputusan kapan harus “tahan canonical”.

**Catatan agar tidak salah kaprah:**
- Penyebutan compute-eod/watchlist/portfolio hanya untuk menjelaskan **dampak downstream**.
- Itu **bukan** larangan reuse.
- Kalau ada logic/config yang sama dan dipakai bareng, fix-nya harus di **shared/public component** supaya semua modul konsisten.

---

## 0) Prinsip Operasional (jangan dilanggar)

Prinsip global tentang SRP, shared/public, config policy, dan logging mengikuti `SRP_Performa.md`.
Cutoff dan aturan run-tanpa-tanggal mengikuti `MARKET_DATA.md` (Bagian 3).

1) **Lebih baik tidak update canonical daripada update salah.**
2) **Setiap fallback/missing/disagreement/reject harus terlihat di health summary run.**
3) **Semua keputusan yang mengubah canonical harus punya jejak alasan (audit-able).**
4) **Cutoff adalah aturan keras** → jangan override ad-hoc saat incident.
5) Jika root-cause ada di aturan umum (cutoff/calendar/normalisasi/validator/telemetry), perbaiki di **shared/public** (jangan patch lokal di satu modul).

---

## 1) Klasifikasi Severity

### S0 — Informational
- 1–2 ticker gagal fetch (symbol tidak dikenal)
- disagreement kecil
Aksi: catat, backlog.

### S1 — Minor
- fallback terjadi tapi coverage tetap tinggi
- missing 1–3 ticker pada trading day
Aksi: retry terarah, verifikasi, canonical aman.

### S2 — Major
- provider utama down/rate-limited berat
- missing banyak ticker trading day
- disagreement major melonjak
Aksi: fallback massal / tahan canonical / rerun saat stabil.

### S3 — Critical
- canonical tercemar partial day
- timezone shift (tanggal geser)
- quality gate bocor (invalid masuk canonical)
- symbol mapping salah (ticker tertukar)
Aksi: freeze canonical, rollback/rebuild, audit dampak.

---

## 2) Sinyal Deteksi (wajib dicek setelah setiap run)

Cek minimal:
1) **Effective date range** (cutoff benar?)
2) **Coverage** (% ticker punya bar di trading day terbaru)
3) **Fallback rate** + alasan dominan
4) **Hard rejects** (lonjakan abnormal?)
5) **Disagreement major** (lonjakan abnormal?)
6) **Missing trading day** (bukan sekadar “tidak ada data”)

Kalau salah satu melonjak, anggap run bermasalah meski “exit code sukses”.

---

## 3) Prosedur Investigasi Cepat (urutan wajib)

### Step 1 — Validasi cutoff & timezone (shared rules)
Pertanyaan:
- Run jam berapa WIB?
- End date sesuai cutoff?
- Ada bar “today” masuk sebelum cutoff?
- Ada indikasi tanggal geser karena UTC→WIB?

Jika ya → S3.

**Catatan:** jika root cause di cutoff resolver / timezone normalizer, fix harus di shared/public.

### Step 2 — Kalender: trading day atau bukan?
- non-trading day → banyak “missing” bisa normal
- trading day → missing adalah masalah

### Step 3 — Provider: down / rate limit?
Indikator:
- timeout/error melonjak
- response kosong massal
- fallback melonjak

### Step 4 — Normalisasi: symbol/unit/precision?
Indikator:
- harga/volume mustahil massal
- banyak reject open/close di luar range
- pola data terlihat tertukar

### Step 5 — Data quality: stale/outlier/disagreement?
Indikator:
- series tidak bergerak (stale)
- spike ekstrem subset ticker
- disagreement major terkonsentrasi di satu sumber

---

## 4) Playbook Kasus Utama

### Case A — Provider Down / Rate Limited (S2)
Tindakan:
1) pastikan cutoff benar (jangan memasukkan partial karena panik)
2) fallback AUTO sesuai priority
3) retry subset missing dengan throttling konservatif
4) canonical:
   - boleh dibangun dari fallback jika lolos quality gate
   - wajib flag `FALLBACK_USED`
5) jika coverage < threshold → tahan canonical (`CANONICAL_HELD`) dan rerun setelah stabil

---

### Case B — Partial Day Masuk Canonical (S3)
Gejala:
- bar “today” masuk sebelum cutoff
- indikator berubah drastis setelah rerun

Tindakan:
1) freeze canonical update untuk tanggal itu
2) rollback/hapus canonical yang tercemar (mekanisme aman)
3) RAW tetap disimpan (audit)
4) perbaiki guardrail: canonical builder reject “today” sebelum cutoff
5) rebuild canonical setelah cutoff atau pakai yesterday

---

### Case C — Timezone Shift (S3)
Gejala:
- gap/double day
- trade_date tidak align dengan kalender

Tindakan:
1) freeze canonical
2) audit sample ticker: timestamp mentah vs trade_date hasil normalisasi
3) perbaiki normalisasi timezone (trade_date=WIB)
4) rebuild canonical untuk range terdampak
5) verifikasi terhadap market calendar (continuity, jumlah trading day)

---

### Case D — Symbol Mapping Salah / Ticker Tertukar (S3)
Gejala:
- ticker tertentu tiba-tiba punya range harga mustahil
- data dua ticker terlihat tertukar

Tindakan:
1) freeze canonical
2) identifikasi ticker terdampak
3) audit RAW: symbol eksternal apa yang dipakai
4) perbaiki mapping tunggal (shared/public)
5) rebuild canonical untuk ticker/range terdampak
6) sanity check: range harga wajar + continuity

---

### Case E — Volume Unit Salah (S2 → bisa S3 jika lama tercemar)
Gejala:
- volume loncat 100x / turun 100x
- sinyal volume burst banjir palsu

Tindakan:
1) pastikan definisi unit internal (shared rule)
2) audit provider yang beda unit
3) perbaiki normalizer provider
4) rebuild canonical range terdampak
5) jadwalkan rerun compute-eod untuk range itu jika indikator memakai volume

---

### Case F — Outlier / Glitch Harga Ekstrem (S2)
Tindakan:
1) cek kemungkinan corporate action hint
2) jika tidak ada CA:
   - tahan canonical bar outlier (reject/flag sesuai threshold)
3) simpan RAW + flag `OUTLIER`
4) jika sumber lain tersedia:
   - canonical ambil sumber lain (priority + quality gate)
5) jika semua sumber outlier:
   - tahan canonical untuk ticker itu
   - masuk daftar investigasi/manual review

---

### Case G — Data Stale (S2)
Gejala:
- trading day tapi bar terbaru tidak muncul
- vendor mengulang data lama

Tindakan:
1) verifikasi trading day
2) run lookback untuk ticker terdampak
3) bandingkan dengan sumber lain
4) turunkan prioritas provider stale sementara
5) canonical jangan pakai stale sebagai “latest”
6) flag `STALE_PROVIDER`

---

### Case H — Missing Massal di Trading Day (S2)
Tindakan:
1) cek cutoff + kalender
2) cek provider down/rate limit
3) retry subset missing
4) fallback massal jika perlu
5) jika coverage < threshold → tahan canonical

---

## 5) Gating Rules: kapan canonical harus ditahan

Tahan canonical jika:
- “today” masuk sebelum cutoff
- coverage trading day terbaru < threshold (misal 95%)
- hard rejects melonjak di atas threshold
- timezone shift terdeteksi
- mapping error terindikasi
- disagreement major melonjak drastis

Saat canonical ditahan:
- RAW tetap disimpan + flags
- health summary wajib mencantumkan status `CANONICAL_HELD` + alasan dominan

---

## 6) Pemulihan: rebuild, backfill, dan dampak downstream

### 6.1 Kapan rebuild canonical?
- priority rules berubah
- provider baru masuk
- bug normalisasi/validator/cutoff diperbaiki
- insiden S3

### 6.2 Kapan rerun compute-eod?
Jika canonical berubah pada periode tertentu dan indikator bergantung pada harga/volume:
- rerun compute-eod untuk range terdampak agar konsisten

### 6.3 Backfill aman
- backfill bertahap (per ticker/per range)
- selalu lewat RAW → canonical
- quality gate yang sama berlaku

---

## 7) Postmortem minimal (supaya tidak ulang)

Untuk S2/S3:
1) root cause (provider/normalisasi/cutoff/mapping/kalender)
2) dampak (tanggal + ticker + downstream)
3) fix (guardrail apa ditambah)
4) prevent (metrik/threshold apa ditambah)

---

## 8) Checklist harian operator

- [ ] effective_end_date benar (cutoff OK)
- [ ] coverage trading day terbaru >= threshold
- [ ] fallback rate normal
- [ ] hard rejects normal
- [ ] disagreement major normal
- [ ] missing trading day tidak melonjak
- [ ] status canonical: updated / held (jelas + alasan)
- [ ] bila bug ada di aturan umum → fix di shared/public, bukan patch lokal

---
