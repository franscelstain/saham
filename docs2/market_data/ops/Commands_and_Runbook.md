# Commands and Runbook (Market Data Platform)

## Tujuan
Dokumen ini mengunci **command minimal** yang wajib ada agar Market Data Platform bisa:
- menyiapkan EOD bars yang canonical
- menghitung indikator EOD yang reproducible
- menerapkan quality gate + menentukan effective trade date
- membangun eligibility snapshot untuk consumer (Watchlist/policy lain)
- menghasilkan audit hash + **seal** dataset agar input Watchlist PLAN tidak berubah diam-diam
- menjalankan intraday snapshot (best-effort) untuk consumer CONFIRM

Dokumen ini tidak membahas scoring/grouping watchlist.

---

## Command Minimal (WAJIB)

### 1) `market-data:eod-bars:ingest`
**Tujuan**
- Ambil EOD OHLCV dari provider via API untuk trade_date T
- Normalisasi + validasi bar
- Upsert ke `eod_bars`
- Catat telemetry tahap INGEST/PUBLISH di `eod_runs`

**Input wajib**
- `--trade-date=YYYY-MM-DD` atau `--latest`

**Output wajib**
- `eod_bars` terisi untuk T (termasuk bar invalid jika kebijakan menyimpan invalid bars)
- `eod_runs` update stage minimal `INGEST_BARS` lalu `PUBLISH_BARS`
- hitung: bars_rows_written, invalid_bar_count, coverage_ratio (atau minimal data untuk menghitungnya)

**Aturan LOCKED**
- Idempotent: rerun untuk T tidak boleh membuat duplikasi PK.

---

### 2) `market-data:eod-indicators:compute`
**Tujuan**
- Hitung indikator EOD untuk trade_date T (atau range) dari `eod_bars`
- Upsert ke `eod_indicators`
- Set `indicator_set_version` dan flag valid/invalid

**Input wajib**
- `--trade-date=YYYY-MM-DD` atau `--range=YYYY-MM-DD..YYYY-MM-DD`
- `--indicator-set-version=...` (wajib untuk run yang mempengaruhi output)

**Output wajib**
- `eod_indicators` terisi untuk T (atau range)
- `eod_runs` update stage `COMPUTE_INDICATORS`
- hitung: indicators_rows_written, invalid_indicator_count

**Aturan LOCKED**
- Formula mengikuti `indicators/Indicator_Computation_Specification.md`
- Window trading-day (market calendar), bukan kalender biasa.

---

### 3) `market-data:eod-eligibility:build`
**Tujuan**
- Bangun snapshot eligibility untuk effective trade date D
- Menentukan eligible=1/0 + reason_code secara upstream

**Input wajib**
- `--trade-date=YYYY-MM-DD` atau `--latest`

**Output wajib**
- `eod_eligibility` terisi untuk D (1 row per ticker dalam coverage universe)
- `eod_runs` update stage `BUILD_ELIGIBILITY`

**Aturan LOCKED**
- Eligibility mengikuti `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- Partial data harus mengikuti `book/Eligibility_Partial_Data_Behavior_LOCKED.md`

---

### 4) `market-data:run:finalize`
**Tujuan**
- Terapkan quality gates
- Tetapkan `status` (SUCCESS/HELD/FAILED)
- Tetapkan `trade_date_effective`
- Pastikan dataset sudah lengkap untuk consumer

**Input wajib**
- `--trade-date=YYYY-MM-DD` atau `--latest`

**Output wajib**
- `eod_runs` status final + trade_date_effective terisi
- Jika status HELD/FAILED: effective date harus fallback sesuai kontrak

**Aturan LOCKED**
- Gate mengikuti `book/Run_Status_and_Quality_Gates_LOCKED.md`
- Effective date mengikuti `book/Effective_Trade_Date_Contract_LOCKED.md`

---

### 5) `market-data:audit:hash`
**Tujuan**
- Hitung dan simpan hash untuk dataset effective date D:
  - bars_batch_hash
  - indicators_batch_hash
  - eligibility_batch_hash

**Input wajib**
- `--trade-date=YYYY-MM-DD` atau `--latest`

**Output wajib**
- Kolom hash di `eod_runs` terisi untuk run yang final

**Aturan LOCKED**
- Hash mengikuti `book/Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- Formatting angka mengikuti `book/Hash_Number_Formatting_LOCKED.md`

