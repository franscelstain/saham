# Panduan Adopsi Minimum

Dokumen ini membantu tim menerapkan paket arsitektur secara bertahap tanpa langsung membuat proses terasa terlalu berat.

## Tujuan

Panduan ini dibuat agar:
- tim kecil tetap bisa mulai dengan disiplin yang cukup;
- fitur sederhana tidak dipaksa menjadi terlalu rumit;
- tim tahu kapan harus naik tingkat disiplin.

## Untuk tim kecil atau proyek awal

Minimal yang wajib dipakai:
- `README.md`
- `aturan-global-arsitektur-api.md`
- `transport-boundary.md`
- `application-service.md`
- `repository.md`
- `response-dan-error.md`
- `checklist-review-arsitektur.md`

Minimal praktik yang wajib dijaga:
- query tidak boleh di controller
- service tidak boleh jadi tempat query
- response publik harus konsisten
- request mentah berhenti di boundary
- repository tetap rumah persistence

## Untuk fitur sederhana

Boleh tetap sederhana bila:
- rule domain sangat tipis
- volume data kecil
- retry/rerun tidak relevan
- tidak ada integrasi eksternal kompleks

Pada kondisi ini:
- DTO boleh tetap sedikit
- domain compute boleh belum dipisah menjadi banyak komponen
- read/write repository boleh tetap sederhana

Tetapi tetap tidak boleh:
- query di controller
- rule bisnis besar di controller
- response liar tanpa kontrak
- request framework bocor ke semua layer

## Kapan wajib naik disiplin

Naikkan disiplin saat salah satu kondisi ini muncul:
- service mulai penuh if/else bisnis
- query mulai kompleks
- data besar mulai diproses
- batch/job mulai muncul
- retry/rerun mulai relevan
- integrasi eksternal mulai penting
- banyak endpoint mulai butuh konsistensi response
- tim bertambah dan review makin sering

## Tahap adopsi yang disarankan

### Tahap 1
Fokus pada:
- transport boundary
- application service
- repository
- response/error

### Tahap 2
Tambahkan:
- DTO yang lebih eksplisit
- handoff antar layer
- domain compute untuk rule yang mulai berat

### Tahap 3
Tambahkan concern operasional:
- performa data besar
- determinisme dan idempotensi
- logging operasional

### Tahap 4
Tambahkan governance:
- checklist review
- template review per jenis perubahan
- template pengecualian
- ADR

## Prinsip penting

- Mulai dari yang perlu, bukan dari yang paling banyak dokumennya.
- Jangan memakai alasan “fitur masih kecil” untuk membenarkan desain buruk yang jelas.
- Sederhana boleh, liar jangan.
- Begitu kompleksitas naik, boundary harus ikut diperketat.

## Red flags bahwa tim terlalu longgar

- controller mulai query database
- service mulai menulis SQL
- repository mulai menentukan hasil bisnis
- DTO mulai memanggil service
- retry dilakukan tanpa identitas operasi
- endpoint response beda-beda tanpa kontrak
- full load besar dilakukan tanpa alasan jelas

## Red flags bahwa tim terlalu berat

- terlalu banyak abstraction untuk fitur kecil
- DTO dibuat berlapis-lapis tanpa kebutuhan nyata
- domain dipecah terlalu banyak padahal rule masih sangat tipis
- template dan proses review dipakai terlalu kaku untuk perubahan sangat kecil

## Putusan praktis

Kalau ragu:
- jaga boundary dasar dulu
- jaga placement query dulu
- jaga response contract dulu
- naikkan disiplin concern lain saat kompleksitas benar-benar mulai terasa


## Minimum Adoption Path for the Active System

Untuk sistem aktif, adopsi arsitektur minimum harus mengikuti urutan berikut:

1. pastikan owner docs producer sudah stabil;
2. pastikan owner docs consumer sudah stabil;
3. pastikan baseline lintas-domain dan system assembly sudah jelas;
4. baru terapkan boundary controller/service/repository/domain compute;
5. baru refactor atau tulis implementasi dengan guardrail arsitektur ini.

## Do Not Start Architecture Refactoring Before These Contracts Are Stable

Refactor arsitektur tidak boleh dimulai bila salah satu hal berikut masih belum stabil:

- producer-facing intake;
- consumer behavior owner docs;
- system assembly order;
- placement architecture guidance sebagai translation phase.

Bila kontrak-kontrak di atas belum stabil, refactor justru berisiko menciptakan kode yang rapi tetapi salah arah.
