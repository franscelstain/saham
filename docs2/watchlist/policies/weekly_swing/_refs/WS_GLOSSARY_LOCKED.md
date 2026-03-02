# WS Glossary (LOCKED)

Istilah di dokumen Weekly Swing yang **wajib** dipahami sama oleh semua implementasi.

## PLAN
Output rekomendasi berbasis EOD untuk `trade_date = T` (besok). PLAN berisi ranking + group_semantic + score_total + reasons.

## CONFIRM
Pengecekan keyakinan yang memakai **intraday snapshot manual**, bukan real-time. CONFIRM menghasilkan output terpisah dan **dilarang mengubah PLAN**.

## Snapshot (CONFIRM Snapshot)
Data intraday yang diinput manual dan disimpan ke DB untuk dipakai CONFIRM.

- Snapshot = sumber kebenaran CONFIRM.
- Jika snapshot diambil di masa lalu tapi baru diinput sekarang, isi snapshot **tidak diperdebatkan**.
- Yang dinilai hanya **validitas usia snapshot (TTL)**.

## captured_at
Waktu ketika data order book **diambil** (manual input, dari jam perangkat saat mengambil data).

## inserted_at
Waktu ketika snapshot masuk database (otomatis).

## effective_captured_at (LOCKED)
Timestamp konservatif untuk TTL:
- `effective_captured_at = LEAST(captured_at, inserted_at)`

## checked_at
Waktu saat CONFIRM dijalankan.

## TTL CONFIRM (LOCKED)
Batas usia snapshot agar sah:
- `snapshot_max_age_sec = 900` (15 menit)
- Jika `checked_at - effective_captured_at > 900` → snapshot **EXPIRED** → CONFIRM wajib downgrade menjadi DELAY/NO_TRADE + reason `WS_STALE`.

## Top of Book
Bid/ask level teratas:
- `bid1_price`, `bid1_lots`, `ask1_price`, `ask1_lots`

## Depth Summary (Top 5 / Top 10)
Ringkasan lot:
- `bid_lots_sum_5`, `ask_lots_sum_5`, `bid_lots_sum_10`, `ask_lots_sum_10`

## Imbalance
Tekanan bid vs ask:
- `imbalance_n = (bid_sum_n - ask_sum_n) / (bid_sum_n + ask_sum_n)` untuk n=5 dan n=10.
