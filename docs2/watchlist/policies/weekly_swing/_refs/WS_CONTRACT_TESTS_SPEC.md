# WS Contract Tests Spec (LOCKED)

Dokumen ini mendefinisikan contract tests yang WAJIB dipenuhi oleh implementasi policy WEEKLY_SWING (WS).
Tidak ada interpretasi bebas. Jika hasil berbeda dari spesifikasi ini, implementasi dianggap salah.

## 0. Terminologi (LOCKED)
- PLAN: output watchlist deterministik dari data EOD as-of date tertentu untuk trade_date berikutnya.
- CONFIRM: overlay runtime berbasis snapshot intraday yang TIDAK BOLEH mengubah PLAN.
- Paramset: konfigurasi policy WS sesuai contract `03_WS_PARAMSET_JSON_CONTRACT.md`.
- Plan Hash: hash deterministik PLAN sesuai `07_WS_REASON_CODES_AND_HASH.md`.

## 1) Test: PLAN Determinism (LOCKED)
### Tujuan
PLAN yang sama (input EOD sama + paramset sama) harus menghasilkan output identik byte-to-byte (atau identik secara canonical JSON).

### Input
- EOD dataset as-of date D (fixture A).
- Paramset WS (fixture A paramset).

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
- `plan_output` dari fixture A.
- snapshot runtime (fixture A confirm snapshot).

### Prosedur
1. Ambil `plan_hash_before` dari `plan_output`.
2. Jalankan CONFIRM overlay terhadap plan → hasilkan `confirm_output`.
3. Ambil ulang plan yang sama (atau re-read) → dapatkan `plan_output_after` dan `plan_hash_after`.

### Assert (semua wajib)
- `plan_hash_before == plan_hash_after`
- `plan_output_after.items[]` identik dengan `plan_output.items[]` untuk field LOCKED (lihat Test #1).
- `confirm_output` adalah output terpisah (tidak menulis balik ke plan item).

## 3) Test: Paramset Validator Coverage (LOCKED)
### Tujuan
Semua parameter yang dinyatakan `in_10=Y` di `WS_PARAMETER_COVERAGE_MATRIX.md` harus divalidasi oleh validator spec `06_WS_PARAMSET_VALIDATOR_SPEC.md`.

### Prosedur
1. Parse tabel `WS_PARAMETER_COVERAGE_MATRIX.md`.
2. Ambil semua `param_key` dengan `in_10=Y`.
3. Untuk setiap `param_key`, pastikan ada aturan validasi eksplisit di `06`:
   - required/optional status
   - type (int/float/bool/list)
   - range/domain (mis. 0..1, >0, etc.)
   - relasi antar parameter bila ada (mis. min <= max)

### Assert
- Tidak ada `param_key` `in_10=Y` yang tidak disebut di validator spec.

## 4) Test: Hash Contract Vectors (LOCKED)
### Tujuan
Implementasi hash canonical harus menghasilkan SHA-256 yang sama persis dengan test vector di `07_WS_REASON_CODES_AND_HASH.md`.

### Input
- Test Vector A (dari dok 07)
- Test Vector B (dari dok 07)

### Assert
- `sha256(canonical_string_A) == expected_hash_A`
- `sha256(canonical_string_B) == expected_hash_B`
- aturan canonical:
  - rounding sesuai `hash_contract.scales`
  - NULL handling sesuai `hash_contract.null_handling`
  - negative zero normalization sesuai dok 07
  - order_by sesuai `hash_contract.order_by`

## 5) Test: Golden Fixtures E2E (LOCKED)
Lihat `WS_GOLDEN_FIXTURES.md` untuk definisi fixture A/B/C dan expected outputs.
Assert: output PLAN dan CONFIRM harus match expected.