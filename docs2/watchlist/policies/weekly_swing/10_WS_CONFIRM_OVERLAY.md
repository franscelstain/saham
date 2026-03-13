# 10 — CONFIRM Overlay (Intraday Snapshot) — Weekly Swing

> **Status:** LOCKED (Normative)
> **Doc Role:** WS CONFIRM overlay contract


## Purpose
Menetapkan CONFIRM sebagai **pengecekan keyakinan** berbasis **intraday snapshot manual** yang:

- **tidak boleh mengubah PLAN** (PLAN immutability),
- **tidak mengklaim real-time** (snapshot = sumber kebenaran CONFIRM),
- otomatis **tidak sah** (EXPIRED) jika snapshot melewati TTL,
- memaksa output yang **tidak bisa diperdebatkan**: benar/salahnya CONFIRM hanya ditentukan oleh **snapshot yang diinput** dan **usia snapshot**.


## Contract Strictness — LOCKED

CONFIRM menerima payload snapshot intraday sebagai **input deterministik**. Untuk mencegah drift, aturan ketat berikut berlaku:

### 1) Unknown/extra fields
- **Top-level unknown fields** (di luar kontrak) ⇒ **FAIL (INVALID_SCHEMA_DRIFT)**.
- **Item-level unknown fields** (di dalam `items[]`) ⇒ **IGNORE** *tanpa efek ke keputusan*, tetapi **wajib dicatat** dalam output `confirm_meta.ignored_fields_by_item[]`.

> Alasan: top-level drift berisiko mengubah arti payload secara global; sedangkan item-level “noise” seperti orderbook boleh lewat agar integrasi tidak rapuh, selama benar-benar tidak mempengaruhi keputusan.

### 2) Non-contract “orderbook fields”
Field seperti `bid1_price`, `ask1_price`, `spread`, `orderbook_json` **selalu dianggap non-contract** dan **harus diabaikan** (tidak boleh masuk perhitungan apapun).
Fixture referensi: `fixtures/confirm_payload_with_orderbook_fields.json`.

### 3) Logging minimum
Output CONFIRM **wajib** memuat:
- `confirm_meta.snapshot_captured_at`
- `confirm_meta.snapshot_age_seconds`
- `confirm_meta.ttl_seconds`
- `confirm_meta.is_expired`
- `confirm_meta.ignored_fields_by_item[]` (jika ada)

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

**Contoh (LOCKED):**
- `captured_at=09:30`, `inserted_at=10:10` ⇒ `effective_captured_at=09:30` (snapshot bisa **EXPIRED** walau baru diinput jam 10:10).

---

## Validity & TTL (LOCKED)

### TTL CONFIRM (Weekly Swing)
- `snapshot_max_age_sec = 900` (**15 menit**)

Hitung:
- `snapshot_age_sec = checked_at - effective_captured_at`

Aturan:
- Jika `snapshot_age_sec > snapshot_max_age_sec` (LOCKED: 900 detik) → snapshot **EXPIRED** → output CONFIRM **wajib**:
  - `label = DELAY`
  - reason code wajib: `WS_STALE`
  - karena ini kondisi snapshot-level, seluruh item hasil CONFIRM pada run tersebut wajib berlabel `DELAY`
- Jika tidak ada snapshot → `label = DELAY`, reason code wajib: `WS_SNAPSHOT_MISSING`
- LOCKED: `NO_TRADE` bukan label CONFIRM. `NO_TRADE` hanya berlaku untuk status run PLAN/global selection, sedangkan label CONFIRM hanya boleh `CONFIRMED`, `NEUTRAL`, `CAUTION`, atau `DELAY`.

**LOCKED:** snapshot yang diambil masa lalu tapi baru diinput sekarang **tetap sah sebagai snapshot**, namun bisa menjadi **EXPIRED** karena TTL.

---

## Output Model (LOCKED)

CONFIRM menghasilkan output **terpisah** dari PLAN. Kontrak output final API/UI yang bersifat normatif dikunci oleh dokumen bernomor Weekly Swing ini; [`_refs/WS_RUNTIME_OUTPUT_SCHEMA.md`](_refs/WS_RUNTIME_OUTPUT_SCHEMA.md) hanya menjadi ringkasan referensi dan tidak boleh diperlakukan sebagai sumber aturan utama.

Bentuk minimum output final:
- `meta`:
  - `policy`
  - `checked_at`
  - `snapshot_ts`
  - `snapshot_age_sec`
  - `source.snapshot_id`
- `items[]`:
  - `ticker`
  - `label` (`CONFIRMED` / `NEUTRAL` / `CAUTION` / `DELAY`)
  - `reasons[]`:
    - `code`
    - `severity`
    - `message`
    - `payload`
- `summary`:
  - `confirmed_count`
  - `neutral_count`
  - `caution_count`
  - `delay_count`

Catatan (LOCKED):
- Field PLAN seperti `group_semantic`, `score_total`, `entry_ref`, atau ranking boleh dipakai **sebagai input evaluasi**, tetapi **tidak boleh** ditambahkan ke item output final CONFIRM kecuali schema runtime resmi diubah.
- Nama field final yang sah hanya `ticker`, `label`, dan `reasons[]` pada level item.

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
- Snapshot CONFIRM memakai **intraday aggregate** (last_price/chg_pct/volume_shares/turnover_idr). Order book ladder **bukan** input keputusan CONFIRM.

---

## Execution Steps (reference)

1) Load PLAN untuk `trade_date=T`
2) Load snapshot terbaru untuk `(policy_code='WS', trade_date=T)` dengan urutan:
   - `captured_at DESC`, lalu tie-breaker `snapshot_id DESC` (LOCKED)
3) Hitung `snapshot_age_sec = checked_at - effective_captured_at`
4) Jika `snapshot_age_sec > snapshot_max_age_sec` → hasilkan `label = DELAY` + `WS_STALE`
5) Jika snapshot tidak ada → hasilkan `label = DELAY` + `WS_SNAPSHOT_MISSING`
6) Jika `last_price` tidak ada → hasilkan `label = DELAY` + `WS_NO_PRICE`
7) Jika snapshot valid:
   - hitung `drift_pct = abs(last_price - entry_ref) / entry_ref`
   - jika `drift_pct > max_drift_from_entry_pct` → tambah `WS_DRIFT_FAR`
   - jika salah satu dari `volume_shares` atau `turnover_idr` tidak ada → tambah `WS_INPUT_INCOMPLETE` (BLOCK)
8) Mapping label:
   - ada `BLOCK` → `DELAY`
   - else ada `WARN` → `CAUTION`
   - else jika ada reason code `WS_CONFIRM_OK` → `CONFIRMED`
   - else jika ada `INFO` lain (mis. `WS_CONFIRM_NEUTRAL`) → `NEUTRAL`
   - else → `CONFIRMED`
   - LOCKED: `WS_CONFIRM_OK` adalah positive confirmation code; `WS_CONFIRM_NEUTRAL` adalah informational-neutral code.
   - LOCKED: CONFIRM tidak boleh menambahkan reason code yang hanya mengulang label akhir.
  `CONFIRMED`, `NEUTRAL`, `CAUTION`, dan `DELAY` adalah label hasil evaluasi, bukan reason code tersendiri.
9) Output CONFIRM **tidak mengubah PLAN** (invariant harus lolos)

## Next
### Weekly Swing
- 11_WS_INTRADAY_SNAPSHOT_TABLES.md (tabel & kolom input manual CONFIRM)
