# 01 — Policy Framework Overview (Global)

## Purpose
Dokumen ini menjelaskan kontrak global untuk seluruh policy watchlist dan cara membacanya tanpa salah urut.

## Yang dianggap global
- Struktur `paramset` (kontrak JSON level global)
- Validator & aturan anti-drift
- Urutan eksekusi canonical lintas policy (PLAN selalu EOD snapshot; CONFIRM tidak mengubah PLAN)
- Kontrak auditability (reason codes, fail codes, batch hash)
- Kriteria minimum agar sebuah policy layak dianggap complete

## Start here
Urutan baca global:
1. [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md)
2. [`02_PARAMSET_CONTRACT_GLOBAL.md`](02_PARAMSET_CONTRACT_GLOBAL.md)
3. [`03_VALIDATOR_SPEC_GLOBAL.md`](03_VALIDATOR_SPEC_GLOBAL.md)
4. [`04_CONTRACT_TESTS_GLOBAL.md`](04_CONTRACT_TESTS_GLOBAL.md)
5. [`05_EXECUTION_CANONICAL_GLOBAL.md`](05_EXECUTION_CANONICAL_GLOBAL.md)
6. [`06_SCHEMA_PARITY_RULES.md`](06_SCHEMA_PARITY_RULES.md)
7. [`07_CONTRACT_FAILURE_CODES_LOCKED.md`](07_CONTRACT_FAILURE_CODES_LOCKED.md)

## Cara baca yang benar
- Baca dokumen global dulu.
- Setelah itu baru masuk ke folder policy spesifik.
- Jika ada konflik antara aturan global dan interpretasi lokal yang tidak eksplisit, anggap itu defect dokumentasi yang harus dibereskan, bukan ruang improvisasi.

## Hubungan dengan policy spesifik
Dokumen policy spesifik (contoh Weekly Swing) berada di:
- [`../weekly_swing/`](../weekly_swing/README.md) (mulai dari `../weekly_swing/01_WS_OVERVIEW.md` lalu lanjut sesuai nomor)

## Output yang harus dihasilkan framework global
Framework global harus cukup untuk menjawab 4 hal ini:
1. PLAN vs CONFIRM dipisahkan bagaimana
2. paramset sah itu seperti apa
3. validator minimum wajib apa saja
4. drift dideteksi dan diblok di layer mana
