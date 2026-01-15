# TradeAxis
TradeAxis adalah aplikasi untuk membantu analisa beli & jual saham BEI secara lebih terukur. Fokusnya bukan “tebakan”, tapi mengubah data harga/volume menjadi sinyal yang bisa dibaca cepat dan konsisten.

# Apa yang TradeAxis lakukan
TradeAxis bekerja dalam 3 langkah besar:
- Ambil & simpan data OHLC harian (EOD)
- Hitung indikator + klasifikasi kondisi saham (Compute EOD)
- Tampilkan hasilnya di UI untuk membantu screening dan keputusan eksekusi manual

# Output utama yang dipakai user:
- Signal / Pattern: kondisi harga (konsolidasi, uptrend awal, breakout, distribusi, false breakout, dll).
- Volume Label: kekuatan minat pasar berdasarkan rasio volume terhadap baseline.
- Decision: rekomendasi EOD berbasis rule, yang mempertimbangkan signal + volume + guardrails.

# Kenapa dipisah jadi pipeline
Perhitungan indikator untuk ratusan ticker itu berat. Karena itu TradeAxis:
- menghitungnya di proses terjadwal (compute),
- menyimpan hasilnya ke database,
- lalu UI tinggal membaca hasil (cepat & stabil).

# Prinsip pengembangan
- SRP: tiap layer punya tugas jelas (orchestration, akses DB, logic domain).
- Performa: streaming data besar, batch write, minim query berulang.
- Konsistensi: konfigurasi satu sumber, build punya build_id dan MANIFEST.md.

# Cara pakai (gambaran umum)
- Pastikan data OHLC EOD sudah tersedia untuk tanggal target.
- Jalankan proses compute EOD untuk menghasilkan indikator/signal/volume/decision.
- Buka UI untuk melihat kandidat dan status terbaru.

# Apa yang TradeAxis bukan
- Bukan bot yang auto-buy/sell ke broker.
- Bukan jaminan profit.
- Keputusan final tetap di user, TradeAxis hanya membantu memperjelas kondisi.

# Dokumen
- compute_eod.md — definisi indikator & alur compute EOD
- SRP_Performa.md — aturan inti SRP + performa
- DTO.md — pedoman DTO