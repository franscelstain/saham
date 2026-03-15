# 01 — Policy Framework Overview (Global)

## Purpose

Dokumen ini adalah overview navigasional untuk policy framework lintas strategy di domain watchlist. Dokumen ini tidak menetapkan kontrak detail; setiap aturan wajib tetap dimiliki oleh dokumen owner yang dirujuk di folder `_shared/` maupun di folder strategy-specific yang relevan.

## Scope

Folder `_shared/` memuat aturan yang benar-benar lintas strategy. Isi folder ini digunakan untuk menjaga konsistensi kontrak dasar yang dapat diwarisi atau dirujuk oleh strategy-specific policies.

Aturan yang hanya berlaku untuk satu strategy tidak boleh ditetapkan di folder ini dan harus hidup di folder strategy-specific yang relevan.

## Shared Document Areas

Dokumen shared di folder ini dibagi menjadi area berikut:

- kontrak global paramset,
- spesifikasi validator global,
- baseline contract tests global,
- canonical execution baseline,
- schema parity rules,
- dan failure code contract lintas strategy.

Masing-masing area di atas tetap dimiliki oleh dokumen owner yang spesifik, bukan oleh overview ini.

## Relationship to Strategy Policies

Strategy-specific policies dapat merujuk atau mewarisi baseline shared yang relevan. Namun, strategy-specific policies tetap menjadi owner untuk aturan, perilaku, atau kontrak yang hanya berlaku pada strategy tersebut.

## Reading Guidance

Overview ini digunakan untuk memetakan dokumen shared yang relevan sebelum pembaca masuk ke strategy-specific policies. Untuk kontrak detail, pembaca harus berpindah ke dokumen owner yang ditunjuk oleh area shared yang sesuai.

## Non-Authority Statement

Dokumen ini tidak boleh dipakai sebagai dasar tunggal untuk menetapkan aturan implementasi. Jika terdapat perbedaan antara overview ini dan dokumen owner normatif, dokumen owner normatif selalu menang.
