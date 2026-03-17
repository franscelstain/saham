# 05 — WS Persistence Guidance

## Purpose

Dokumen ini memberi panduan persistence artifact watchlist agar aplikasi tetap sinkron dengan baseline docs.

## Persistence Objects

### PLAN Artifact
Persist jika dibutuhkan sebagai baseline immutable harian.

### RECOMMENDATION Artifact
Persist jika dibutuhkan untuk replay, audit, atau delivery caching.

### CONFIRM Artifact
Persist jika dibutuhkan untuk history confirm dan consumer reads.

## Separation Rules

1. `PLAN`, `RECOMMENDATION`, dan `CONFIRM` harus dapat dipisahkan sebagai artifact berbeda
2. persistence `RECOMMENDATION` tidak boleh menunggu `CONFIRM`
3. persistence `CONFIRM` tidak boleh menimpa `RECOMMENDATION`
4. persistence watchlist tidak boleh mencampur artifact portfolio

## Minimal Storage Keys

- `strategy_code`
- `trade_date`
- `ticker` (untuk item-level)
- `policy_code`
- `param_set_id`
- `policy_version`
- `schema_version`
- timestamps audit yang relevan

## Note on Source Data

Penyimpanan watchlist tidak bertugas menyimpan raw market-data provider. Watchlist hanya menyimpan artifact hasil domain watchlist.


## Anti-Ambiguity Guard

- Simpan `param_set_id` bila artifact perlu menunjuk instance paramset aktif yang dipakai saat generate runtime output.
- Simpan `policy_version` untuk menunjukkan versi rule/business contract.
- Simpan `schema_version` untuk menunjukkan versi schema paramset yang divalidasi.
- Jangan memakai `paramset_version` sebagai nama field persistence karena maknanya tidak tunggal.
