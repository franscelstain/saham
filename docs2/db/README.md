# Database Contracts (Shared)

> **Status:** LOCKED (Normative)
> **Doc Role:** Shared market-data contracts index


## Purpose
Entry point kontrak data sumber market data.

## Scope
Dipakai sebelum membaca dokumen watchlist policy yang memakai market data tersebut.

## Inputs
- Pembaca yang perlu memahami struktur dan arti tabel sumber data.

## Outputs
- Peta baca dokumen kontrak data sumber.

Folder ini mendokumentasikan **kontrak data sumber** yang dipakai lintas modul.
Fokusnya bukan nama tabel fisik, tetapi **struktur minimum, makna kolom, unit, key, dan perilaku data** yang harus stabil.

## Start here
Baca berurutan:
1. [`01_MARKET_CALENDAR.md`](01_MARKET_CALENDAR.md)
2. [`02_TICKERS_MASTER.md`](02_TICKERS_MASTER.md)

## Apa yang dikunci di folder ini
- **Tanggal perdagangan**: definisi hari bursa yang dipakai PLAN/backtest/audit.
- **Identitas ticker**: canonical code, status aktif, dan histori listing/delisting.

## Source of truth
- Untuk **kontrak data sumber**, dokumen di folder ini ([`./`](./README.md)) adalah **source of truth**.
- Untuk **schema database watchlist aplikasi**, baca [`../watchlist/db/`](../watchlist/db/README.md) karena itu layer penyimpanan aplikasi, bukan layer sumber market data.
- Untuk **aturan policy**, baca [`../watchlist/policies/`](../watchlist/policies/README.md).

## Cara pakai
Jika implementasi memakai nama tabel/kolom fisik berbeda, tetap wajib menyediakan mapping yang membuat kontrak di folder ini terpenuhi.
Kalau ada konflik antara implementasi dan dokumen ini, yang harus dibenahi adalah implementasinya atau adapter mapping-nya, bukan definisi bisnisnya.

## Non-scope
Folder ini **tidak** mendefinisikan:
- algoritma ranking/scoring policy
- reason codes/fail codes watchlist
- runtime PLAN/CONFIRM output
- prosedur promosi paramset

Hal-hal itu ada di [`../watchlist/`](../watchlist/README.md).
