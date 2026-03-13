# Performa Data Besar

Dokumen ini mengatur standar performa minimum untuk akses dan pemrosesan data besar.

## Aturan performa wajib
- Pemrosesan data besar wajib streaming atau bertahap. Full load ke memori tanpa alasan yang sah dilarang.
- Penulisan data besar wajib menggunakan batching, buffer, flush berkala, atau mekanisme setara.
- Query utama harus memilih kolom minimum yang benar-benar dipakai.
- N+1 query dilarang.
- Pengurutan chunk harus deterministik dan stabil.
- Kebutuhan index dianggap bagian dari fitur, bukan optimasi tambahan belakangan.

## Yang harus diperiksa
- Query utama benar-benar index-friendly.
- Range besar tetap bisa diproses tanpa OOM.
- Bulk read dan bulk write dilakukan pada layer persistence resmi.

## Hubungan dengan dokumen lain
- Rumah query tetap di `repository.md`.
- Concern run ulang dan hasil identik dibahas di `determinisme-dan-idempotensi.md`.
