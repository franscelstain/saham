# WS Contract Tests Spec (LOCKED)

## Purpose
Spesifikasi contract tests referensi yang harus dipenuhi implementasi Weekly Swing.

## Scope
Menjabarkan tujuan, input, prosedur, dan assert minimum per test kontrak.

## Inputs
- Engineer test suite, reviewer release, dan auditor kualitas implementasi.

## Outputs
- Daftar referensi contract tests yang harus diterjemahkan menjadi test nyata.

Dokumen ini mendefinisikan contract tests yang WAJIB dipenuhi oleh implementasi policy WEEKLY_SWING (WS).
Tidak ada interpretasi bebas. Jika hasil berbeda dari spesifikasi ini, implementasi dianggap salah.

## 0. Terminologi (LOCKED)
- PLAN: output watchlist deterministik dari data EOD as-of date tertentu untuk trade_date berikutnya.
- CONFIRM: overlay runtime berbasis snapshot intraday yang TIDAK BOLEH mengubah PLAN.
- Paramset: konfigurasi policy WS sesuai contract `../04_WS_PARAMSET_JSON_CONTRACT.md`.
- Plan Hash: hash deterministik PLAN sesuai [`WS_RUNTIME_OUTPUT_SCHEMA.md`](WS_RUNTIME_OUTPUT_SCHEMA.md) bagian `3.2.2 Canonicalization for meta.plan_hash`.

## 1) Test: PLAN Determinism (LOCKED)
### Tujuan
PLAN yang sama (input EOD sama + paramset sama) harus menghasilkan output identik byte-to-byte (atau identik secara canonical JSON).

### Input
- EOD dataset as-of date D dari fixture `../fixtures/PLAN_FIXTURE_A_TIES_V1.json`.
- Paramset WS dari fixture `../fixtures/paramset_valid.json`.

### Prosedur
1. Jalankan PLAN (run 1) → simpan `plan_output_1` dan `plan_hash_1`.
2. Jalankan PLAN (run 2) pada input yang sama → simpan `plan_output_2` dan `plan_hash_2`.

### Assert (semua wajib)
- `plan_hash_1 == plan_hash_2`
- `plan_output_1` identik dengan `plan_output_2` pada field LOCKED:
  - `meta.asof_eod_date`
  - `meta.trade_date`
  - `meta.policy`
  - `meta.paramset_id` (atau `paramset_version`)
  - `meta.plan_hash`
  - setiap item: `ticker`, `score_total`, `group_semantic`, `rank`,
    `levels.entry_ref`, `levels.entry_band_low`, `levels.entry_band_high`,
    `levels.stop_price`, `levels.tp1_price`,
    `reasons[]` (code + message + severity + payload canonical)

## 2) Test: PLAN Immutability Under CONFIRM (LOCKED)
### Tujuan
CONFIRM tidak boleh mengubah PLAN, termasuk ranking, skor, grouping, dan level harga.

### Input
- `plan_output` dari fixture `../fixtures/confirm_immutability_pair.json`.
- snapshot runtime dari fixture yang sama (`confirm_input`).

### Prosedur
1. Ambil `plan_hash_before` dari `plan_output`.
2. Jalankan CONFIRM overlay terhadap plan → hasilkan `confirm_output`.
3. Ambil ulang plan yang sama (atau re-read) → dapatkan `plan_output_after` dan `plan_hash_after`.

### Assert (semua wajib)
- `plan_hash_before == plan_hash_after`
- `plan_output_after.items[]` identik dengan `plan_output.items[]` untuk field LOCKED (lihat Test #1).
- `confirm_output` adalah output terpisah (tidak menulis balik ke plan item).

## 2B) Test: CONFIRM Rule Contracts (LOCKED)
### Tujuan
CONFIRM harus menghasilkan `label` dan `reason codes` yang tepat untuk kondisi runtime yang sudah didefinisikan di `../10_WS_CONFIRM_OVERLAY.md`.

### Input (fixtures)
- `plan_output` dari fixture `../fixtures/confirm_immutability_pair.json` (minimal 1 item dengan `levels.entry_ref` valid).
- Snapshot fixture yang dapat diatur dari `../fixtures/confirm_snapshots_two.json` atau `../fixtures/confirm_payload_with_orderbook_fields.json`.

### Cases & Assert (semua wajib, per-case)
1) **Snapshot stale**
- Setup: `checked_at - effective_captured_at > snapshot_max_age_sec`
- Assert: `label = DELAY` dan ada reason code `WS_STALE`

2) **Snapshot missing**
- Setup: tidak ada snapshot untuk `(policy='WS', trade_date=T)`
- Assert: `label = DELAY` dan ada reason code `WS_SNAPSHOT_MISSING`

3) **No price**
- Setup: snapshot ada dan valid TTL, tetapi `last_price` tidak tersedia
- Assert: `label = DELAY` dan ada reason code `WS_NO_PRICE`

4) **Spread wide**
- Setup: snapshot valid, `last_price` tersedia
- Assert: jika `turnover_idr` atau `volume_shares` missing → `label = DELAY` dan ada reason code `WS_INPUT_INCOMPLETE` (LOCKED)

