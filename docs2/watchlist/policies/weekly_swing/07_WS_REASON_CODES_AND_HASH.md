# 07 — Reason Codes & Data Batch Hash — Weekly Swing

## Purpose
Dokumen ini mengunci:
1) Reason codes yang dipakai Weekly Swing untuk **PLAN** dan **CONFIRM**
2) Cara menghitung `data_batch_hash` untuk reproducibility (PLAN)

## Inputs
- reason dictionary seed: `db/REASON_CODES_SEED.sql` (policy_code='WS')
- `hash_contract` di params_json

---

## A. Reason Codes (LOCKED)

### A1) Rules umum
- PLAN items **wajib** memiliki `reason_codes_json` (array reason_code).
- Minimal 1 reason per ticker.
- `selection_reason_code` adalah ringkasan untuk UI (1 kode).

### A2) Reason codes khusus CONFIRM (LOCKED)

CONFIRM memakai intraday snapshot manual. Failure yang harus eksplisit:

- `WS_SNAPSHOT_MISSING` (BLOCK, CONFIRM)  
  Snapshot belum diinput → CONFIRM wajib `decision=DELAY`.

- `WS_STALE` (BLOCK, CONFIRM)  
  Snapshot melewati TTL (15 menit; `snapshot_max_age_sec=900`) → CONFIRM wajib downgrade `decision=DELAY` (atau NO_TRADE sesuai policy).

Catatan:
- Kode lain seperti `WS_SPR_NA`, `WS_SPR_WIDE`, `WS_OUT_BAND` bersifat WARN/INFO (overlay), namun **tidak boleh** mengubah PLAN.

---

## B. Data Batch Hash (PLAN) — canonical (LOCKED)

Payload hashing dibangun dari ticker yang memenuhi required_fields (required_ok).
Canonical per ticker (ordered by hash_contract.order_by):
- ticker_id
- close (scaled)
- hh20 (scaled)
- roc20 (scaled)
- atr14_pct (scaled)
- dv20_idr (scaled)

Null handling:
- `EXCLUDE_FROM_HASH_PAYLOAD` (locked)

Hash function:
- `SHA256(canonical_string)`

### Reproducibility (LOCKED)
- Hash harus sama jika input sama + paramset sama.
- Hash berubah jika salah satu field payload berubah.

---

## Failure modes
- Hash mismatch → Contract/Test fail.

