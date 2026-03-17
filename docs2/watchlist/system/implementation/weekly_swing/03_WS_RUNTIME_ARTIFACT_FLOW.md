# 03 — WS Runtime Artifact Flow

## Purpose

Dokumen ini menjelaskan aliran artifact runtime yang harus dihasilkan aplikasi watchlist.

## Canonical Flow

### Step 1 — Build PLAN
Input EOD yang sah dibaca dan divalidasi.
Output `PLAN` dimaterialize sebagai artifact immutable untuk `trade_date`.

### Step 2 — Build RECOMMENDATION
Artifact `PLAN` dibaca ulang.
Output `RECOMMENDATION` dibentuk tanpa membaca `CONFIRM`.

### Step 3 — Build CONFIRM
Permintaan confirm mengikat ticker ke candidate `PLAN` yang sah.
Output `CONFIRM` dibentuk tanpa memutasi `PLAN` atau `RECOMMENDATION`.

### Step 4 — Build Composite View
Consumer view dapat menggabungkan:
- `PLAN`
- `RECOMMENDATION`
- `CONFIRM`

Composite view tidak boleh mengubah semantics artifact asal.

## Runtime Keys

Kunci minimum yang harus konsisten di semua artifact:
- `strategy_code`
- `trade_date`
- `policy_code`
- `paramset_version`
- `ticker`

## Allowed States

- `PLAN only`
- `PLAN + RECOMMENDATION`
- `PLAN + CONFIRM`
- `PLAN + RECOMMENDATION + CONFIRM`

## Invalid States

- `RECOMMENDATION without PLAN`
- `CONFIRM without PLAN candidate`
