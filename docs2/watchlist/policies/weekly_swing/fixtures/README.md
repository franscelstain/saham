# Fixtures — Weekly Swing

> **Status:** LOCKED (Normative)
> **Doc Role:** Fixtures governance


## Purpose
Pedoman resmi folder fixture Weekly Swing.

## Scope
Mengunci fungsi fixture, kategori fixture, dan aturan perubahan fixture resmi.

## Inputs
- Engineer test, reviewer, auditor, dan AI yang membaca fixture resmi.

## Outputs
- Aturan penggunaan fixture resmi dan hubungan ke contract tests.

Folder ini berisi **fixture resmi** untuk validator, contract tests, regression tests, dan golden tests policy Weekly Swing.
Fixture di sini dipakai untuk memastikan implementasi tetap tunduk pada kontrak, bukan untuk memberi contoh naratif.

## Tujuan folder ini
Fixture di folder ini dipakai untuk:
1. menguji input valid dan invalid pada validator,
2. menguji kontrak PLAN vs CONFIRM,
3. menguji canonicalization dan hash contract,
4. menguji deterministic selection, tie handling, dan guard behavior,
5. menguji coverage/evidence minimum untuk backtest dan promote,
6. mencegah drift antara dokumen, fixture, dan test code.

## Source of truth
Urutan sumber kebenaran wajib dibaca seperti ini:
1. `../13_WS_CONTRACT_TEST_CHECKLIST.md`  
   Menentukan apa saja test kontrak yang wajib ada.
2. `../_refs/WS_GOLDEN_FIXTURES.md`  
   Menentukan inventori fixture resmi dan fungsi utamanya.
3. Dokumen LOCKED yang relevan dengan fixture tersebut  
   Contoh: validator spec, plan/confirm canonical contract, reason/hash contract, coverage matrix, OOS proof, dan artifact manifest.
4. File JSON di folder ini  
   Menjadi fixture resmi yang dipakai test untuk memverifikasi kontrak.

Jika ada konflik antara fixture di folder ini dan dokumen LOCKED yang relevan, maka **dokumen LOCKED yang menang**. Fixture harus segera diperbaiki pada patch yang sama atau release berikutnya sebelum dianggap valid kembali.

## Aturan resmi folder
- Nama file fixture di folder ini adalah **referensi resmi**.
- Isi fixture di folder ini adalah **golden content** untuk test yang terkait.
- Jika fixture di-mirror atau disalin ke folder test lain, hasil salinannya harus **byte-identical**.
- Fixture tidak boleh diubah diam-diam untuk membuat test yang salah terlihat lolos.
- Perubahan fixture yang mengubah expected behavior dianggap **breaking change**, kecuali perubahan itu memang mengikuti perubahan kontrak resmi yang sudah disetujui.

## Cara pakai
- Gunakan fixture ini langsung di unit test, contract test, integration test, atau replay validation.
- Gunakan fixture valid untuk memastikan jalur sukses tetap stabil.
- Gunakan fixture invalid untuk memastikan validator menolak input yang memang harus ditolak.
- Gunakan fixture pair/immutability untuk memastikan CONFIRM tidak mengubah PLAN.
- Gunakan fixture coverage/evidence minimum untuk guard promosi dan audit artifact.

## Kategori fixture di folder ini
Secara fungsional, fixture di folder ini mencakup beberapa kelompok berikut:
- **Validator fixtures**  
  Untuk required keys, enum, tipe data, audit fields, unknown keys, dan kontrak hash.
- **PLAN/selection fixtures**  
  Untuk tie handling, no-trade, forced watch-only, guard fail, quantile cutoff, dan snapshot universe.
- **CONFIRM fixtures**  
  Untuk snapshot overlay, field orderbook, dan immutability PLAN.
- **Backtest/evidence fixtures**  
  Untuk coverage, eval metrics, OOS proof, dan artifact reference guard.
- **Hash/canonical fixtures**  
  Untuk memastikan canonicalization dan hash tetap stabil.

## Aturan LOCKED
- File yang sudah tercantum sebagai fixture resmi tidak boleh dihapus, diganti nama, atau diubah sembarangan.
- Jika test bergantung pada sebuah fixture, maka perubahan isi fixture harus dianggap perubahan kontrak sampai terbukti sebaliknya.
- Jika dokumen LOCKED menyatakan sebuah perilaku wajib, fixture harus mencerminkan perilaku itu secara eksplisit.
- Jika implementasi saat ini bertentangan dengan fixture resmi, maka implementasi yang harus diperbaiki; bukan fixture yang diturunkan supaya bug terlihat benar.

## Kapan fixture boleh ditambah
Fixture baru boleh ditambah jika:
1. ada kontrak baru yang perlu dibuktikan dengan data konkret,
2. ada edge case penting yang belum dicakup,
3. ada bug/regresi nyata yang perlu dikunci agar tidak terulang,
4. ada dokumen LOCKED baru yang butuh fixture pendukung.

Menambah fixture baru **wajib** diikuti dengan update inventori referensi jika file baru termasuk fixture resmi.

## Kapan fixture boleh diubah
Fixture resmi hanya boleh diubah jika:
1. kontrak resmi berubah,
2. dokumen LOCKED yang relevan berubah,
3. fixture lama terbukti salah atau drift dari kontrak,
4. ada kebutuhan normalisasi yang tidak mengubah meaning kontrak namun memang diwajibkan oleh source of truth.

Setiap perubahan fixture harus bisa dijelaskan jelas dalam audit: **apa yang berubah, kenapa berubah, dokumen mana yang menjadi dasar, dan test mana yang ikut terdampak**.

## Kewajiban saat menambah atau mengubah fixture
Jika menambah atau mengubah fixture, wajib lakukan semua hal berikut:
1. pastikan file cocok dengan dokumen LOCKED terkait,
2. update `../_refs/WS_GOLDEN_FIXTURES.md` bila inventori resmi berubah,
3. cek test yang mengonsumsi fixture tersebut,
4. pastikan mirror copy tetap byte-identical,
5. tulis alasan perubahan di patch/audit summary bila perubahan memengaruhi expected behavior,
6. hindari file baru yang duplikatif tanpa fungsi kontrak yang jelas.

## Quick review checklist
Sebelum fixture dianggap sah, pastikan:
- nama file jelas dan stabil,
- fungsi fixture jelas,
- tidak duplikatif tanpa alasan,
- isi file cocok dengan kontrak yang diuji,
- valid/invalid status-nya memang sengaja,
- file yang dimirror tetap byte-identical,
- inventori referensi sudah diperbarui bila perlu.

## Larangan
- Jangan ubah fixture agar test hijau padahal kontrak salah.
- Jangan membuat fixture “semi-resmi” yang dipakai luas tetapi tidak tercatat.
- Jangan rename atau pindah file tanpa memperbarui referensi resminya.
- Jangan menambah fixture yang hanya mengulang kasus lama tanpa nilai kontrak baru.

## Status folder
Folder ini adalah **paket fixture resmi Weekly Swing** untuk pengujian kontrak dan regression.  
Selama dokumen LOCKED belum berubah, fixture resmi di folder ini harus diperlakukan stabil.

### Drift / strictness fixtures
- `confirm_payload_with_unknown_top_level_field.json` — harus **FAIL** (INVALID_SCHEMA_DRIFT) karena ada unknown top-level field.
