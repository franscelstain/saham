# MARKET_DATA_FAILURE_PLAYBOOK — Ops Checklist (S1/S2/S3)

Dokumen ini hanya untuk **aksi cepat** saat data bermasalah. Detail kontrak ada di `MARKET_DATA.md` & `compute_eod.md`.

## Terminologi singkat
- `effective_end_date`: tanggal final yang boleh dipakai downstream.
- `last_good_trade_date`: fallback saat run terbaru `CANONICAL_HELD/FAILED`.

## S1 — Fetch/Provider error (RAW kosong, timeout, 401, dsb)
Gejala:
- `md_runs.status = FAILED` atau `canonical_points` sangat kecil.
Langkah:
1) Cek log error provider (HTTP status, token, rate limit).
2) Rerun import untuk tanggal target:
   - `market-data:import-eod --from=... --to=...`
3) Publish jika import sudah sehat:
   - `market-data:publish-eod --run=RUN_ID`
4) Compute ulang:
   - `trade:compute-eod --date=YYYY-MM-DD`

Downstream:
- Jika tetap gagal ➜ pakai `last_good_trade_date` (jangan paksa today).

## S2 — Disagreement/quality turun (coverage masih oke, tapi data mencurigakan)
Gejala:
- `coverage_pct` masih ≥ threshold, tapi banyak anomali / mismatch antar provider.
Langkah:
1) Jalankan validator (jika dipakai) untuk tanggal target:
   - `market-data:validate-eod --date=YYYY-MM-DD`
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
   - `market-data:rebuild-canonical --from=... --to=... [--ticker=CODE]`
3) Publish ulang:
   - `market-data:publish-eod --run=RUN_ID`
4) Recompute indikator untuk range:
   - `trade:compute-eod --from=... --to=... [--ticker=CODE]`

Downstream:
- Sampai rebuild selesai ➜ pakai `last_good_trade_date`.

## Checklist output yang wajib dicek (selalu)
- `md_runs`: `status`, `effective_end_date`, `coverage_pct`, `fallback_pct`, `hard_rejects`, `notes`
- `ticker_ohlc_daily`: duplikasi `(ticker_id, trade_date)` harus **tidak ada**
- `ticker_indicators_daily`: indikator hari target terisi wajar (tidak dominan NULL tanpa sebab)
