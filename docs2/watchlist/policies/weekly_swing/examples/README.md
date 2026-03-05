# Runtime Examples — Weekly Swing

> **Status:** LOCKED (Normative)
> **Doc Role:** Runtime examples governance


## Purpose
Pedoman resmi folder contoh output runtime Weekly Swing.

## Scope
Mengunci cara membaca, memakai, dan mengubah contoh runtime.

## Inputs
- Engineer serializer, reviewer, auditor, dan AI yang membaca contoh output.

## Outputs
- Aturan penggunaan contoh runtime resmi.

Folder ini berisi contoh **output runtime resmi** untuk policy Weekly Swing.
Tujuan folder ini bukan untuk menjelaskan algoritma; tujuan folder ini adalah menjadi **contoh bentuk output** yang harus bisa dihasilkan dan dibaca ulang secara konsisten oleh aplikasi, test, dan proses audit.

## Kegunaan folder ini
Contoh di folder ini dipakai untuk:
1. memvalidasi serializer output PLAN dan CONFIRM,
2. memvalidasi nama field, struktur objek, enum, dan nesting,
3. memvalidasi bahwa output runtime tetap sesuai schema LOCKED,
4. membantu audit replay ketika implementasi diubah,
5. memberi contoh nyata untuk engineer, reviewer, atau AI agar tidak menebak bentuk output.

Folder ini **bukan** source of truth utama untuk kontrak. Folder ini adalah artefak referensi operasional yang harus **patuh penuh** pada kontrak LOCKED dan tidak boleh dipakai untuk menimpa kontrak utama.

## Source of truth
Urutan sumber kebenaran wajib dibaca seperti ini:
1. `../_refs/WS_RUNTIME_OUTPUT_SCHEMA.md`  
   Menentukan schema output resmi, field wajib, tipe data, enum, dan aturan canonicalization.
2. `../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`  
   Menentukan batas PLAN vs CONFIRM, termasuk larangan CONFIRM mengubah PLAN.
3. `../07_WS_REASON_CODES_AND_HASH.md`  
   Menentukan aturan reason codes, canonicalization, dan hash yang relevan.
4. `../_refs/WS_WORKED_EXAMPLE_E2E.md`  
   Menjelaskan alur contoh end-to-end untuk membantu pembacaan hasil runtime.
5. File-file JSON di folder ini  
   Menjadi contoh runtime yang harus tetap konsisten dengan seluruh dokumen di atas.

Jika ada konflik antara contoh JSON di folder ini dengan dokumen LOCKED di atas, maka **dokumen LOCKED yang menang**. File contoh harus segera diperbaiki pada patch yang sama atau release berikutnya sebelum dianggap valid kembali.

## Isi folder dan fungsi tiap file
- [`WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`](WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json)  
  Contoh output PLAN yang sudah jadi dan siap disimpan/dibaca ulang.
- [`WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`](WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json)  
  Contoh output CONFIRM yang berdiri sebagai overlay terpisah.
- [`WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json`](WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json)  
  Contoh pasangan PLAN + CONFIRM untuk memeriksa immutability PLAN dan pemisahan output CONFIRM.

## Aturan penggunaan
- Gunakan file di folder ini untuk **snapshot/golden assertion** terhadap bentuk output.
- Gunakan file ini saat menguji serializer, response builder, audit reader, atau replay tool.
- Gunakan file ini untuk memastikan aplikasi tidak mengganti nama field, struktur list, enum, atau nesting tanpa sadar.
- Jangan gunakan file di folder ini sebagai dasar tunggal untuk menghitung skor, ranking, atau keputusan trading. Rumus dan logika tetap mengacu ke dokumen algoritma dan kontrak.

## Aturan LOCKED
- Struktur output yang dicontohkan di sini harus tetap konsisten dengan schema LOCKED.
- Field PLAN yang sudah canonical **tidak boleh berubah** akibat proses CONFIRM.
- Contoh CONFIRM harus tetap menunjukkan bahwa hasil CONFIRM adalah output terpisah, bukan modifikasi diam-diam terhadap record PLAN.
- Jika ada field baru yang sah ditambahkan oleh kontrak resmi, contoh di folder ini wajib diperbarui pada patch yang sama.

## Kapan file di folder ini boleh diubah
File contoh di folder ini hanya boleh diubah jika salah satu kondisi berikut benar:
1. schema resmi berubah secara sah,
2. reason code resmi berubah secara sah,
3. format runtime resmi berubah secara sah,
4. contoh lama terbukti tidak lagi mewakili kontrak yang sudah LOCKED.

Perubahan contoh **tidak boleh** dilakukan hanya karena preferensi tampilan, gaya penulisan, atau asumsi implementasi lokal.

## Kewajiban saat menambah atau mengubah contoh
Jika menambah atau mengubah file contoh di folder ini, wajib lakukan semua hal berikut:
1. cek konsistensi terhadap `WS_RUNTIME_OUTPUT_SCHEMA.md`,
2. cek konsistensi terhadap kontrak PLAN vs CONFIRM,
3. cek konsistensi reason codes dan hash bila ada field terkait,
4. pastikan contoh baru tidak menormalisasi perilaku yang dilarang,
5. update dokumen referensi yang menyebut contoh jika perlu,
6. pastikan contoh baru bisa dijelaskan dengan jelas dalam audit.

## Quick review checklist
Sebelum contoh runtime dianggap valid, pastikan:
- nama field benar,
- tipe data benar,
- enum benar,
- struktur objek/list benar,
- output PLAN dan CONFIRM tidak tercampur,
- contoh tidak melanggar kontrak immutability PLAN,
- contoh tetap cocok dengan schema LOCKED terbaru.

## Larangan
- Jangan rename file contoh seenaknya.
- Jangan ubah isi contoh agar cocok dengan bug implementasi.
- Jangan pakai contoh sebagai pembenaran untuk menyimpang dari schema.
- Jangan menambah field tidak resmi hanya agar output terlihat “lebih lengkap”.

## Status folder
Folder ini adalah **referensi runtime resmi tingkat contoh**.  
Ia bukan sumber kebenaran tertinggi, tetapi cukup penting untuk regression test, audit replay, dan pencegahan drift bentuk output.
