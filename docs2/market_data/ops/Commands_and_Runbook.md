# Commands and Runbook (Market Data Platform, LOCKED)

## Tujuan
Dokumen ini mengunci command minimum agar platform dapat memproduksi data pasar upstream yang canonical, tervalidasi, deterministik, auditable, dan siap dikonsumsi modul aplikasi lain.

Dokumen ini **tidak** membahas screening, scoring, grouping, ranking, logika strategi, maupun kebutuhan data real-time.

## Aturan umum command (LOCKED)
- semua command harus berjalan dalam konteks `run_id` yang jelas
- semua command untuk requested date yang sama harus mematuhi kontrak locking
- command wajib menulis event audit minimum ke `eod_run_events`
- command dilarang menulis final `SUCCESS` sebelum seal berhasil ditulis
- semua output-affecting parameter harus dibaca dari registry efektif, bukan override liar

## Command minimum (WAJIB)
### 1) `market-data:eod-bars:ingest`
Input minimum:
- `requested_date=T`
- source mode (`api|manual_file|manual_entry`)

Output minimum:
- valid bars -> `eod_bars`
- invalid bars -> `eod_invalid_bars`
- telemetry counts -> `eod_runs`
- audit events -> `eod_run_events`

### 2) `market-data:eod-indicators:compute`
Input minimum:
- `requested_date=T`
- `indicator_set_version`

Output minimum:
- hitung indikator dari `eod_bars`
- tulis `eod_indicators`
- update invalid/row counts + events

### 3) `market-data:eod-eligibility:build`
Input minimum:
- `requested_date=T`
- coverage universe snapshot untuk T

Output minimum:
- bangun 1 row per ticker coverage universe untuk T
- set `eligible` dan `reason_code`

### 4) `market-data:audit:hash`
Input minimum:
- `requested_date=T`
- candidate effective date `D`

Output minimum:
- hitung hash untuk bars/indicators/eligibility berdasarkan format dan ordering yang terkunci
- hanya atas artifact consumer-visible milik requested context yang sedang diproses
- provenance field tidak ikut hash content

### 5) `market-data:dataset:seal`
Input minimum:
- command pelengkap tidak boleh mengubah sealed dataset tanpa controlled correction flow