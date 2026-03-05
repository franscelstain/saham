# 11 — Tabel Input Manual Intraday Snapshot (CONFIRM) — Weekly Swing

## Purpose
Mengunci struktur tabel, semantics timestamp, selection rule, TTL rule, dan write discipline untuk snapshot intraday manual yang dipakai oleh CONFIRM pada policy Weekly Swing.

## Scope
Dokumen ini hanya mengatur **input intraday snapshot untuk CONFIRM**.
Dokumen ini tidak mengubah definisi PLAN, ranking, score, bucket, atau paramset.

## Inputs
- snapshot manual yang diinput operator / proses manual,
- trade date PLAN yang akan dikonfirmasi,
- policy code `WS`.

## Outputs
- struktur tabel snapshot resmi,
- aturan pemilihan snapshot aktif,
- aturan TTL,
- aturan append-only,
- aturan field contract vs non-contract.

## Principles (LOCKED)

1. **CONFIRM bukan real-time engine.**
   CONFIRM selalu membaca snapshot terakhir yang valid menurut kontrak ini.

2. **Snapshot manual adalah source of truth CONFIRM.**
   Jika snapshot diambil di masa lalu dan baru diinput sekarang, validitasnya tetap ditentukan oleh timestamp contract, bukan oleh opini operator.

3. **Snapshot wajib punya dua timestamp.**
   - `captured_at` = waktu data intraday diambil.
   - `inserted_at` = waktu record masuk database.

4. **Anti-manipulation timestamp rule (LOCKED).**
   - `effective_captured_at = LEAST(captured_at, inserted_at)`
   - TTL dihitung dari `effective_captured_at`, bukan dari field lain.

5. **TTL CONFIRM (LOCKED).**
   - `snapshot_max_age_sec = 900`
   - Jika `NOW() - effective_captured_at > 900`, snapshot dianggap stale/expired.
   - CONFIRM wajib menghasilkan `label = DELAY` dan reason `WS_STALE`.

6. **Append-only persistence (LOCKED).**
   - `UPDATE` dan `DELETE` terhadap snapshot resmi dilarang.
   - Input ulang harus membuat row baru.

7. **CONFIRM hanya boleh memakai contract fields.**
   Field non-contract seperti orderbook depth, bid ladder, ask ladder, atau metadata tambahan boleh ikut terbawa pada payload mentah, tetapi tidak boleh memengaruhi hasil canonical CONFIRM kecuali kelak dikontrakkan secara resmi.

## Prerequisites
- [`10_WS_CONFIRM_OVERLAY.md`](10_WS_CONFIRM_OVERLAY.md)
- [`02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
- [`07_WS_REASON_CODES_AND_HASH.md`](07_WS_REASON_CODES_AND_HASH.md)

## A) Official Tables (LOCKED)

### A1) Snapshot header table — `watchlist_confirm_snapshots`
Fungsi: menyimpan metadata snapshot untuk satu `(policy_code, trade_date, captured_at)`.

Kolom minimum resmi:
- `snapshot_id`
- `policy_code`
- `trade_date`
- `captured_at`
- `inserted_at`
- `source`
- `note`
- `snapshot_hash`
- `created_at`

DDL referensi:
