# Template Review Per Jenis Perubahan

Dokumen ini berisi template ringkas review untuk jenis perubahan yang umum pada API dan backend. Template ini dipakai agar review lebih konsisten dan concern yang penting tidak terlewat.

## Cara pakai

- Pilih template yang sesuai dengan jenis perubahan.
- Isi bagian yang relevan.
- Bila perubahan menyentuh lebih dari satu kategori, gabungkan template yang diperlukan.
- Untuk detail aturan, kembali ke dokumen concern yang relevan.

---

## Template 1: Endpoint atau route baru

### Ringkasan perubahan

### Boundary
- Apakah request mentah berhenti di transport boundary?
- Apakah input dipetakan ke DTO atau kontrak eksplisit?
- Apakah controller atau handler bebas dari query langsung?
- Apakah controller atau handler bebas dari rule domain utama?

### Service
- Apakah use case punya application service yang jelas?
- Apakah service hanya mengorkestrasi?
- Apakah transaction boundary jelas bila ada write?

### Repository
- Apakah query tetap di persistence layer?
- Apakah return shape repository jelas?
- Apakah tidak ada lazy loading bocor?

### Domain
- Apakah keputusan bisnis utama ada di domain compute bila relevan?
- Apakah service tidak menyerap rule domain berat?

### Response dan error
- Apakah shape sukses konsisten?
- Apakah shape error konsisten?
- Apakah mapping error ke status transport jelas?

### Operasional
- Apakah logging cukup?
- Apakah idempotensi perlu dipikirkan?
- Apakah performa endpoint sudah masuk akal?

### Putusan
- Approve / Needs changes / Reject

---

## Template 2: Job, batch, atau scheduler baru

### Ringkasan perubahan

### Boundary
- Apakah ada boundary yang jelas untuk trigger job?
- Apakah parameter run eksplisit?

### Data besar
- Apakah chunking, streaming, batching, atau bulk write dipilih dengan sadar?
- Apakah full load dihindari?
- Apakah N+1 dicegah?

### Idempotensi
- Apakah run id jelas?
- Apakah retry aman?
- Apakah rerun aman?
- Apakah duplicate item dapat dideteksi?

### Partial failure
- Apakah strategi partial failure jelas?
- Apakah resume vs rerun dari awal jelas?

### Logging
- Apakah start, end, progress, partial failure, dan retry dapat ditelusuri?

### Putusan
- Approve / Needs changes / Reject

---

## Template 3: Perubahan repository atau persistence

### Ringkasan perubahan

### Query placement
- Apakah semua query tetap di persistence layer?
- Apakah service atau controller tidak ikut query?

### Contract
- Apakah input repository berubah?
- Apakah output repository berubah?
- Apakah perubahan itu terdokumentasi?

### Performance
- Apakah query baru sudah diprofilkan atau setidaknya ditinjau masuk akal?
- Apakah ada risiko N+1, full scan liar, atau full load besar?

### Boundary
- Apakah repository tetap bebas dari keputusan domain utama?
- Apakah repository tidak membentuk response atau memakai request framework?

### Operasional
- Apakah bulk write, transaction, dan error handling jelas?

### Putusan
- Approve / Needs changes / Reject

---

## Template 4: Perubahan rule domain

### Ringkasan perubahan

### Penempatan
- Apakah rule domain ditempatkan di domain compute?
- Apakah rule tidak tercecer di service, repository, atau controller?

### Input dan output
- Apakah input rule fokus dan eksplisit?
- Apakah hasil rule eksplisit?

### Determinisme
- Apakah hasil tetap deterministik terhadap input dan policy yang sah?
- Apakah ada threshold, weight, atau policy yang perlu ditelusuri versinya?

### Dampak
- Apakah perubahan rule mengubah output bisnis?
- Apakah test rule dan contract test terkait diperbarui?

### Putusan
- Approve / Needs changes / Reject

---

## Template 5: Perubahan DTO atau handoff

### Ringkasan perubahan

### Kontrak
- Apakah field ditambah, dihapus, diubah namanya, atau diubah maknanya?
- Apakah perubahan ini memengaruhi layer lain?

### Boundary
- Apakah request framework, ORM model, atau query builder tidak bocor lintas layer?
- Apakah array liar tidak dipakai sebagai kontrak utama?

### Konsistensi
- Apakah mapper tetap eksplisit?
- Apakah shape payload tetap stabil?

### Dampak
- Apakah dokumentasi dan test yang bergantung pada shape diperbarui?

### Putusan
- Approve / Needs changes / Reject

---

## Template 6: Integrasi eksternal baru

### Ringkasan perubahan

### Boundary
- Apakah integrasi punya adapter atau boundary yang jelas?
- Apakah layer lain tidak bergantung langsung pada detail provider?

### Error dan retry
- Apakah dependency failure dipetakan dengan benar?
- Apakah retry aman?
- Apakah timeout dan duplicate effect dipikirkan?

### Logging
- Apakah operation name, status, latency, dan correlation id dapat ditelusuri?
- Apakah credential dan payload sensitif tidak bocor?

### Idempotensi
- Apakah operasi write ke provider perlu idempotency key atau deduplication?

### Putusan
- Approve / Needs changes / Reject

---

## Template 7: Pengecualian arsitektur

### Ringkasan penyimpangan
- Apa aturan yang dilonggarkan?

### Alasan
- Kenapa penyimpangan ini perlu?

### Cakupan
- Apakah penyimpangannya sempit?

### Risiko
- Apa risikonya?

### Pengaman
- Apa guardrail-nya?

### Review ulang
- Kapan pengecualian ini harus ditinjau ulang?

### Putusan
- Approve / Needs changes / Reject
