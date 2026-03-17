# Link Integrity Check (LOCKED)

## Tujuan
Dokumen ini mengunci standar integritas referensi file dalam [`docs/watchlist/`](./README.md).

Jika sebuah dokumen menyebut file lain sebagai rujukan resmi, maka:
- file tersebut **wajib ada** di repo,
- path yang digunakan **wajib resolve**, dan
- perubahan path **wajib terdeteksi** (bukan ketahuan belakangan lewat human review).

## Konvensi Path (LOCKED)
1) **Dokumen root normatif** di [`docs/watchlist/system/`](./README.md) (mis. [`policy.md`](./policy.md), [`README.md`](./README.md), dokumen ini) boleh menyebut path repo-root dengan prefix `docs/...` untuk menegaskan root scope.

2) **Dokumen selain root normatif** (mis. `docs/watchlist/system/policies/**`, `docs/watchlist/system/db/**`, `docs/watchlist/system/implementation/**`) wajib memakai **path relatif** dari lokasi file tersebut.
   - Dilarang menulis prefix `docs/...` di dokumen-dokumen ini.

3) Jika sebuah path dirujuk di teks, gunakan **Markdown link** (klikable), bukan sekadar inline code.


## Mode Dokumen (LOCKED): SELF-CONTAINED

Paket `docs/` dianggap **self-contained**. Artinya: seluruh referensi internal **wajib tetap valid** walaupun folder `docs/` dipindah ke lokasi lain (tanpa repo di luar `docs/`).

Konsekuensi:
- Link **tidak boleh keluar** dari root `docs/` (dilarang “escape” dengan `../` sampai melampaui `docs/`).
- Link folder **wajib eksplisit** ke file `README.md` (hindari link ke directory seperti `(./)` atau `(db/)` bila `README.md` yang dimaksud).

## Aturan Path Lokal (LOCKED)

1) **Dilarang absolute path** untuk file lokal:
   - Dilarang: `/docs/watchlist/...`, `C:\...`, `D:\...`
   - Dilarang: `docs/...` di dokumen non-root.

2) **Dilarang directory-link** (harus target file):
   - Salah: [`db/`](db/README.md)
   - Benar: [`db/README.md`](db/README.md)

3) **Batas escape `../`**:
   - Boleh: `../README.md` jika parent masih di dalam `docs/`.
   - Dilarang: target yang resolve ke luar `docs/` (apa pun bentuknya).


## Aturan Wajib
- Tidak boleh ada “file hantu” (disebut tapi tidak ada).
- Jika ada rename/move file, semua referensi harus ikut diperbarui.
- Jika sebuah artefak memang tidak disertakan, itu harus ditulis eksplisit sebagai **non-scope** dan tidak boleh ditulis seolah-olah “wajib ada”.

## Minimal Check (LOCKED)
Setiap perubahan di folder [`docs/watchlist/`](./README.md) wajib menjalankan pemeriksaan berikut:

1) Scan seluruh `*.md` di [`docs/watchlist/`](./README.md).
2) Ambil semua markdown link target `(...)` yang mengarah ke file lokal (bukan URL).
3) Resolve target relatif terhadap lokasi dokumen.
4) Fail jika target tidak ada.

Implementasi boleh via script sederhana (bash/python/node), tapi hasilnya harus deterministik.

## Status
Dokumen ini adalah kontrak. Jika ada pelanggaran, itu dianggap **documentation parity failure**.
