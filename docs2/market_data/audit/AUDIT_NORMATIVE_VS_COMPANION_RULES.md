# Audit Normative vs Companion Rules

## Purpose
Dokumen ini memaksa pembedaan antara owner behavior, support behavior, ringkasan, ilustrasi, dan archived proof agar audit tidak salah membaca kekuatan sebuah file.

## Normative core materials
Dokumen dianggap normative core bila menetapkan behavior, invariant, acceptance boundary, publication semantics, correction semantics, identity rules, atau data-product contract.

Umumnya berada di:
- `book/`
- `db/` untuk enforcement/support contracts yang langsung mengikat behavior
- `registry/` untuk baseline registry yang memang membatasi behavior platform
- `indicators/` untuk formula/computation specification yang menentukan hasil deterministik
- `session_snapshot/` untuk snapshot contracts bila fitur snapshot memang ada dalam paket

## Normative support materials
Dokumen dianggap normative support bila menerjemahkan atau menegakkan owner behavior untuk keperluan operasi, pembuktian, atau replay validation, tanpa menjadi sumber baru perilaku domain.

Umumnya berada di:
- `ops/`
- `tests/`
- `backtest/` bila dipakai sebagai proof of data-quality, replay-quality, correction-quality, atau acceptance validation terhadap market-data behavior

## System companion materials
Dokumen di `system/` adalah high-level companion. Mereka membantu pembaca memahami bentuk repo, boundary, ownership, runtime flow, dan read order. Mereka tidak boleh menambah perilaku baru atau mengalahkan owner contracts.

## Illustrative materials
Contoh payload, contoh manifest, contoh evidence bundle, contoh executed diff, dan contoh folder layout adalah illustrative. Mereka membantu pemahaman, tetapi tidak menetapkan behavior resmi.

Umumnya berada di:
- `examples/`

## Archived actual evidence
`evidence/` berisi artifact yang benar-benar terjadi bila folder tersebut memang terisi actual runtime material. Evidence penting untuk pembuktian, tetapi bukan owner of future behavior. Evidence hanya membuktikan apa yang terjadi, bukan mendefinisikan apa yang seharusnya terjadi.

## Audit materials
`audit/` berisi cara menilai paket. Audit tidak boleh diperlakukan sebagai source of truth untuk behavior runtime.

## Folder role map
- `system/` = high-level companion and entry-point
- `book/` = normative domain contracts
- `db/` = schema, enforcement, and persistence support contracts
- `ops/` = operational contracts, runbooks, and operator decision support
- `tests/` = proof specification, fixture contracts, admission criteria
- `registry/` = normative registries and constrained baseline values
- `indicators/` = deterministic formula and computation specification
- `session_snapshot/` = snapshot-specific normative contracts when feature is active
- `backtest/` = replay/backtest proof support when used to validate market-data quality or corrections
- `examples/` = illustrative only
- `evidence/` = archived actual evidence if genuine
- `audit/` = evaluation tools only

## Conflict rule
Bila terjadi konflik:
1. `LOCKED` owner contract menang
2. owner contract menang atas schema/support summary
3. normative core menang atas normative support
4. normative/support menang atas system summary
5. system summary menang atas nothing, tetapi tidak boleh mengalahkan contract
6. examples dan archived evidence tidak boleh mengalahkan contract

## Misread prevention rule
Hal-hal berikut wajib dianggap salah baca:
- menganggap `system/` sebagai owner of behavior
- menganggap `examples/` sebagai runtime contract
- menganggap `evidence/` sebagai sumber behavior baru
- menganggap `backtest/` otomatis consumer-strategy domain; di paket ini ia bisa menjadi proof support untuk market-data
- menganggap `registry/` sekadar referensi tambahan padahal ia dapat membatasi behavior platform
