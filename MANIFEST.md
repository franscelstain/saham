# MANIFEST

- base_build: trade-axis_v14.zip
- patch_build: trade-axis_v14_patched_r22
- patch_date: 2026-02-03

## Patch r17 — Dokumen anti salah tafsir (watchlist.md + policy)

Perkuat wording agar implementasi aplikasi tidak nyasar:

- Perjelas definisi `asof_eod_date` vs `exec_trade_date` vs `trade_date` (alias) agar tidak kebalik.
- Kunci semantik hasil evaluasi: **EXCLUDE (Universe DROP)** vs **NOT_QUALIFIED (watch_only)** vs **AVOID** vs **PLAN_INVALID**.
- Selaraskan semua policy docs agar tidak memakai kata “DROP/gugur” untuk hard rules biasa; hard rules fail = **NOT_QUALIFIED** (watch_only).

File berubah:
- `docs/watchlist/watchlist.md`
- `docs/watchlist/policy/*.md`

## Patch r19 — Sentralisasi PLAN_INVALID reason codes

Rapihin code agar klasifikasi `PLAN_INVALID` tidak tersebar dan tidak rawan drift:

- Tambah helper `PlanInvalidClassifier` sebagai satu-satunya tempat menentukan apakah `reason_code` termasuk `PLAN_INVALID`.
- `WatchlistEngine` sekarang cukup memanggil `PlanInvalidClassifier::any($reasonCodes)`.

File berubah:
- `app/Trade/Watchlist/PlanInvalidClassifier.php`
- `app/Trade/Watchlist/WatchlistEngine.php`

## Patch r16 — Kandidat hard-rule fail tetap tampil + turnover20 fallback

Perbaikan untuk konsisten dengan `docs/watchlist/watchlist.md`:

- Hard-rule fail dari policy **tidak lagi menghapus** ticker dari output PLAN. Ticker tetap muncul
  (biasanya di `watch_only` jika skor memenuhi threshold), tetapi **tidak eligible** untuk new entry
  (`plan.is_eligible_new_entry=false`) dan memiliki `plan.block_codes[]`.
- Perbaikan bug query: `turnover20_idr` sebelumnya ketiban alias `NULL as turnover20_idr` sehingga
  fallback likuiditas tidak pernah aktif. Sekarang `turnover20_idr` benar-benar terisi.

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`
- `app/Repositories/WatchlistRepository.php`

## Patch r1 — Recommendations weighting (LOCKED)

Selaraskan perhitungan `weight_pct` recommendations sesuai `docs/watchlist/watchlist.md#weighting-for-recommendations-locked`:

- `weight_pct` diturunkan dari `score_total` kandidat (setelah cutoff pool), lalu di-`clamp` dengan default:
  - `W_MIN = 0.10`
  - `W_MAX = 0.60`
- Setelah clamp, bobot dinormalisasi sehingga total = 1.0.

Implementasi ini menggantikan bobot fixed `[0.6, 0.4] / [0.5, 0.3, 0.2]` agar output deterministik dan sesuai dokumen.

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`

## Patch r2 — No-overspend guard (LOCKED)

Tegakkan aturan `estimated_cost <= remaining_capital` untuk setiap allocation recommendations:

- Budget per ticker tetap berbasis `capital_total * weight_pct`, tapi **dibatasi** oleh `remaining_cash`.
- Lots yang direkomendasikan dihitung dari budget, lalu dikurangi (binary search) sampai **total biaya beli**
  (gross + fee + slippage) tidak melebihi `remaining_cash`.
- `remaining_cash` kini selalu turun secara valid (`remaining - estimated_cost`), tanpa `max(0, ...)` yang menutupi overspend.

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`

## Patch r3 — Backfill recommendations jika ticker tidak feasible (LOCKED)

Selaraskan perilaku recommendations saat ticker Top Picks tidak feasible untuk min 1 lot atau gagal affordability:

- Build `poolIdx` dari Top Picks yang lolos filter PLAN (hard lock / eligibility / trade_viability).
- Pilih `target` ticker pertama sebagai selection awal.
- Jalankan allocation pass (weighting sudah LOCKED via r1, overspend guard via r2).
- Jika ada ticker di selection yang gagal membuat allocation (min lots / insufficient cash), ticker tersebut **dikeluarkan** lalu slotnya **diisi** oleh kandidat ranking berikutnya dari `poolIdx`.
- Setelah drop/backfill, bobot `weight_pct` **di-recompute dan di-renormalize** (sesuai dokumen).
- Hasil akhir adalah best-effort: berhenti jika pool habis atau cash habis.

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`

## Patch r4 — Isi `cash_remaining` top-level recommendations (LOCKED)

Selaraskan payload recommendations agar `cash_remaining_idr` di output preopen tidak `null` saat canonical ready dan capital tersedia:

- Tambahkan field top-level `cash_remaining` pada struktur `plan.recommendations` yang dibangun oleh engine.
- Nilai `cash_remaining` diambil dari `remaining_cash` allocation terakhir; jika tidak ada allocation, sama dengan `capital_total`.
- Semua return path di `buildRecommendations()` kini mengisi `cash_remaining` secara eksplisit agar mapping contract konsisten.

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`

