# DTO — Pedoman Data Antar Layer (TradeAxis)

Dokumen ini menetapkan standar **Data Transfer Object (DTO)** sebagai kontrak data lintas layer.
Tujuan utamanya: struktur data jelas, perubahan terkontrol, dan alur data dapat ditelusuri tanpa ketergantungan pada model database atau array bebas.

---

## 1) Definisi DTO
DTO adalah objek sederhana untuk membawa data dari satu layer ke layer lain.

DTO bukan tempat:
- logika bisnis,
- query database,
- efek samping (I/O, logging, cache, network).

Isi DTO:
- data (nilai),
- struktur (nama field dan tipe/format yang disepakati),
- validasi bentuk data untuk menjaga konsistensi (format dan tipe), tanpa keputusan bisnis.

Catatan pembeda:
- Validasi bentuk data menjaga “bentuk”, bukan “kelayakan domain”.
- Kelayakan trading seperti risk/reward, score, eligibility, atau rekomendasi adalah aturan bisnis dan tidak berada di DTO.

---

## 2) Mengapa DTO digunakan
Tanpa kontrak data yang jelas, data mudah berubah menjadi array bebas yang sulit dipelihara: field tertukar, perubahan menyebar, dan sumber perubahan sulit dilacak.
DTO membatasi perubahan dengan menegaskan bentuk input/output dan memisahkan “data” dari “aturan”.

---

## 3) DTO vs Model vs Array

### 3.1 DTO
- Kontrak data lintas layer.
- Ringan, tanpa query/relasi, tanpa keputusan bisnis.
- Cocok sebagai input/output proses domain (guard, classifier, planner, portfolio).

### 3.2 Model (Eloquent)
- Representasi tabel database.
- Membawa relasi dan kemampuan query.
- Berisiko menimbulkan side-effect (lazy loading, akses DB tersembunyi) dan tidak ideal sebagai format data inti untuk proses deterministik.

### 3.3 Array
- Fleksibel, tetapi kontrak tidak terlihat.
- Rawan typo key, missing field, dan perubahan diam-diam.

---

## 4) Hubungan DTO dengan SRP
SRP berarti satu alasan untuk berubah.

- DTO berubah ketika struktur data berubah.
- Service/Domain berubah ketika aturan bisnis berubah.

---

## 5) Penempatan DTO
DTO bersifat lintas layer dan bukan artefak privat milik satu service/modul.

Ketentuan:
- DTO ditempatkan pada area lintas modul (shared/domain contract).
- Area shared adalah area yang secara eksplisit disepakati sebagai tempat kontrak lintas modul dan tidak memuat implementasi spesifik modul.
- DTO tidak diletakkan “terkubur” di area privat yang memicu ketergantungan silang atau circular dependency.

---

## 6) Kontrak Input/Output Lintas Layer
Setiap langkah proses menerima dan mengembalikan DTO/objek hasil yang jelas.

Contoh pola (konseptual):
- Repository → DTO
- Guard(DTO) → GuardResult
- Classifier(DTO) → SignalDecision
- Planner(DTO, SignalDecision) → TradePlan

Prinsip:
- bentuk input dan output jelas,
- tiap layer tidak bergantung pada detail internal layer lain.

---

## 7) Aturan Wajib DTO

### 7.1 Larangan
- Tidak menjalankan query database.
- Tidak memuat logika bisnis.
- Tidak melakukan efek samping.

### 7.2 Boleh
- Field dan struktur data.
- Normalisasi mekanis tanpa keputusan bisnis (trimming, casting, memastikan format).
- Validasi bentuk data (schema-level), bukan validasi aturan trading.

### 7.3 Immutability
Untuk menjaga determinisme dan mempermudah penelusuran perubahan:
- DTO tidak diubah setelah dibuat.
- Perubahan data dilakukan dengan membuat DTO baru (atau metode copy/with-style).

---

## 8) Standar Penamaan dan Konsistensi Field
- Nama field merepresentasikan makna domain, bukan cara hitung.
- Field yang bermakna sama harus konsisten di seluruh modul.
- Gunakan satu gaya penamaan dan konsisten.

---

## 9) Kapan DTO dibuat atau diubah
DTO dibuat ketika data menjadi kontrak lintas layer dan perlu bentuk yang stabil.
DTO diubah ketika struktur atau format data berubah secara resmi.

Setiap perubahan DTO diikuti:
- penyesuaian titik produksi (producer) dan konsumsi (consumer),
- penyesuaian test/fixtures yang bergantung pada struktur DTO.

---

## 10) Indikator implementasi yang menyimpang
- DTO berisi keputusan (label/status/rekomendasi) yang seharusnya ditentukan Domain Compute.
- DTO memanggil repository/model/query.
- “DTO” dipakai sebagai tempat utilitas yang tidak terkait kontrak data.
- Data lintas layer masih dominan berupa array bebas tanpa kontrak yang terlihat.
