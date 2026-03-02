# 10 — CONFIRM Overlay (Intraday Snapshot) — Weekly Swing

## Purpose
Menetapkan CONFIRM sebagai **pengecekan keyakinan** berbasis **intraday snapshot manual** yang:

- **tidak boleh mengubah PLAN** (PLAN immutability),
- **tidak mengklaim real-time** (snapshot = sumber kebenaran CONFIRM),
- otomatis **tidak sah** (EXPIRED) jika snapshot melewati TTL,
- memaksa output yang **tidak bisa diperdebatkan**: benar/salahnya CONFIRM hanya ditentukan oleh **snapshot yang diinput** dan **usia snapshot**.

## Prerequisites
- 09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md

---

## Inputs (LOCKED)

### A) PLAN
- `plan_run_id` untuk `trade_date = T`
- `plan_items[]` (ranking, group_semantic, score_total, reasons PLAN)

### B) CONFIRM snapshot (manual, dari DB)
Snapshot berasal dari tabel:
- `watchlist_confirm_snapshots`
- `watchlist_confirm_snapshot_items`

**Kolom wajib per ticker** dan contoh data wajib mengikuti:
- 11_WS_INTRADAY_SNAPSHOT_TABLES.md

### C) Timestamps
- `captured_at` (diisi manual)
- `inserted_at` (otomatis)
- `checked_at` (waktu CONFIRM dijalankan)

**LOCKED anti-manipulasi:**
- `effective_captured_at = LEAST(captured_at, inserted_at)`

---

## Validity & TTL (LOCKED)

### TTL CONFIRM (Weekly Swing)
- `snapshot_max_age_sec = 900` (**15 menit**)

Hitung:
- `snapshot_age_sec = checked_at - effective_captured_at`

Aturan:
- Jika `snapshot_age_sec > 900` → snapshot **EXPIRED** → output CONFIRM **wajib**:
  - `label = DELAY`
  - reason code wajib: `WS_STALE`
- Jika tidak ada snapshot → `label = DELAY`, reason code wajib: `WS_SNAPSHOT_MISSING`
- LOCKED: `NO_TRADE` bukan label CONFIRM. `NO_TRADE` hanya berlaku untuk status run PLAN/global selection, sedangkan label CONFIRM hanya boleh `CONFIRMED`, `NEUTRAL`, `CAUTION`, atau `DELAY`.

**LOCKED:** snapshot yang diambil masa lalu tapi baru diinput sekarang **tetap sah sebagai snapshot**, namun bisa menjadi **EXPIRED** karena TTL.

---

## Output Model (LOCKED)

CONFIRM menghasilkan output terpisah, minimal:
- `confirm_run_id`
- `checked_at`
- `snapshot_id` (yang dipakai)
- `snapshot_age_sec`
- `items[]`:
  - `ticker_code`
  - `plan_group_semantic` (dari PLAN, immutable)
  - `plan_score_total` (dari PLAN, immutable)
  - `label` (`CONFIRMED` / `NEUTRAL` / `CAUTION` / `DELAY`)
  - `reasons[]`:
    - `code`
    - `severity`
    - `message`
    - `payload`

---

## PLAN Immutability (LOCKED Invariant)

CONFIRM **dilarang**:
- mengubah record PLAN
- mengubah ranking PLAN
- mengubah `score_total`
- mengubah `group_semantic`
- mengganti top picks / secondary

Cara enforce (wajib ada di test/contract):
- `plan_hash_before == plan_hash_after`
- jumlah item PLAN tidak berubah
- urutan ranking PLAN tidak berubah

---

## Snapshot Storage Rules (LOCKED)

- Snapshot bersifat **append-only** (UPDATE/DELETE dilarang).
- Jika input ulang snapshot untuk ticker yang sama, buat **snapshot baru** (snapshot_id baru).
- `orderbook_json` boleh `{}` jika tidak menyimpan ladder; namun kolom ringkasan (`sum_5/sum_10`, spread, imbalance) **wajib** terisi.

---

## Execution Steps (reference)

1) Load PLAN untuk `trade_date=T`
2) Load snapshot terbaru untuk `(policy_code='WS', trade_date=T)` berdasarkan `captured_at DESC`
3) Hitung `snapshot_age_sec = checked_at - effective_captured_at`
4) Jika `snapshot_age_sec > snapshot_max_age_sec` → hasilkan `label = DELAY` + `WS_STALE`
5) Jika snapshot tidak ada → hasilkan `label = DELAY` + `WS_SNAPSHOT_MISSING`
6) Jika `last_price` tidak ada → hasilkan `label = DELAY` + `WS_NO_PRICE`
7) Jika snapshot valid:
   - hitung `drift_pct = abs(last_price - entry_ref) / entry_ref`
   - jika `drift_pct > max_drift_from_entry_pct` → tambah `WS_DRIFT_FAR`
   - jika bid1/ask1 tersedia:
     - hitung `spread = ask1_price - bid1_price`
     - hitung `spread_pct = spread / last_price`
     - jika `spread_pct > spread_max_pct` → tambah `WS_SPR_WIDE`
   - jika bid1/ask1 tidak tersedia → tambah `WS_SPR_NA`
8) Mapping label:
   - ada `BLOCK` → `DELAY`
   - else ada `WARN` → `CAUTION`
   - else ada `INFO` saja → `NEUTRAL`
   - else → `CONFIRMED`
   - LOCKED: CONFIRM tidak boleh menambahkan reason code yang hanya mengulang label akhir.
  `CONFIRMED`, `NEUTRAL`, `CAUTION`, dan `DELAY` adalah label hasil evaluasi, bukan reason code tersendiri.
9) Output CONFIRM **tidak mengubah PLAN** (invariant harus lolos)

## Next
### Weekly Swing
- 11_WS_INTRADAY_SNAPSHOT_TABLES.md (tabel & kolom input manual CONFIRM)