## Patch r5 — Gunakan `plan_price_cap` sebagai referensi biaya (LOCKED)

Selaraskan perhitungan affordability agar menggunakan harga referensi yang realistis untuk breakout:

- `refPrice` untuk sizing/recommendations memakai `plan_price_cap` jika tersedia, fallback ke `entry_trigger_price`.
- Ini mencegah rekomendasi lot yang terlihat mampu di entry tetapi gagal saat chase (breakout) karena limit cap.

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`

## Patch r6 — Update fixtures + tambah tes allocator (LOCKED)

Mengunci behavior terbaru agar tidak regress:

- Tambah fixture preopen dengan recommendations berisi item (`preopen_weekly_swing_with_capital.json`).
- Update `PreopenContractTest` agar memvalidasi fixture tambahan.
- Update `WatchlistLotSizingRegressionTest` agar sesuai aturan no-overspend (remaining_cash turun deterministik tanpa `max(0, ...)`).
- Tambah test baru `WatchlistRecommendationsAllocatorTest` untuk mengunci:
  - clamp + renormalize weight
  - guard tidak overspend dan remaining monotonic
  - backfill ticker infeasible
  - determinism untuk input yang sama
- Perbaiki `WatchlistPreopenBuildRegressionTest` agar tetap robust terhadap perubahan dependency injection (tambahan `ScorecardConfig` dan confirm guards).

File berubah/ditambah:
- `tests/Unit/Contracts/PreopenContractTest.php`
- `tests/Fixtures/watchlist/preopen_weekly_swing_with_capital.json`
- `tests/Unit/Regression/WatchlistLotSizingRegressionTest.php`
- `tests/Unit/Watchlist/WatchlistRecommendationsAllocatorTest.php`
- `tests/Unit/Watchlist/WatchlistPreopenBuildRegressionTest.php`

## Patch r7 — Living docs: schema & strategi (opsional tapi direkomendasikan)

Selaraskan dokumentasi agar anti salah tafsir dan mudah dipakai saat operasional:

- Tambah banner **STATUS (LIVING DOCS)** pada `docs/watchlist/schema.md` dan `docs/watchlist/strategi.md`.
- `schema.md` diperkuat dengan catatan **sumber pengisian data** per tabel:
  - mana yang diisi otomatis oleh endpoint preopen,
  - mana yang diisi oleh command scorecard,
  - mana yang manual/eksternal (snapshot & dividend events).
- `strategi.md` dipertegas sebagai **panduan penggunaan** (runbook mini) dan menambahkan daftar aktivitas ringkas.

File berubah:
- `docs/watchlist/schema.md`
- `docs/watchlist/strategi.md`

## Patch r8 — Fix WatchlistRepository calendar + ticker active flags (FATAL)

Memperbaiki error keras yang menyebabkan watchlist gagal query sebelum masuk ke scoring/plan:

- `WatchlistRepository` sebelumnya query tabel `market_calendars` dan kolom `trade_date` (tidak ada pada migration).
  Sekarang menggunakan `MarketCalendarRepository` sebagai sumber trading dates (tabel `market_calendar`, kolom `cal_date`).
- Filter ticker diperbaiki dari `t.is_active = 1` (kolom tidak ada) menjadi `t.is_deleted = 0` sesuai migration.

Dokumentasi diselaraskan:
- `docs/watchlist/schema.md` dan `docs/watchlist/strategi.md` mengganti referensi `market_calendars` menjadi `market_calendar`.

File berubah:
- `app/Repositories/WatchlistRepository.php`
- `docs/watchlist/schema.md`
- `docs/watchlist/strategi.md`

## Patch r9 — Repository helpers untuk readiness gate

Menambahkan helper yang dibutuhkan watchlist untuk menentukan `asof_eod_date` dan menghitung coverage, agar `canonical_ready` bisa diputuskan secara benar (kontrak `docs/watchlist/watchlist.md`).

Perubahan:
- Tambah `WatchlistRepository::getLatestCommonEodDate()` (tanggal EOD terbaru yang ada di OHLC dan indicators).
- Tambah `WatchlistRepository::coverageSnapshot($eodDate)` (coverage OHLC dan indicators terhadap universe tickers).
- Tambah `WatchlistRepository::maxCloseBetween()` dan `maxHighBetween()` (dipakai saat ada posisi / stop logic agar tidak fatal).

File berubah:
- `app/Repositories/WatchlistRepository.php`

## Patch r10 — Fix crash CONFIRM mapping + fail-soft kalender

Memperbaiki crash yang bisa terjadi saat mapping CONFIRM intraday dan menambah guard agar sistem tidak hard-crash jika `market_calendar` belum siap.

Perubahan:
- `WatchlistEngine`: mapping CONFIRM tidak lagi mengakses properti yang tidak ada; gunakan repository untuk update retry-state.
- `MarketCalendarRepository`: table-exists guard + try/catch pada method utama.

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`
- `app/Repositories/MarketCalendarRepository.php`

