# Audit Domain Boundary

## What this domain is
Market-data adalah domain producer-side data platform yang bertanggung jawab atas:
- acquisition
- normalization
- validation
- persistence
- publication
- correction
- replay
- auditability and reproducibility

## What this domain is not
Market-data bukan domain untuk:
- stock picking
- watchlist grouping
- recommendation output
- execution routing
- broker integration behavior
- portfolio decision logic

## Allowed downstream mention
Consumer downstream boleh disebut hanya untuk:
- readability guarantees
- readiness contracts
- interface expectations
- dependency statements

## Disallowed downstream takeover
Bila dokumen mulai menentukan bagaimana consumer memilih saham atau mengeksekusi keputusan, itu adalah drift.

## Drift severity
- minor terminology bleed = PARTIAL
- mixed ownership / mixed purpose = PARTIAL to FAIL
- market-data document turned into strategy or execution owner = FAIL
