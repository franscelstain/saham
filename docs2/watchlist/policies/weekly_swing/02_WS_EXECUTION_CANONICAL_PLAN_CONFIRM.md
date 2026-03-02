# 02 — Execution Canonical: PLAN (EOD) → CONFIRM (Intraday Snapshot) — Weekly Swing

Dokumen ini mengunci alur eksekusi Weekly Swing menjadi dua tahap yang tegas:

- **PLAN** dibuat dari data **EOD hari ini** untuk rekomendasi **besok**.
- **CONFIRM** memakai **intraday snapshot manual** (bukan real-time) untuk mengecek keyakinan pada saat snapshot diambil.

**LOCKED:** CONFIRM tidak boleh mengubah PLAN.

---

## Prerequisites
### Weekly Swing
01_WS_OVERVIEW.md

## A. Timeline (LOCKED)

- Hari D (setelah market close): sistem membentuk PLAN untuk trade_date = D+1.
- Hari D+1 (sebelum eksekusi manual user): user input snapshot intraday (manual) ke DB, lalu menjalankan CONFIRM.

---

## B. PLAN (EOD) (LOCKED)

PLAN:
- input: data EOD, indikator, scoring, risk guard, universe
- output: `plan_items[]` dengan `ranking`, `group_semantic`, `score_total`, `reasons[]`

PLAN adalah **referensi utama**. PLAN tidak boleh berubah ketika CONFIRM jalan.

---

## C. CONFIRM (Intraday Snapshot) (LOCKED)

### C1) Sumber data CONFIRM
CONFIRM memakai snapshot manual yang disimpan di DB, bukan data real-time.

Tabel input manual snapshot (wajib):
- `watchlist_confirm_snapshots`
- `watchlist_confirm_snapshot_items`

Spesifikasi tabel + kolom + contoh data:
- 11_WS_INTRADAY_SNAPSHOT_TABLES.md

### C2) Timestamp & validity
- `captured_at` = waktu data diambil (manual)
- `inserted_at` = waktu input ke DB (otomatis)
- `checked_at` = waktu CONFIRM dijalankan

**LOCKED anti-manipulasi:**
- `effective_captured_at = LEAST(captured_at, inserted_at)`

### C3) TTL CONFIRM (LOCKED)
- TTL = 15 menit → `snapshot_max_age_sec = 900`

Aturan:
- Jika `checked_at - effective_captured_at > 900 detik` → snapshot **EXPIRED**
  - CONFIRM wajib menghasilkan `confirm_label = DELAY` + `WS_STALE`
- Jika snapshot tidak ada → `confirm_label = DELAY` + `WS_SNAPSHOT_MISSING`

---

## D. Immutability Contract (LOCKED)

CONFIRM overlay hanya boleh menambah **output CONFIRM terpisah**.
Dilarang:
- mengubah PLAN record/fields
- reorder ranking
- mengubah `score_total`
- mengubah `group_semantic`

**Contract test wajib:**
- `plan_hash_before == plan_hash_after`

---

## E. Output Canonical (LOCKED)

Output akhir di UI harus menampilkan dua hal terpisah:

1) **PLAN untuk besok (trade_date=T)**  
   - ranking, group_semantic, score_total, reasons PLAN

2) **CONFIRM untuk snapshot tertentu**  
   - `checked_at`, `snapshot_id`, `captured_at`, `snapshot_age_sec`, valid/expired
   - per ticker: `confirm_label` + `confirm_reasons`

**LOCKED:** CONFIRM tidak boleh mengubah tampilan PLAN (ranking/score/group) untuk besok.

## Next
### Weekly Swing
- 03_WS_DATA_MODEL_MARIADB.md