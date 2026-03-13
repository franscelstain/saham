# Template Pengecualian Arsitektur

Dokumen ini adalah template resmi untuk mencatat pengecualian arsitektur. Template ini dipakai saat ada kebutuhan yang secara sadar menyimpang dari aturan normal, tetapi masih dianggap sah karena alasan yang jelas dan dengan pengaman yang cukup.

## Aturan umum

- Pengecualian harus sempit.
- Pengecualian harus punya owner.
- Pengecualian harus punya alasan yang nyata.
- Pengecualian harus punya dampak yang dijelaskan.
- Pengecualian harus punya batas waktu review.
- Pengecualian tidak boleh berubah menjadi praktik default tanpa keputusan baru.

---

## Template

### Judul pengecualian
Tulis judul singkat dan spesifik.

### ID pengecualian
Isi dengan ID unik.

### Tanggal dibuat
Isi tanggal pembuatan pengecualian.

### Owner
Isi nama tim atau orang yang bertanggung jawab.

### Dokumen utama yang dikecualikan
Sebut file dan bagian aturan yang dikecualikan.

### Ringkasan penyimpangan
Jelaskan secara singkat apa penyimpangannya.

### Alasan bisnis atau teknis
Jelaskan alasan nyata, bukan alasan umum.

### Cakupan
Jelaskan seberapa sempit pengecualian ini.

### Risiko
Jelaskan risiko yang muncul karena penyimpangan ini.

### Pengaman
Jelaskan pengaman yang dipakai agar penyimpangan tetap terkendali.

### Alternatif yang dipertimbangkan
Tulis alternatif yang sempat dipertimbangkan dan kenapa tidak dipilih.

### Dampak ke boundary
Jelaskan boundary apa yang tetap dijaga dan apa yang dilonggarkan.

### Dampak ke testing
Jelaskan test tambahan yang wajib ada.

### Dampak ke operasional
Jelaskan logging, monitoring, retry, rerun, atau observabilitas tambahan bila perlu.

### Batas waktu review ulang
Isi tanggal kapan pengecualian ini wajib ditinjau lagi.

### Kondisi pencabutan
Jelaskan kapan pengecualian harus dihapus.

### Status
Pilih salah satu:
- Proposed
- Approved
- Rejected
- Expired
- Removed

### Persetujuan
Isi siapa yang menyetujui pengecualian.
