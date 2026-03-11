# Reason Codes Registry (LOCKED)

## Purpose
Define the canonical reason-code vocabulary used by Market Data Platform across:
- `eod_invalid_bars.invalid_reason_code`
- `eod_indicators.invalid_reason_code`
- `eod_eligibility.reason_code`
- `eod_run_events.reason_code`

This registry is intentionally upstream-only. It does not encode watchlist scores, groups, picks, or strategy actions.

## Registry rules (LOCKED)
1. Codes are stable identifiers and must be uppercase snake case.
2. One code has one meaning only.
3. Description text may be clarified over time, but code semantics must not drift silently.
4. Deprecated codes must not be physically reused for a different meaning.
5. Severity is the default registry severity; actual run outcome is still decided by the locked decision table.

## Canonical registry
| code | category | severity | description |
|---|---|---:|---|
| `RUN_COVERAGE_LOW` | RUN | HARD | Rasio cakupan untuk requested date berada di bawah ambang minimum yang dikunci. |
| `RUN_INDICATORS_MISSING` | RUN | HARD | Artefak indikator wajib atau himpunan row indikator untuk requested date tidak tersedia. |
| `RUN_ELIGIBILITY_MISSING` | RUN | HARD | Snapshot eligibility untuk requested date tidak tersedia. |
| `RUN_HASH_MISSING` | RUN | HARD | Satu atau lebih content hash wajib tidak tersedia pada saat finalisasi. |
| `RUN_HASH_FAILED` | RUN | HARD | Proses perhitungan hash gagal atau menghasilkan output yang tidak dapat digunakan. |
| `RUN_SEAL_PRECONDITION_FAILED` | RUN | HARD | Proses seal dijalankan sebelum seluruh prasyarat yang dikunci terpenuhi. |
| `RUN_SEAL_WRITE_FAILED` | RUN | HARD | Metadata seal gagal ditulis dengan sukses. |
| `RUN_FINALIZE_BEFORE_CUTOFF` | RUN | HARD | Final success dicoba sebelum kebijakan cutoff mengizinkannya. |
| `RUN_LOCK_CONFLICT` | RUN | HARD | Terjadi konflik kepemilikan proses atau duplicate writer pada tahap hash, seal, atau finalize. |
| `RUN_SOURCE_TIMEOUT` | RUN | WARN | Source mengalami timeout dan kebijakan retry sudah dijalankan atau habis. |
| `RUN_SOURCE_RATE_LIMIT` | RUN | WARN | Source terkena pembatasan rate limit dan memengaruhi proses akuisisi data. |
| `RUN_SOURCE_AUTH_ERROR` | RUN | HARD | Kegagalan autentikasi source atau kesalahan credential/config menghambat akuisisi data. |
| `RUN_SOURCE_RESPONSE_CHANGED` | RUN | HARD | Terdeteksi perubahan schema atau kontrak response dari source. |
| `RUN_SOURCE_PARTIAL_COVERAGE` | RUN | WARN | Source mengembalikan cakupan simbol yang tidak lengkap untuk requested date. |
| `RUN_SOURCE_MALFORMED_PAYLOAD` | RUN | HARD | Payload dari source tidak dapat dinormalisasi dengan aman. |
| `BAR_DUPLICATE_SOURCE_ROW` | BAR | WARN | Terdapat lebih dari satu row source yang memetakan ke `(trade_date, ticker_id)` yang sama sehingga perlu pemilihan winner secara deterministik. |
| `BAR_INVALID_OHLC_ORDER` | BAR | HARD | Nilai OHLC yang diterima melanggar aturan urutan canonical. |
| `BAR_NON_POSITIVE_PRICE` | BAR | HARD | Nilai harga yang diterima bernilai nol atau negatif pada field yang seharusnya positif. |
| `BAR_NEGATIVE_VOLUME` | BAR | HARD | Nilai volume yang diterima bernilai negatif. |
| `BAR_MISSING_REQUIRED_FIELD` | BAR | HARD | Satu atau lebih field source yang wajib tidak tersedia. |
| `IND_INSUFFICIENT_HISTORY` | INDICATOR | WARN | Riwayat trading-day yang dibutuhkan belum cukup untuk perhitungan indikator secara deterministik. |
| `IND_MISSING_DEPENDENCY_BAR` | INDICATOR | HARD | Canonical bar yang dibutuhkan dalam rantai trading-day tidak tersedia. |
| `IND_INVALID_BAR_INPUT` | INDICATOR | HARD | Input canonical bar yang digunakan untuk menghitung indikator tidak valid. |
| `IND_COMPUTE_ERROR` | INDICATOR | HARD | Perhitungan indikator gagal karena kesalahan logika atau runtime. |
| `ELIG_MISSING_BAR` | ELIGIBILITY | WARN | Ticker yang termasuk coverage universe tidak memiliki canonical valid bar untuk requested date. |
| `ELIG_MISSING_INDICATORS` | ELIGIBILITY | HARD | Eligibility tidak dapat ditentukan karena indikator wajib tidak tersedia. |
| `ELIG_INVALID_INDICATORS` | ELIGIBILITY | WARN | Row indikator tersedia tetapi status indikator wajib ditandai tidak valid. |
| `ELIG_INSUFFICIENT_HISTORY` | ELIGIBILITY | WARN | Eligibility ditolak karena riwayat yang dibutuhkan untuk indikator wajib belum mencukupi. |
| `ELIG_UNIVERSE_DEPENDENCY_MISSING` | ELIGIBILITY | HARD | Dependency upstream yang dibutuhkan untuk membentuk membership universe tidak tersedia. |
| `ELIG_FETCH_FAILURE` | ELIGIBILITY | WARN | Eligibility ditolak karena akuisisi source pada level ticker gagal sehingga artefak upstream yang dibutuhkan tidak dapat dibentuk dengan aman. |
| `SNAP_SOURCE_TIMEOUT` | INTRADAY | WARN | Source untuk session snapshot mengalami timeout. |
| `SNAP_SOURCE_RATE_LIMIT` | INTRADAY | WARN | Source untuk session snapshot terkena rate limit. |
| `SNAP_PARTIAL_SCOPE` | INTRADAY | WARN | Session snapshot hanya berhasil menangkap sebagian scope yang direncanakan. |
| `SNAP_SOURCE_ERROR` | INTRADAY | WARN | Source untuk session snapshot gagal karena alasan operasional yang tidak memblokir EOD. |

## Locked usage notes
- `ELIG_MISSING_BAR` and `ELIG_INSUFFICIENT_HISTORY` may coexist as different row outcomes on different dates/tickers, but one row stores only the single most specific blocking reason.
- `RUN_SOURCE_TIMEOUT` and `RUN_SOURCE_RATE_LIMIT` do not automatically force `FAILED`; terminal status still follows the decision table and gate results.
- `RUN_HASH_MISSING`, `RUN_HASH_FAILED`, `RUN_SEAL_PRECONDITION_FAILED`, and `RUN_SEAL_WRITE_FAILED` are always incompatible with final `SUCCESS`.
- Session snapshot reason codes must never be used to justify fallback of sealed EOD datasets.