# 04 — WS API Guidance

## Purpose

Dokumen ini memberi panduan API/read-model untuk aplikasi watchlist tanpa masuk ke domain execution atau portfolio.

## Recommended Read APIs

### A. Plan Read
Contoh:
- `GET /watchlist/weekly-swing/plan?trade_date=YYYY-MM-DD`

Mengembalikan artifact `PLAN` apa adanya atau summary yang sah.

### B. Recommendation Read
Contoh:
- `GET /watchlist/weekly-swing/recommendation?trade_date=YYYY-MM-DD`

Mengembalikan artifact `RECOMMENDATION` yang dibentuk dari `PLAN`.

### C. Confirm Submit / Read
Contoh:
- `POST /watchlist/weekly-swing/confirm`
- `GET /watchlist/weekly-swing/confirm?trade_date=YYYY-MM-DD&ticker=...`

`POST` hanya menerima input confirm yang sah terhadap candidate `PLAN`.

### D. Composite Read
Contoh:
- `GET /watchlist/weekly-swing/view?trade_date=YYYY-MM-DD`

Mengembalikan gabungan state watchlist untuk consumer.

## API Rules

1. endpoint watchlist tidak boleh membuat transaksi beli/jual
2. endpoint watchlist tidak boleh expose field execution-only
3. response `RECOMMENDATION` tidak boleh dibentuk dari `CONFIRM`
4. response `CONFIRM` harus gagal bila ticker bukan candidate `PLAN`
5. jika recommendation kosong, endpoint confirm tetap boleh berjalan untuk candidate `PLAN`

## Manual Input Support

Aplikasi harus mendukung input manual untuk confirm sepanjang payload sesuai kontrak.
