# System Data Product Map

## Purpose
Dokumen ini merangkum data products yang dihasilkan market-data dan status readiness-nya secara high-level.

## Core product families
- EOD bars
- indicators
- eligibility snapshots
- publication manifests
- run summaries and anomaly artifacts
- correction / replay outputs
- session snapshots if applicable

## Product-state language
Gunakan status berikut secara konsisten:
- source-acquired
- normalized
- validated
- publishable
- published
- corrected / resealed
- archived as evidence

## Ownership principle
Detail contract tiap product tetap berada di `book/`, `db/`, `ops/`, dan `tests/`. Dokumen ini hanya peta ringkas.
