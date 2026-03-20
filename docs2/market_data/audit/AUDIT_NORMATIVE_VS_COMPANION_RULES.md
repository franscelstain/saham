# Audit Normative vs Companion Rules

## Normative materials
Dokumen dianggap normative bila menetapkan behavior, contract, invariant, atau acceptance boundary sistem.

## Companion materials
Dokumen dianggap companion bila membantu membaca, mengaudit, atau mengimplementasikan normative materials tanpa menjadi owner behavior.

## Illustrative materials
Contoh payload, contoh manifest, dan worked examples adalah illustrative. Mereka tidak boleh mengalahkan contract.

## Archived actual evidence
Evidence archive berisi bukti nyata yang terjadi. Evidence penting untuk pembuktian, tetapi tidak otomatis menjadi penentu behavior resmi.

## Folder role map
- `system/` = high-level system baseline companion
- `book/` = normative domain contracts
- `db/` = schema / persistence support contracts
- `ops/` = operational rules dan guidance
- `tests/` = proof specification / admission contracts
- `examples/` = illustrative only
- `evidence/` = archived actual evidence if genuine
- `audit/` = evaluation tools

## Conflict rule
Bila summary atau example bertentangan dengan contract, contract menang.
