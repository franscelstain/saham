# 10 — WS Confirm Overlay

## Purpose

Dokumen ini menetapkan aturan normatif untuk layer **CONFIRM** pada Weekly Swing.

CONFIRM adalah overlay tambahan yang membaca immutable PLAN output untuk `trade_date` yang sama dan mengevaluasi ticker yang masih valid sebagai candidate PLAN.

CONFIRM tidak membentuk recommendation, tidak mengubah recommendation, dan dapat berlaku pada ticker recommended maupun non-recommended selama ticker tersebut masih valid sebagai candidate PLAN.

## Scope

Dokumen ini mencakup:
1. syarat eligibility CONFIRM;
2. binding CONFIRM ke PLAN immutable;
3. overlay evaluation per ticker;
4. relationship CONFIRM terhadap RECOMMENDATION;
5. strictness boundary agar CONFIRM tidak bocor ke recommendation.

## Step 1 — Bind Immutable PLAN Result

Sebelum CONFIRM dievaluasi, sistem **MUST** lebih dahulu melakukan binding ke PLAN immutable yang sah untuk `trade_date` yang sama.

Jika ticker tidak ditemukan pada candidate PLAN yang valid untuk `trade_date` yang sama, maka CONFIRM **MUST** ditolak.

CONFIRM **MUST NOT** menambah candidate baru dari luar PLAN.

## Step 2 — Relationship to RECOMMENDATION

Eligibility CONFIRM **MUST** ditentukan oleh candidate PLAN membership, bukan oleh recommendation membership.

Akibatnya:
- ticker yang direkomendasikan dapat di-confirm;
- ticker yang tidak direkomendasikan tetapi masih valid sebagai candidate PLAN juga dapat di-confirm;
- ticker di luar candidate PLAN tidak dapat di-confirm.

RECOMMENDATION yang kosong **MUST NOT** dianggap menghalangi CONFIRM terhadap candidate PLAN yang sah.

## Step 3 — Evaluate Overlay Conditions per Item

Evaluasi CONFIRM hanya berlaku pada item yang berhasil di-bind ke PLAN dan tidak boleh menambah ticker baru.

## Step 4 — Confirm Result Semantics

### A. Recommended + Confirmed
Jika ticker berada pada recommendation set dan juga memiliki hasil CONFIRM yang valid, maka hasil CONFIRM **MAY** dibaca consumer sebagai **strengthening signal**.

### B. Non-Recommended + Confirmed
Jika ticker tidak berada pada recommendation set tetapi masih valid sebagai candidate PLAN lalu memiliki hasil CONFIRM yang valid, maka hasil tersebut hanya bermakna sebagai **candidate validation**.

### C. Invalid Case
Jika ticker tidak berada pada candidate PLAN yang sah, maka hasil CONFIRM untuk ticker tersebut **MUST NOT** dianggap valid.

## Strictness Boundary (LOCKED)

1. CONFIRM **MUST** membaca immutable PLAN output.
2. CONFIRM eligibility **MUST** berasal dari candidate PLAN membership.
3. CONFIRM **MUST NOT** menggunakan recommendation membership sebagai syarat eligibility.
4. CONFIRM **MUST NOT** membentuk recommendation baru.
5. CONFIRM **MUST NOT** mengubah recommendation membership, rank, score, label, atau hash.
6. CONFIRM **MUST NOT** menambah ticker dari luar PLAN.

## Final Rules

1. CONFIRM hanya sah untuk ticker yang berhasil di-bind ke candidate PLAN yang valid.
2. Ticker recommended dapat di-confirm.
3. Ticker non-recommended tetap dapat di-confirm selama masih merupakan candidate PLAN.
4. Recommendation yang kosong tidak membatalkan eligibility CONFIRM terhadap candidate PLAN.
5. Jika ticker recommended lalu confirmed, hasil CONFIRM hanya bertindak sebagai strengthening signal.
6. Jika ticker non-recommended lalu confirmed, hasil CONFIRM hanya bertindak sebagai candidate validation.
7. CONFIRM tidak pernah membentuk atau mengubah recommendation.
