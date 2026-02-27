# Policy Governance — Watchlist

Folder utama: `docs/watchlist/`.

Dokumen ini mengunci aturan lintas policy: definisi PLAN/CONFIRM, asal-usul parameter, auditability, dan aturan pembacaan dokumen berbasis nomor.

Dokumentasi isi folder policy (struktur dan katalog) dijelaskan di: `policies/README.md`.

## Cara baca (anti salah tafsir)
Penomoran dokumen memakai prefix angka dua digit (01, 02, 03, ...). Baca sesuai urutan angka tersebut.
- Framework global lintas policy ada di: `policies/_shared/` (mulai dari 01, lanjut berurutan).
- Dokumen per policy ada di: `policies/` (contoh: `policies/weekly_swing/`) (mulai dari 01, lanjut berurutan).

## Rule: Document = Work (no opsi/spekulasi)
Dokumen di repository ini adalah **kontrak kerja**: hanya berisi hal yang benar-benar akan diimplementasikan.
Jika ada ide/opsi yang belum diputuskan atau belum akan dikerjakan, **jangan ditulis** sebagai bagian dari spesifikasi.
Taruh sebagai catatan terpisah di luar struktur `docs/watchlist/` (mis. ticket / note), bukan di dokumen kontrak.

Konsekuensi:
- Tidak ada “opsi A/B” di dokumen kontrak.
- Tidak ada tabel/schema/parameter yang tidak akan dibuat.
- Tidak ada referensi ke file/tabel yang tidak eksis.

## North Star (ringkasan kebutuhan)
Sistem watchlist yang dibangun harus memenuhi poin-poin berikut:

- Setiap parameter (threshold, bobot, batas likuiditas/volatilitas, dll) wajib punya asal-usul yang jelas:
  1) **BT**: kalibrasi backtest 2 tahun yang tervalidasi, dan/atau
  2) **DET**: aturan deterministik berbasis prinsip pasar yang stabil, dan/atau
  3) **MAN**: pengaturan manual yang terdokumentasi.  
  Setiap parameter wajib punya alasan dipilih serta kapan harus diubah.
- Proses pemilihan ticker boleh gabungan beberapa metode (filter kelayakan + scoring + kondisi pasar), asalkan:
  - konsisten dan deterministik,
  - bisa diaudit (jejak keputusan tersimpan),
  - tidak memaksakan saran saat data kurang/aneh,
  - selalu memberi alasan singkat yang membantu untuk tiap ticker.

## Next
Baca framework policy global (urut):
- `policies/_shared/01_POLICY_FRAMEWORK_OVERVIEW.md`
- `policies/_shared/02_PARAMSET_CONTRACT_GLOBAL.md`
- `policies/_shared/03_VALIDATOR_SPEC_GLOBAL.md`
- `policies/_shared/04_CONTRACT_TESTS_GLOBAL.md`
- `policies/_shared/05_EXECUTION_CANONICAL_GLOBAL.md`

Lalu baca policy Weekly Swing (urut):
- `policies/weekly_swing/01_WS_BOOK_OVERVIEW.md`
- `policies/weekly_swing/02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
- `policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md`
- `policies/weekly_swing/04_WS_PARAMSET_JSON_CONTRACT.md`
- `policies/weekly_swing/05_WS_PARAMETER_REGISTRY_COMPLETE.md`
- `policies/weekly_swing/06_WS_PARAMSET_VALIDATOR_SPEC.md`
- `policies/weekly_swing/07_WS_REASON_CODES_AND_HASH.md`
- `policies/weekly_swing/08_WS_PLAN_ALGORITHM.md`
- `policies/weekly_swing/09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
- `policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md`
- `policies/weekly_swing/11_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`
- `policies/weekly_swing/12_WS_CONTRACT_TEST_CHECKLIST.md`
- `policies/weekly_swing/13_WS_CANONICAL_PARAMSET_PROCEDURES.md`
- `policies/_shared/06_SCHEMA_PARITY_RULES.md`
