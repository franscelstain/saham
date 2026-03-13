# WS Glossary (Reference)

## Reference status
Glosarium ini membantu pembaca menjaga konsistensi istilah saat membaca dokumen Weekly Swing. Glosarium ini tidak menetapkan kontrak perilaku atau acceptance rule baru. Jika ada perbedaan antara glosarium ini dan dokumen normatif bernomor, dokumen normatif bernomor yang berlaku.

## Purpose
Tujuan glosarium ini adalah memberi definisi ringkas yang memudahkan pembaca memahami istilah yang berulang di dokumen Weekly Swing, terutama saat istilah yang sama muncul di PLAN, CONFIRM, fixture, examples, dan audit output.

## PLAN
### ranking
Urutan relatif kandidat setelah guard dan scoring dijalankan. Dalam implementasi, field ini biasanya dipakai untuk urutan baca hasil PLAN, bukan untuk menghitung ulang skor.

### top_picks
Kelompok kandidat prioritas tertinggi yang layak masuk daftar utama hasil PLAN. Di UI atau laporan, kelompok ini biasanya menjadi daftar pertama yang dibaca reviewer.

### secondary
Kelompok kandidat pendukung yang tetap lolos, tetapi berada di bawah `top_picks` dalam prioritas. Kelompok ini berguna saat engineer atau reviewer ingin melihat kandidat cadangan tanpa mencampurnya dengan kandidat utama.

### watch_only
Label untuk kandidat yang masih layak dipantau tetapi belum layak diperlakukan sebagai kandidat aktif. Label ini berguna untuk memisahkan “masih menarik” dari “siap diprioritaskan”.

### avoid
Kelompok kandidat yang tidak layak diprioritaskan karena gagal guard atau alasan penting lain. Dalam pembacaan hasil, `avoid` membantu pembaca melihat bahwa kandidat memang dipertimbangkan tetapi tidak lolos.

## CONFIRM
### overlay
Proses pembacaan snapshot intraday di atas hasil PLAN yang sudah ada, tanpa mengubah hasil PLAN itu sendiri. Istilah ini penting untuk menjaga batas bahwa CONFIRM mengevaluasi, bukan menulis ulang seleksi PLAN.

### stale
Kondisi ketika snapshot atau input intraday sudah terlalu tua untuk dipakai sebagai dasar keputusan CONFIRM. Saat implementasi atau audit, istilah ini biasanya dibaca bersama umur snapshot dan TTL.

### delay
Hasil CONFIRM yang menunjukkan keputusan perlu ditunda karena input belum cukup kuat, belum cukup lengkap, atau snapshot tidak layak dipakai. `delay` tidak sama dengan gagal total; ini lebih dekat ke “jangan eksekusi dulu”.

### caution
Hasil CONFIRM yang menunjukkan input tersedia tetapi mengandung kondisi yang perlu kewaspadaan tambahan, misalnya drift terlalu jauh dari entry yang diharapkan atau volume intraday tidak cukup kuat.

### confirmed
Hasil CONFIRM yang menunjukkan snapshot masih layak dipakai dan tidak ada sinyal negatif yang cukup kuat untuk menahan eksekusi. Istilah ini berguna saat pembaca ingin membedakan outcome yang aktif dari outcome hati-hati.

## Snapshot (CONFIRM Snapshot)
### effective_captured_at
Timestamp referensial yang dipakai untuk menilai umur snapshot yang benar-benar relevan bagi proses CONFIRM. Field ini membantu pembaca membedakan waktu snapshot dianggap berlaku dari timestamp lain seperti waktu insert atau waktu baca ulang.

### TTL CONFIRM
Batas usia snapshot intraday yang masih dianggap layak untuk evaluasi CONFIRM. Setelah melewati TTL, snapshot biasanya tidak lagi diperlakukan sebagai dasar yang aman untuk keputusan overlay.

### captured_at
Timestamp saat snapshot atau data intraday dikumpulkan dari sumbernya. Field ini sering dipakai untuk menelusuri kapan data sebenarnya diambil.

### inserted_at
Timestamp saat snapshot atau input dimasukkan ke storage atau tabel kerja. Field ini berguna untuk audit operasional, tetapi tidak selalu identik dengan waktu data diambil.

### checked_at
Timestamp saat proses atau reviewer melakukan pemeriksaan terhadap snapshot. Field ini membantu menelusuri jejak proses, tetapi tidak otomatis menjadi acuan umur snapshot yang sah.

## Numeric / aggregate fields
### volume_shares
Representasi jumlah saham atau unit perdagangan yang berpindah tangan pada snapshot atau agregat tertentu. Istilah ini berguna untuk membedakan ukuran aktivitas berbasis volume dari ukuran berbasis nilai uang.

### turnover_idr
Representasi nilai perdagangan dalam Rupiah. Istilah ini dipakai ketika pembaca perlu melihat besarnya aktivitas pasar dalam satuan nilai, bukan hanya jumlah saham.

### score_total
Skor agregat kandidat setelah komponen penting digabungkan menurut rule strategi. Dalam pembacaan hasil, field ini biasanya dipakai untuk memahami urutan relatif kandidat, bukan sebagai satu-satunya penjelas keputusan.

### plan_hash
Jejak ringkas yang mewakili payload PLAN canonical sesuai kontrak normatif hashing. Istilah ini berguna untuk audit, immutability check, dan cross-check antara output PLAN sebelum dan sesudah proses lain.

## Non-Contract Fields (CONFIRM)
Istilah ini mengacu pada field tambahan yang boleh hadir untuk membantu pembacaan, debugging, atau tampilan, tetapi tidak dijadikan dasar kontrak hasil CONFIRM. Kehadirannya dapat berguna, tetapi field ini sebaiknya tidak dijadikan dasar utama saat membaca perilaku normatif.