## Patch r11 — Fix Candidate DTO: enrich() butuh CandidateInput (FATAL)

Menutup error TypeError di `/watchlist/preopen`: `CandidateDerivedMetricsBuilder::enrich()` mengharapkan `CandidateInput`, tapi repository mengembalikan array.

Perubahan:
- `WatchlistRepository::getEodCandidates()` sekarang mengembalikan array of `CandidateInput`.
- Tambah field yang diperlukan oleh engine ke `CandidateInput` (prev OHLC, score_total, decision/signal/volume codes, vol_sma20) dan mapping dv20 dari dv20_idr.
- Tambah `app/DTO/BaseDto.php` minimal untuk memastikan autoload DTO stabil.

File berubah:
- `app/Repositories/WatchlistRepository.php`
- `app/DTO/Watchlist/CandidateInput.php`
- `app/DTO/BaseDto.php`

## Patch r13 — Fix Collection::toArray() mengubah DTO jadi array (FATAL)

Menutup error TypeError yang masih muncul di `/watchlist/preopen` ketika hasil repository berupa Collection of DTO dikonversi menggunakan `toArray()`. Laravel akan memanggil `CandidateInput::toArray()` sehingga item menjadi array, padahal `CandidateDerivedMetricsBuilder::enrich()` mengharapkan objek `CandidateInput`.

Perubahan:
- `WatchlistRepository::getEodCandidates()` sekarang mengembalikan array of `CandidateInput` via `->values()->all()` (bukan `->toArray()`).

File berubah:
- `app/Repositories/WatchlistRepository.php`

## Patch r14 — Watchlist scorecard readiness + intraday ingest (FATAL)

Menutup 5 gap yang sebelumnya masih menyebabkan fitur watchlist/scorecard tidak sesuai `docs/watchlist/*`:

1) `watchlist_strategy_runs` harus bisa terbentuk saat `check-live` (sesuai schema.md)
   - `WatchlistScorecardService::checkLiveDto()` sekarang auto-hydrate PLAN dari `watchlist_daily` (payload preopen) bila run belum ada.

2) Retry state CONFIRM harus ter-update (fail-soft)
   - Setelah evaluate, `check-live` best-effort update `confirm_retry_count`, `confirm_last_checked_at`, `confirm_next_check_at` di `watchlist_intraday_snapshots`.

3) Tambah command ingest snapshot intraday (opsional)
   - Command baru `watchlist:intraday:ingest` untuk upsert snapshot dari JSON manual.

4) Default ScorecardConfig diselaraskan dengan docs
   - Default: `stale_tol_pct=0.003`, `max_snapshot_age_sec=30`, `retry_cooldown_sec=30`, `breakout_band_pct_default=0.004`, `max_retry_windows_default=2`.

5) Living docs strategi.md diperbarui agar operator bisa jalan tanpa SQL
   - Tambah contoh command & format JSON untuk ingest snapshot.

File berubah/ditambah:
- `app/Services/Watchlist/WatchlistScorecardService.php`
- `app/Repositories/WatchlistPersistenceRepository.php`
- `app/Repositories/TickerRepository.php`
- `app/Repositories/IntradaySnapshotRepository.php`
- `app/Trade/Watchlist/Config/ScorecardConfig.php`
- `app/Console/Commands/WatchlistIntradayIngest.php`
- `docs/watchlist/strategi.md`


## Patch r15 — Candidate cleanliness (Universe + policy hard DROP)

Selaraskan pembentukan kandidat agar benar-benar *bersih* sesuai `docs/watchlist/*`:

- Universe hard gates diterapkan di `buildCandidate()`:
  - Liquidity min: prefer `dv20_idr`, fallback `turnover20_idr` (same scale, min_periods=20)
  - Price sanity: `close >= 50` (default)
  - Extreme volatility: `atr_pct <= 0.20` (default)
- Policy hard rules WEEKLY_SWING kini benar-benar **DROP** (bukan sekadar block)
  - Trend gate / liquidity / ATR band / tick size gate => gugur
- Fix RR estimator di DIVIDEND_SWING agar tidak menggunakan `max(1.0, R)`

File berubah:
- `app/Trade/Watchlist/WatchlistEngine.php`
- `app/Repositories/WatchlistRepository.php`
- `app/DTO/Watchlist/CandidateInput.php`
- `config/trade.php`
- `tests/Unit/Watchlist/WatchlistPreopenBuildRegressionTest.php`
