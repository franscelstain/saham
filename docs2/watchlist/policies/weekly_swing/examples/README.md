# Runtime Examples — Weekly Swing

> **Status:** REFERENCE
> **Doc Role:** Runtime examples index

## Purpose
Menjelaskan fungsi folder contoh runtime Weekly Swing dan cara memakainya tanpa menjadikannya sumber kontrak.

## Scope
Dokumen ini hanya mengatur cara membaca dan merawat contoh output runtime.
Dokumen ini bukan owner aturan perilaku sistem.

## Inputs
- Engineer serializer, reviewer, auditor, dan pembaca yang membutuhkan contoh bentuk output.

## Outputs
- Peta contoh runtime yang tersedia.
- Batas yang jelas antara contoh dan kontrak normatif.

Folder ini berisi contoh **output runtime** untuk policy Weekly Swing.
Fungsinya adalah membantu pembaca melihat bentuk payload yang patuh kontrak, membantu regression test, dan membantu audit replay.

## Normative owner
Kontrak normatif untuk runtime output Weekly Swing tetap berada pada dokumen bernomor utama berikut:
1. [`../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
2. [`../03_WS_DATA_MODEL_MARIADB.md`](../03_WS_DATA_MODEL_MARIADB.md)
3. [`../07_WS_REASON_CODES_AND_HASH.md`](../07_WS_REASON_CODES_AND_HASH.md)
4. [`../10_WS_CONFIRM_OVERLAY.md`](../10_WS_CONFIRM_OVERLAY.md)

Referensi pendukung yang boleh membantu pembacaan:
- [`../_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`](../_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md)
- [`../_refs/WS_WORKED_EXAMPLE_E2E.md`](../_refs/WS_WORKED_EXAMPLE_E2E.md)
- file JSON di folder ini

Jika ada konflik antara contoh di folder ini dan dokumen normatif di atas, maka dokumen normatif yang berlaku dan contoh harus dirapikan.

## Isi folder
- [`WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`](WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json) — contoh output PLAN.
- [`WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`](WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json) — contoh output CONFIRM sebagai overlay terpisah.
- [`WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json`](WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json) — contoh pasangan PLAN + CONFIRM untuk memeriksa immutability PLAN.

## Cara pakai
- Pakai contoh di folder ini untuk melihat bentuk payload dan untuk golden assertion atas struktur output.
- Jangan memakai contoh di folder ini sebagai sumber tunggal untuk scoring, ranking, grouping, atau keputusan trading.
- Saat contoh diperbarui, acuan perbaikannya tetap dokumen normatif bernomor.

## Review minimum
Sebelum contoh dianggap layak dipakai, cek bahwa:
- nama field, tipe data, enum, dan nesting cocok dengan kontrak,
- PLAN dan CONFIRM tidak tercampur,
- contoh tidak melegitimasi perilaku yang dilarang,
- perubahan contoh bisa dijelaskan dengan dasar dokumen normatif.
