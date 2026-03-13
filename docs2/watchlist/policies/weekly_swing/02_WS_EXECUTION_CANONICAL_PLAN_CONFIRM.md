# 02 — Execution Canonical: PLAN (EOD) → CONFIRM (Intraday Snapshot) — Weekly Swing

Dokumen ini mengunci alur eksekusi Weekly Swing menjadi dua tahap yang tegas:

- **PLAN** dibuat dari data **EOD hari ini** untuk rekomendasi **besok**.
- **CONFIRM** memakai **intraday snapshot manual** (bukan real-time) berbasis **intraday aggregate** (last/chg/volume/turnover), **bukan** order book ladder.

**LOCKED:** CONFIRM tidak boleh mengubah PLAN.

## LOCKED — Invariant: PLAN Immutability (wajib bisa dites)

Saat CONFIRM dijalankan, **PLAN harus identik** sebelum vs sesudah eksekusi.

Definisi “identik” (LOCKED):
- `plan_hash_before == plan_hash_after`
- `items[]` (urutan, `rank`, `group_semantic`, `score_total`, `levels.*`, `flags.*`, `reasons[]`) **tidak berubah**
- Tidak boleh ada UPDATE/DELETE pada storage PLAN untuk `trade_date` yang sama.

Definisi `plan_hash` (LOCKED):
- `plan_hash = SHA256( canonical_plan_payload(items[]) )`
- `canonical_plan_payload(items[])` **ditentukan penuh oleh dokumen ini**; dokumen referensi hanya boleh memberi contoh, bukan aturan baru.

Aturan canonical payload (LOCKED):
1. Bentuk yang di-hash adalah array `items[]` setelah ranking final.
2. Jika run PLAN berstatus `NO_TRADE`, maka `items[]` **wajib** `[]`; canonical payload untuk hash adalah array kosong.
3. Urutan item **wajib**: `rank ASC`, lalu `ticker ASC`.
4. Untuk setiap item, field yang boleh masuk hash **hanya**:
   - `ticker`
   - `rank`
   - `group_semantic`
   - `score_total`
   - `levels.entry_ref`
   - `levels.entry_band_low`
   - `levels.entry_band_high`
   - `levels.stop_price`
   - `levels.tp1_price`
   - `flags.eligible`
   - `flags.hidden`
   - `reasons[]` dengan field reason yang boleh ikut hanya `code` dan `severity`
5. `message` dan `payload` pada reason **dilarang** masuk hash.
6. Urutan `reasons[]` per item **wajib**: severity desc (`BLOCK` > `WARN` > `INFO`), lalu `code ASC`.
7. Format angka **wajib fixed**:
   - `score_total` 4 decimal
   - semua `levels.*` 4 decimal
8. Serialisasi canonical payload **wajib**:
   - JSON UTF-8
   - object keys diurutkan alfabetis (`sort_keys=true`)
   - tanpa whitespace (`separators=(',', ':')`)
9. Hasil hash ditulis sebagai SHA-256 hex lowercase.

Scope write yang diizinkan saat CONFIRM (LOCKED):
- hanya menulis `confirm_result` / `confirm_reasons` / `confirm_meta` (storage terpisah)
- dilarang menulis ulang PLAN fields, termasuk “reorder ranking” atau “recompute score_total”

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
- output: `plan_items[]` dengan `rank`, `group_semantic`, `score_total`, `reasons[]`

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
- Jika `checked_at - effective_captured_at > snapshot_max_age_sec` (LOCKED: 900 detik) → snapshot **EXPIRED**
  - CONFIRM wajib menghasilkan `label = DELAY` + `WS_STALE`
- Jika snapshot tidak ada → `label = DELAY` + `WS_SNAPSHOT_MISSING`
- `NO_TRADE` tidak dipakai sebagai label hasil CONFIRM; `NO_TRADE` hanya berlaku pada status run PLAN/global selection.

---

## D. Immutability Contract (LOCKED)

CONFIRM overlay hanya boleh menambah **output CONFIRM terpisah**.

Dilarang:
- mengubah PLAN record/fields
- reorder ranking
- mengubah `score_total`
- mengubah `group_semantic`

## LOCKED — DB write scope (CONFIRM)
Untuk mencegah writeback tidak sengaja, ruang lingkup operasi DB saat CONFIRM adalah:
- **READ-ONLY** terhadap seluruh persistence PLAN (run/header/items/levels/reasons) untuk `trade_date=T`.
- **WRITE-ONLY** ke persistence CONFIRM (confirm_run + confirm_items + confirm_reasons) sebagai output terpisah.
- **Dilarang** melakukan `UPDATE`/`DELETE` pada data PLAN dalam proses CONFIRM, termasuk perubahan status, rank, score, group, atau levels.
- Jika implementasi memakai transaksi DB, maka transaksi CONFIRM tidak boleh mencakup statement yang memodifikasi PLAN.

**Contract test wajib:**
- `plan_hash_before == plan_hash_after`
- Test suite **wajib** memiliki audit “DB Write-Scope” (lihat [`_refs/WS_CONTRACT_TESTS_SPEC.md`](_refs/WS_CONTRACT_TESTS_SPEC.md) Test 2C). Tanpa audit ini, kontrak dianggap **belum terpenuhi**.

## LOCKED — PLAN Hash Scope (Immutability)
Definisi `meta.plan_hash` harus **mekanis** dan hanya memiliki **satu** sumber kebenaran.

**Sumber kebenaran tunggal (LOCKED):**
- `meta.plan_hash = SHA256(canonical_plan_payload(items[]))`
- Canonical payload dan aturan serialisasi `meta.plan_hash` mengikuti definisi LOCKED pada dokumen ini.
- Urutan item canonical **wajib**: `rank ASC, ticker ASC`.
- `message` dan `payload` pada `reasons[]` **dilarang** masuk ke `meta.plan_hash`.

**Larangan interpretasi lain (LOCKED):**
- `meta.plan_hash` **tidak boleh** memasukkan field header/run-level seperti `policy_code`, `trade_date`, `param_id`, `data_batch_hash`, atau `plan_version`.
- `meta.plan_hash` **tidak boleh** memakai urutan `ticker_code ASC` sebagai pengganti urutan canonical resmi.

**Jika implementasi membutuhkan hash persistence/run-level terpisah:**
- boleh membuat hash lain dengan nama berbeda (contoh: `plan_state_hash`),
- tetapi hash tersebut **bukan** `meta.plan_hash` dan **tidak boleh** dipakai untuk menguji invariant immutability pada dokumen ini.

---

## E. Output Canonical (LOCKED)

Output akhir di UI harus menampilkan dua hal terpisah:

1) **PLAN untuk besok (trade_date=T)**  
   - ranking, group_semantic, score_total, reasons PLAN

2) **CONFIRM untuk snapshot tertentu**  
   - `checked_at`, `snapshot_id`, `captured_at`, `snapshot_age_sec`, valid/expired
   - per ticker: `label` + `reasons[]`

**LOCKED:** CONFIRM tidak boleh mengubah tampilan PLAN (ranking/score/group) untuk besok.

## Next
### Weekly Swing
- 03_WS_DATA_MODEL_MARIADB.md