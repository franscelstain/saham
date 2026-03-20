# System Ownership Map

## Purpose
Dokumen ini menjelaskan siapa pemilik behavior, siapa yang hanya menerjemahkan, dan siapa yang hanya membantu membaca repo.

## Ownership categories
### Normative core
Owner utama behavior market-data berada di:
- `book/`
- `db/` untuk enforcement/support contracts yang langsung mengikat behavior
- `registry/` untuk constrained baseline values and registries
- `indicators/` untuk deterministic computation specification
- `session_snapshot/` untuk snapshot-specific contracts bila fitur snapshot aktif

### Normative support and proof-critical baseline
Support yang sangat penting untuk implementasi, operasi, dan pembuktian berada di:
- `ops/`
- `tests/`
- `backtest/` bila replay/backtest digunakan untuk memvalidasi data quality, correction behavior, atau replay correctness dari market-data

### Companion layers
- `system/` = ringkasan sistem dan peta baca
- `audit/` = alat evaluasi

### Non-owner supporting layers
- `examples/` = illustrative only
- `evidence/` = archived actual proof if genuine

## Authority rule
Jika terjadi konflik:
1. `LOCKED` contracts win
2. owner contracts win over support docs
3. support docs win over summary only in the sense of implementation/proof detail, never by creating new behavior
4. summary docs never override owner behavior
5. examples and evidence never define future behavior

## Practical reading rule
- gunakan `system/` untuk orientasi
- gunakan `book/` sebagai pusat behavior domain
- gunakan `db/`, `registry/`, `indicators/`, dan `session_snapshot/` untuk detail kontrak teknis dan constrained specification
- gunakan `ops/`, `tests/`, dan `backtest/` untuk operasi dan pembuktian
- gunakan `examples/` dan `evidence/` hanya sebagai pendamping

## Misread prevention
Hal berikut dianggap salah baca:
- menganggap `system/` sebagai source-of-truth utama
- menganggap `backtest/` otomatis domain watchlist; pada paket ini ia bisa menjadi market-data proof support
- menganggap `registry/` dan `indicators/` hanya pelengkap padahal mereka bisa menentukan hasil platform
- menganggap `evidence/` sebagai kontrak perilaku ke depan
