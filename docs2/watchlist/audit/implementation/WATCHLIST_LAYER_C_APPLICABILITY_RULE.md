# Watchlist Layer C Applicability Rule

## Tujuan

Dokumen ini mengunci cara menilai **real implementation evidence** pada audit watchlist agar reviewer tidak memberi `PARTIAL` hanya karena ZIP memang **bukan** paket Layer C.

## Aturan Utama

- Jika ZIP **tidak** mengaktifkan Layer C menurut [`../../LAYER_ACTIVATION_RULE.md`](../../LAYER_ACTIVATION_RULE.md), maka seluruh cek yang khusus untuk code/app/runtime nyata **MUST** diberi status `N/A`, bukan `PARTIAL`.
- `PARTIAL` hanya boleh dipakai bila Layer C **aktif** tetapi bukti implementasi nyatanya belum lengkap, belum sinkron, atau hanya sebagian yang patuh.
- `FAIL` hanya boleh dipakai bila Layer C **aktif** dan bukti nyata menunjukkan pelanggaran terhadap baseline watchlist.

## Contoh Penerapan

### Benar
- ZIP berisi guidance + examples + fixtures + SQL support saja → item Layer C = `N/A`
- ZIP berisi service nyata + API nyata, tetapi persistence nyata belum sinkron → item Layer C terkait persistence = `PARTIAL`
- ZIP berisi payload runtime nyata yang menyatukan recommendation dan confirm secara salah → item terkait = `FAIL`

### Salah
- ZIP dokumen A+B diberi `PARTIAL` pada real-app evidence hanya karena code nyata belum ada
- ZIP guidance diberi penalti seolah-olah wajib membawa service/controller/repository nyata

## Final Rule

Ketiadaan bukti implementasi nyata **bukan defect** bila ZIP memang tidak mengaktifkan Layer C. Dalam kondisi itu, reviewer **MUST NOT** menurunkan nilai hanya karena artefak Layer C tidak ada.
