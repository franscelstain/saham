# WS Glossary (Reference)

## Reference Status

Glosarium ini membantu pembaca menjaga konsistensi istilah saat membaca dokumen Weekly Swing. Glosarium ini tidak menetapkan kontrak perilaku, acceptance rule, atau ownership baru. Jika ada perbedaan antara glosarium ini dan dokumen normatif Weekly Swing, dokumen normatif yang berlaku.

## Purpose

Tujuan glosarium ini adalah memberi definisi ringkas yang memudahkan pembaca memahami istilah yang berulang di dokumen Weekly Swing, terutama saat istilah yang sama muncul di PLAN, CONFIRM, fixtures, examples, dan audit output.

## Term Format

Setiap istilah pada glosarium ini harus dibaca dengan struktur berikut:

- **Term**
- **Short meaning in watchlist context**
- **Primary owner document(s)**

## PLAN

### ranking
**Short meaning in watchlist context**  
Urutan relatif kandidat setelah guard dan scoring dijalankan.

**Primary owner document(s)**  
`08_WS_PLAN_ALGORITHM.md`, `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

### top_picks
**Short meaning in watchlist context**  
Kelompok kandidat prioritas tertinggi yang layak menjadi daftar utama hasil PLAN.

**Primary owner document(s)**  
`08_WS_PLAN_ALGORITHM.md`, `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

### secondary
**Short meaning in watchlist context**  
Kelompok kandidat pendukung yang tetap lolos tetapi berada di bawah `top_picks` dalam prioritas.

**Primary owner document(s)**  
`08_WS_PLAN_ALGORITHM.md`, `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

### watch_only
**Short meaning in watchlist context**  
Label untuk kandidat yang masih layak dipantau tetapi belum layak diprioritaskan sebagai kandidat aktif.

**Primary owner document(s)**  
`08_WS_PLAN_ALGORITHM.md`, `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

### avoid
**Short meaning in watchlist context**  
Kelompok kandidat yang tidak layak diprioritaskan karena gagal guard atau alasan penting lain yang ditetapkan strategy.

**Primary owner document(s)**  
`08_WS_PLAN_ALGORITHM.md`, `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

## CONFIRM

### overlay
**Short meaning in watchlist context**  
Pembacaan snapshot intraday di atas hasil PLAN yang sudah ada, tanpa mengubah semantics PLAN itu sendiri.

**Primary owner document(s)**  
`10_WS_CONFIRM_OVERLAY.md`

### stale
**Short meaning in watchlist context**  
Kondisi ketika snapshot atau input intraday sudah terlalu tua untuk dipakai sebagai dasar evaluasi CONFIRM.

**Primary owner document(s)**  
`10_WS_CONFIRM_OVERLAY.md`, `11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### delay
**Short meaning in watchlist context**  
Hasil CONFIRM yang menunjukkan keputusan perlu ditunda karena input belum cukup kuat, belum cukup lengkap, atau snapshot tidak layak dipakai.

**Primary owner document(s)**  
`10_WS_CONFIRM_OVERLAY.md`

### caution
**Short meaning in watchlist context**  
Hasil CONFIRM yang menunjukkan input tersedia tetapi mengandung kondisi yang memerlukan kewaspadaan tambahan.

**Primary owner document(s)**  
`10_WS_CONFIRM_OVERLAY.md`

### confirmed
**Short meaning in watchlist context**  
Hasil CONFIRM yang menunjukkan snapshot masih layak dipakai dan tidak ada sinyal negatif yang cukup kuat untuk menahan evaluasi positif strategy.

**Primary owner document(s)**  
`10_WS_CONFIRM_OVERLAY.md`

## Snapshot (CONFIRM Snapshot)

### effective_captured_at
**Short meaning in watchlist context**  
Timestamp referensial yang dipakai untuk menilai umur snapshot yang benar-benar relevan bagi proses CONFIRM.

**Primary owner document(s)**  
`11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### TTL CONFIRM
**Short meaning in watchlist context**  
Batas usia snapshot intraday yang masih dianggap layak untuk evaluasi CONFIRM.

**Primary owner document(s)**  
`10_WS_CONFIRM_OVERLAY.md`, `11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### captured_at
**Short meaning in watchlist context**  
Timestamp saat snapshot atau data intraday dikumpulkan dari sumbernya.

**Primary owner document(s)**  
`11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### inserted_at
**Short meaning in watchlist context**  
Timestamp saat snapshot atau input dimasukkan ke storage atau tabel kerja.

**Primary owner document(s)**  
`11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### checked_at
**Short meaning in watchlist context**  
Timestamp saat proses atau reviewer melakukan pemeriksaan terhadap snapshot.

**Primary owner document(s)**  
`11_WS_INTRADAY_SNAPSHOT_TABLES.md`

## Numeric / Aggregate Fields

### volume_shares
**Short meaning in watchlist context**  
Representasi jumlah saham atau unit perdagangan yang berpindah tangan pada snapshot atau agregat tertentu.

**Primary owner document(s)**  
`03_WS_DATA_MODEL_MARIADB.md`, `11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### turnover_idr
**Short meaning in watchlist context**  
Representasi nilai perdagangan dalam Rupiah.

**Primary owner document(s)**  
`03_WS_DATA_MODEL_MARIADB.md`, `11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### score_total
**Short meaning in watchlist context**  
Skor agregat kandidat setelah komponen penting digabungkan menurut rule strategy.

**Primary owner document(s)**  
`08_WS_PLAN_ALGORITHM.md`, `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

### plan_hash
**Short meaning in watchlist context**  
Jejak ringkas yang mewakili payload PLAN canonical sesuai kontrak normatif hashing.

**Primary owner document(s)**  
`07_WS_REASON_CODES_AND_HASH.md`, `13_WS_CONTRACT_TEST_CHECKLIST.md`

## Non-Contract Fields (CONFIRM)

### non-contract fields
**Short meaning in watchlist context**  
Field tambahan yang boleh hadir untuk membantu pembacaan, debugging, atau tampilan, tetapi tidak menjadi dasar kontrak hasil CONFIRM.

**Primary owner document(s)**  
`10_WS_CONFIRM_OVERLAY.md`, `13_WS_CONTRACT_TEST_CHECKLIST.md`

## Final Rule

Glosarium ini merangkum istilah. Jika suatu istilah tampak memiliki makna normatif yang lebih kuat di dokumen owner, dokumen owner normatif selalu menang.