---

### 6) `market-data:dataset:seal`
**Tujuan**
- Menandai dataset untuk effective date D sebagai **SEALED**
- Consumer (Watchlist PLAN) hanya boleh membaca dataset yang SEALED

**Input wajib**
- `--trade-date=YYYY-MM-DD` atau `--latest`

**Output wajib**
- Seal marker tersimpan (pilih salah satu cara implementasi):
  - field di `eod_runs` (mis. sealed_at, sealed_by), atau
  - table seal terpisah

**Aturan LOCKED**
- Seal mengikuti `book/Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- Watchlist consumer read model menganggap unsealed = not ready:
  - `book/Watchlist_Consumer_Read_Model_Contract_LOCKED.md`

---

## Convenience Commands (disarankan, tapi bukan pengganti kontrak)

### 7) `market-data:daily`
**Tujuan**
Menjalankan pipeline harian end-to-end untuk requested date T (default latest):
1) eod-bars:ingest  
2) eod-indicators:compute  
3) eod-eligibility:build  
4) run:finalize  
5) audit:hash  
6) dataset:seal

**Aturan LOCKED**
- Harus fail-safe: jika salah satu step gagal, status run harus mencerminkan HELD/FAILED dan consumer fallback berlaku.

---

### 8) `market-data:backfill`
**Tujuan**
Backfill/recompute untuk range trading-day.
Mode:
- `--mode=bars|indicators|eligibility|all` (default all)

**Input**
- `--range=YYYY-MM-DD..YYYY-MM-DD`
- `--indicator-set-version=...` (wajib bila mode mencakup indicators)

**Aturan LOCKED**
- Resumable mengikuti `ops/Resumable_Backfill_Contract_LOCKED.md`
- Perubahan output harus auditable (run_id + hashes).

---

## Intraday Commands (best-effort input untuk CONFIRM)

### 9) `market-data:intraday:snapshot`
**Tujuan**
Ambil intraday snapshot pada slot tertentu (OPEN/MIDDAY/PRE_CLOSE) untuk trade_date_effective D.

**Input**
- `--slot=OPEN_CHECK|MIDDAY_CHECK|PRE_CLOSE_CHECK`
- `--trade-date=YYYY-MM-DD` atau `--latest`

**Scope (LOCKED)**
Default: eligibility-set snapshot (bukan picks-only) sampai Watchlist menyediakan picks list interface:
- `intraday/Intraday_Scope_Selection_and_Dependencies_LOCKED.md`

**Alignment (LOCKED)**
- `trade_date` intraday = trade_date_effective (bukan calendar date):
  - `intraday/Intraday_Date_Alignment_with_Effective_Date_LOCKED.md`

---

### 10) `market-data:intraday:purge`
**Tujuan**
Purge intraday snapshot berdasarkan TTL default (30 hari).

**Aturan LOCKED**
- `intraday/Intraday_Retention_Defaults_LOCKED.md`

---

## Validation Commands (disarankan)

### 11) `market-data:eod-bars:validate`
- Scan bar validity + duplicate PK + coverage
- Tidak mengubah data (read-only)

### 12) `market-data:eod-indicators:validate`
- Spot-check formula, null-policy, version consistency
- Tidak mengubah data (read-only)

---

## Operator Flow Harian (ringkas)
1) Jalankan `market-data:daily --latest`
2) Pastikan `eod_runs` untuk requested date:
   - status SUCCESS/HELD/FAILED sudah final
   - trade_date_effective terisi
   - hashes terisi
   - dataset SEALED

Jika HELD/FAILED:
- consumer wajib fallback ke last-good effective date
- ikuti `ops/Failure_Playbook.md`

---

## Catatan penting untuk Watchlist
Watchlist harus membaca data dengan pola resmi:
- `book/Watchlist_Consumer_Read_Model_Contract_LOCKED.md`
Dan hanya menggunakan dataset yang SEALED:
- `book/Dataset_Seal_and_Freeze_Contract_LOCKED.md`