5) **Drift far**
- Setup: snapshot valid, `last_price` tersedia, `abs(last_price - entry_ref)/entry_ref > max_drift_from_entry_pct`
- Assert: `label = CAUTION` dan ada reason code `WS_DRIFT_FAR`

6) **Bid/ask not available**
- Setup: snapshot valid, `last_price` tersedia
- Assert: field non-contract (contoh: `bid1_price`, `ask1_price`, `spread`, `orderbook_json`) tidak mengubah hasil CONFIRM (LOCKED ignore)

### Notes (LOCKED)
- Test ini **tidak** boleh mengubah PLAN (tetap wajib lulus Test #2).
- Assert reason codes memeriksa **keberadaan** code wajib; urutan reasons tidak mengikat kecuali jika kontrak output menyatakan sebaliknya.

## 2C) Test: DB Write-Scope Audit (LOCKED)
### Tujuan
Membuktikan CONFIRM **tidak** melakukan writeback ke persistence PLAN (read-only terhadap PLAN, write-only ke CONFIRM), sesuai kontrak `../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`.

### Precondition
- PLAN sudah tersedia untuk `trade_date=T` (persistence run/items/levels/reasons ada).

### Definisi fingerprint (LOCKED)
`PLAN_FINGERPRINT(T)` wajib mencakup:
- `plan_hash(T)` (lihat Test #2, sudah ada)
- Untuk setiap tabel persistence PLAN (`plan_run`, `plan_items`, `plan_levels`, `plan_reasons`):
  - `row_count(T)` = COUNT(*) untuk `trade_date=T` / `plan_run_id` terkait
  - `max_updated_at(T)` = MAX(updated_at) jika kolom ada (jika tidak ada, field ini di-skip)

### Prosedur audit (LOCKED)
1) Ambil `F_before = PLAN_FINGERPRINT(T)`  
2) Jalankan CONFIRM overlay untuk `trade_date=T`  
3) Ambil `F_after = PLAN_FINGERPRINT(T)`  
4) Bandingkan `F_before` vs `F_after`

### Assert (semua wajib)
- `plan_hash_before == plan_hash_after`
- Untuk setiap tabel persistence PLAN: `row_count_before == row_count_after`
- Untuk setiap tabel persistence PLAN yang punya `updated_at`: `max_updated_at_before == max_updated_at_after`

### Failure (LOCKED)
- Jika ada perbedaan fingerprint ⇒ fail test / reject release dengan reason code `WS_PLAN_WRITEBACK_DETECTED`

## 3) Test: Paramset Validator Coverage (LOCKED)
### Tujuan
Semua parameter yang dinyatakan `in_10=Y` di [`WS_PARAMETER_COVERAGE_MATRIX.md`](WS_PARAMETER_COVERAGE_MATRIX.md) harus divalidasi oleh validator spec `../06_WS_PARAMSET_VALIDATOR_SPEC.md`.

### Prosedur
1. Parse tabel [`WS_PARAMETER_COVERAGE_MATRIX.md`](WS_PARAMETER_COVERAGE_MATRIX.md).
2. Ambil semua `param_key` dengan `in_10=Y`.
3. Untuk setiap `param_key`, pastikan ada aturan validasi eksplisit di `../06_WS_PARAMSET_VALIDATOR_SPEC.md`:
   - required/optional status
   - type (int/float/bool/list)
   - range/domain (bentuk yang diizinkan harus eksplisit), salah satu:
     - interval numerik: `min..max` (inklusif) atau `min<..<max` (eksklusif bila ditulis eksplisit)
     - batas satu sisi: `>x`, `>=x`, `<x`, `<=x`
     - enum set: `{A,B,C}` atau daftar nilai eksplisit
     - boolean: `{true,false}`
   - relasi antar parameter bila ada (mis. min <= max)

### Assert
- Tidak ada `param_key` `in_10=Y` yang tidak disebut di validator spec.

## 4) Test: Hash Contract Vectors (LOCKED)
### Tujuan
Implementasi hash canonical harus menghasilkan SHA-256 yang sama persis dengan test vector di `../07_WS_REASON_CODES_AND_HASH.md`.

### Input
- Test Vector A (lihat `../07_WS_REASON_CODES_AND_HASH.md`)
- Test Vector B (lihat `../07_WS_REASON_CODES_AND_HASH.md`)

### Assert
- `sha256(canonical_string_A) == expected_hash_A`
- `sha256(canonical_string_B) == expected_hash_B`
- aturan canonical:
  - rounding sesuai `hash_contract.scales`
  - NULL handling sesuai `hash_contract.null_handling`
  - negative zero normalization sesuai `../07_WS_REASON_CODES_AND_HASH.md`
  - order_by sesuai `hash_contract.order_by`

## 5) Test: Golden Fixtures E2E (LOCKED)
Lihat [`WS_GOLDEN_FIXTURES.md`](WS_GOLDEN_FIXTURES.md) untuk inventory fixture resmi dan tujuan masing-masing.
Assert: output PLAN dan CONFIRM harus match expected pada fixture yang relevan.