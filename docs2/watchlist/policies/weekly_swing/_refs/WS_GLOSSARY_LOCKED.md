# WS Locked Glossary

last_updated=2026-02-22

Definisi istilah di bawah **LOCKED** untuk mencegah drift.

## coverage_ratio
`coverage_ratio = eligible_with_complete_required_fields / eligible_total`

## eligible_total
Jumlah ticker yang lolos universe eligibility + guards untuk PLAN pada `asof_eod_date`.

## required_ok
Flag bahwa semua field “required” tersedia untuk ticker pada tanggal itu (dipakai untuk coverage).

## stale
Runtime snapshot dianggap stale jika `now - snapshot_ts > confirm_overlay.snapshot_max_age_sec`.

## spread_pct
`spread_pct = (ask - bid) / mid`, dengan `mid = (bid + ask) / 2`.

## drift_pct
`drift_pct = abs(mid - entry_ref) / entry_ref`.

## entry_ref
Sumber tunggal untuk RR/stop/TP1 di PLAN. **LOCKED:** `entry_ref = entry_band_mid` (lihat Doc 04).

## stop_price
Harga stop hasil rule `risk.stop_mode` dan/atau `risk.stop_atr_mult`.

## tp1_price
Target profit deterministik. **LOCKED:** `tp1_price = entry_ref + (risk.min_rr * (entry_ref - stop_price))` (lihat Doc 04).
