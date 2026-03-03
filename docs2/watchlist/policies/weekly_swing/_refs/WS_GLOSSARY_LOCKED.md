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
Waktu ketika data intraday aggregate **diambil** (manual input, dari jam perangkat saat mengambil data).

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
- Jika `checked_at - effective_captured_at > 900` → snapshot **EXPIRED** → CONFIRM wajib menghasilkan `label = DELAY` + reason `WS_STALE`.

## volume_shares (LOCKED)
Total volume intraday dalam unit **shares** (bukan lot).
- Jika sumber hanya menyediakan lot, wajib konversi: `shares = lot × 100`.

## turnover_idr (LOCKED)
Total nilai transaksi intraday (IDR) / traded value (Ajaib: “Turnover” nominal).
- **DILARANG** memakai “Turnover %” sebagai `turnover_idr`.

## Non-Contract Fields (CONFIRM) (LOCKED)
Field order book ladder (bid/ask/spread/imbalance/orderbook) **bukan input keputusan CONFIRM**.
Jika field tersebut muncul di payload/import, engine CONFIRM wajib **mengabaikan** (no-effect).
