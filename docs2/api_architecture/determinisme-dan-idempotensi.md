# Determinisme dan Idempotensi

Dokumen ini mengatur sifat hasil proses agar dapat diproduksi ulang dan aman dijalankan berulang.

## Determinisme
- Input yang sama harus menghasilkan output yang sama.
- Aturan pembulatan, fallback, warmup window, dan default value harus didefinisikan sekali dan dipakai konsisten.
- Output yang dipakai sistem lain harus bisa ditelusuri kembali ke sumbernya.

## Idempotensi
- Menjalankan proses yang sama dua kali pada scope yang sama tidak boleh menggandakan data atau menciptakan hasil berbeda tanpa alasan yang sah.
- Proses batch harus aman terhadap retry.
- Penulisan data harus dirancang agar run ulang tidak merusak integritas hasil.

## Yang dilarang
- Logic yang bergantung pada urutan acak, waktu lokal tak terkontrol, atau state global tersembunyi.
- Perubahan hasil hanya karena format tampilan atau detail non-domain.
