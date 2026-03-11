# Commands and Runbook (Market Data Platform, LOCKED)

## Tujuan
Dokumen ini mengunci command minimum agar platform dapat memproduksi data pasar upstream yang canonical, tervalidasi, deterministik, auditable, dan siap dikonsumsi downstream.

Dokumen ini **tidak** membahas screening, scoring, grouping, ranking, atau logika kebijakan downstream.

## Command minimum (WAJIB)
### 1) `market-data:eod-bars:ingest`
- ambil provider EOD bars untuk requested date T
- mapping + canonicalization
- valid bars -> `eod_bars`
- invalid bars -> `eod_invalid_bars`
- update telemetry `eod_runs`

### 2) `market-data:eod-indicators:compute`
- hitung indikator dari `eod_bars`
- wajib menyertakan `indicator_set_version`
- update `eod_indicators` + telemetry

### 3) `market-data:eod-eligibility:build`
- bangun 1 row per ticker coverage universe untuk T
- set `eligible` dan `reason_code`

### 4) `market-data:audit:hash`
- hitung hash untuk bars/indicators/eligibility berdasarkan format dan ordering yang terkunci
- hanya atas artifact consumer-visible milik `run_id` yang sedang diproses

### 5) `market-data:dataset:seal`
- tulis metadata seal untuk date yang lolos preconditions
- tanpa seal, dataset dianggap belum siap dibaca

### 6) `market-data:run:finalize`
- evaluasi gates terakhir
- tetapkan status final run
- resolve `trade_date_effective`
- dilarang menghasilkan `SUCCESS` bila hash atau seal belum ada

## Command pelengkap yang direkomendasikan
### 7) `market-data:daily`
Menjalankan urutan harian: ingest -> compute -> eligibility -> hash -> seal -> finalize

### 8) `market-data:backfill`
Backfill/recompute per range trading-day dengan mode `bars|indicators|eligibility|all`

### 9) `market-data:intraday:snapshot`
Ambil snapshot intraday best-effort untuk D tanpa memodifikasi artifact EOD.

### 10) `market-data:intraday:purge`
Purge intraday berdasarkan TTL.

## Operator flow harian (LOCKED)
1) jalankan `market-data:daily --latest`
2) verifikasi `eod_runs` final
3) verifikasi hash non-null
4) verifikasi `sealed_at` non-null
5) bila `HELD/FAILED`, downstream wajib fallback ke prior sealed effective date

## Locking and ownership rule (LOCKED)
- satu requested date hanya boleh punya satu writer aktif untuk pipeline harian
- hash/seal/finalize harus dieksekusi dalam kepemilikan run yang sama
- command pelengkap tidak boleh mengubah sealed dataset tanpa controlled correction flow