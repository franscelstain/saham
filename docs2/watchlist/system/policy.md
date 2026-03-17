# Policy Governance — Watchlist

## Purpose

Dokumen ini adalah governance tertinggi domain watchlist. Dokumen ini menetapkan hierarchy source of truth, aturan konflik, ownership domain, dan klasifikasi dokumen normatif versus dokumen pendukung di seluruh `docs/watchlist/system/` sebagai root normatif domain watchlist.

## Scope

Dokumen ini berlaku untuk seluruh struktur normatif di bawah `docs/watchlist/system/`, termasuk:

- `db/`
- `implementation/`
- `policies/_shared/`
- `policies/<strategy>/`

Dokumen policy yang lebih spesifik tetap mengunci detail teknis strategy, tetapi tidak boleh menyalahi governance root ini.

## Source of Truth Hierarchy

Hierarchy source of truth di domain watchlist adalah:

1. `docs/watchlist/system/policy.md`
2. `docs/watchlist/system/README.md`
3. file normatif bernomor pada strategy yang relevan
4. aturan lintas strategy di `docs/watchlist/system/policies/_shared/`
5. `_refs/`
6. `examples/`
7. `fixtures/`

Dokumen dengan tingkat lebih rendah tidak boleh membatalkan, mengganti, atau memperluas aturan wajib yang sudah ditetapkan secara authoritative pada tingkat yang lebih tinggi.

## Conflict Resolution

Jika terjadi konflik:

- governance domain menang atas interpretasi lokal,
- file normatif bernomor menang atas `_refs/`,
- `_refs/` hanya menjelaskan dan tidak menetapkan aturan baru,
- `examples/` dan `fixtures/` hanya mengilustrasikan atau menguji kontrak yang sudah ada,
- artefak `db/` adalah implementasi persistence dan tidak menjadi owner business rule.

Jika ditemukan perbedaan antara dokumen normatif dan dokumen pendukung, dokumen normatif selalu menang dan dokumen pendukung harus diperbarui.

## Ownership Rule

Aturan wajib harus hidup di dokumen normatif yang menjadi owner topiknya.

Jika ditemukan aturan yang benar tetapi salah tempat, substansinya harus dipindahkan ke owner yang benar. Dokumen sumber lama harus diturunkan menjadi referensi atau dihapus bila tidak lagi menambah kejelasan.

Dokumen pendukung tidak boleh dipakai untuk mengunci aturan yang belum atau tidak hidup di dokumen owner normatif.

## Relationship to `market_data`

Domain watchlist tidak mendefinisikan ulang kontrak upstream yang dimiliki `market_data`.

Hal-hal berikut tetap dimiliki secara authoritative oleh `docs/market_data/` atau owner upstream setara yang memang menjadi source of truth upstream:

- bars / OHLCV,
- indicators,
- publication,
- readiness,
- validity,
- dan kontrak upstream lain yang menyediakan input ke consumer.

Dokumen watchlist hanya boleh menyatakan ketergantungan downstream terhadap kontrak upstream tersebut dan harus merujuk ke owner authoritative yang benar.

## Supporting Document Classes

Klasifikasi dokumen pendukung di domain watchlist adalah sebagai berikut:

- `_refs/`  
  Ringkasan, elaborasi, glossary, template, atau worked examples.

- `examples/`  
  Contoh bentuk output atau representasi runtime yang mengikuti kontrak normatif.

- `fixtures/`  
  Golden fixtures untuk validasi determinism, contract behavior, dan acceptance.

- `db/`  
  Artefak persistence dan implementasi SQL/schema milik watchlist.

Tidak satu pun dari folder di atas menjadi owner aturan wajib. Aturan wajib tetap harus dapat ditelusuri ke dokumen normatif yang benar.

## Action Rule for Misplaced Content

Jika suatu isi:

- benar tetapi salah tempat, pindahkan ke owner yang benar,
- berguna tetapi bernada terlalu normatif di dokumen referensial, ubah menjadi referensial,
- mengulang tanpa menambah kejelasan, padatkan atau hapus,
- membentuk kontrak ganda, tetapkan satu owner dan turunkan yang lain menjadi referensi.

## Interpretation Rule

Domain watchlist harus dibaca sebagai spesifikasi sistem yang formal, netral, dan profesional. Dokumen di domain ini tidak ditulis sebagai prompt untuk AI dan tidak boleh bergantung pada interpretasi informal untuk memahami aturan wajib.
