# MARKET_DATA_FAILURE_PLAYBOOK — Ops Checklist (S1/S2/S3)

Dokumen ini hanya untuk **aksi cepat** saat data bermasalah. Detail kontrak ada di `MARKET_DATA.md` & `compute_eod.md`.

## Terminologi singkat
- `effective_end_date`: tanggal final yang boleh dipakai downstream.
- `last_good_trade_date`: fallback saat run terbaru `CANONICAL_HELD/FAILED`.

## S1 — Fetch/Provider error (RAW kosong, timeout, 401, dsb)
Gejala:
- `md_runs.status = FAILED` atau `canonical_points` sangat kecil.
- Timeout/retry berulang ➜ cek `TRADE_YAHOO_TIMEOUT/RETRY` (import) atau `TRADE_EODHD_TIMEOUT/RETRY` (validator).
Langkah:
1) Cek log error provider (HTTP status, token, rate limit). Untuk EODHD: pastikan `TRADE_EODHD_API_TOKEN` terisi dan `TRADE_EODHD_DAILY_CALL_LIMIT` tidak terlampaui.
2) Rerun import untuk tanggal target:
   - Single date: `market-data:import-eod --date=YYYY-MM-DD`
   - Range: `market-data:import-eod --from=YYYY-MM-DD --to=YYYY-MM-DD`
   - Satu ticker saja (debug cepat): `market-data:import-eod --date=YYYY-MM-DD --ticker=CODE`
   - Jika RAM/DB berat: kecilkan batch ticker `--chunk=100` (atau 50)
3) Publish jika import sudah sehat:
   - `market-data:publish-eod --run=RUN_ID`
   - Jika DB berat: turunkan batch publish `--batch=1000` (atau 500)
4) Compute ulang:
   - `trade:compute-eod --date=YYYY-MM-DD`

Downstream:
- Jika tetap gagal ➜ pakai `last_good_trade_date` (jangan paksa today).

## S2 — Disagree (mismatch antar provider)ment/quality turun (coverage masih oke, tapi data mencurigakan)
Gejala:
- `coverage_pct` masih ≥ threshold, tapi banyak anomali / mismatch antar provider.
Catatan cepat:
- Cek `md_runs.notes` untuk `held_reason`:
  - `held_reason=disagree_major`
  - `held_reason=soft_quality`
  - `held_reason=low_coverage_days`
  - `held_reason=missing_trading_day`

Langkah:
1) Jalankan validator (jika dipakai) untuk tanggal target:
   - Minimal: `market-data:validate-eod --date=YYYY-MM-DD`
   - Optional: `--tickers=BBCA,BBRI` untuk batasi ticker
   - Optional: `--max=20` untuk override jumlah (tetap dibatasi config/kuota)
   - Convenience: kalau `--tickers` kosong, sistem otomatis ambil **top_picks** dari watchlist preopen (biar nggak ngetik manual).
   - Optional: `--save=0` kalau tidak mau nyimpan ke `md_candidate_validations`
   - Ingat kuota `TRADE_EODHD_DAILY_CALL_LIMIT`.
2) Jika validator menunjukkan mismatch besar ➜ perlakukan seperti S3 untuk ticker terdampak.

Downstream:
- Jika banyak ticker kena anomaly ➜ tahan keputusan agresif (watchlist downgrade).

## S3 — Data corrupt / timezone shift / CA mass / split‑like move
Gejala:
- `CANONICAL_HELD` berulang, spike `hard_rejects`, banyak `ca_hint`, candle “loncat”, **volume salah unit** (lot vs shares), atau **mapping ticker salah**.
Langkah:
1) Tentukan range & ticker terdampak.
2) Rebuild canonical:
   - Minimal range: `market-data:rebuild-canonical --from=YYYY-MM-DD --to=YYYY-MM-DD [--ticker=CODE]`
   - Single date: `market-data:rebuild-canonical --date=YYYY-MM-DD [--ticker=CODE]`
   - Override sumber RAW run (kalau butuh audit/debug): `--source_run=RUN_ID`
3) Publish ulang:
   - `market-data:publish-eod --run=RUN_ID`
   - Jika DB berat: `--batch=1000` (atau 500)
4) Recompute indikator untuk range:
   - `trade:compute-eod --from=... --to=... [--ticker=CODE]`

Downstream:
- Sampai rebuild selesai ➜ pakai `last_good_trade_date`.

## Checklist output yang wajib dicek (selalu)
- `md_runs`: `status`, `effective_end_date`, `coverage_pct`, `fallback_pct`, `hard_rejects`, `notes`
  - Pastikan ada `published_ohlc_rows=...` setelah publish untuk memastikan publish benar-benar terjadi
- `ticker_ohlc_daily`: duplikasi `(ticker_id, trade_date)` harus **tidak ada**
- `ticker_indicators_daily`: indikator hari target terisi wajar (tidak dominan NULL tanpa sebab)


## Config kunci yang paling sering dipakai saat insiden
- Coverage hold: `TRADE_MD_COVERAGE_MIN`
- HOLD disagree: `TRADE_MD_HOLD_DISAGREE_RATIO_MIN`, `TRADE_MD_HOLD_DISAGREE_COUNT_MIN`
- HOLD low coverage days: `TRADE_MD_MIN_DAY_COVERAGE_RATIO`, `TRADE_MD_MIN_POINTS_PER_DAY`, `TRADE_MD_HOLD_LOW_COVERAGE_DAYS_MIN`
- Disagree scoring (import): `TRADE_MD_DISAGREE_PCT`
- Outlier/gap (soft-quality): `TRADE_MD_GAP_EXTREME_PCT`, `TRADE_MD_TOL`
- Lookback default range: `TRADE_MD_LOOKBACK_TRADING_DAYS`
- Validator caps: `TRADE_MD_VALIDATOR_MAX_TICKERS`, `TRADE_MD_VALIDATOR_DISAGREE_PCT`, `TRADE_EODHD_DAILY_CALL_LIMIT`
- Token/timeout validator: `TRADE_EODHD_API_TOKEN`, `TRADE_EODHD_TIMEOUT`
