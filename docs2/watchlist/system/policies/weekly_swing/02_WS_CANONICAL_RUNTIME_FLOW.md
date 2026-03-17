# 02 — WS Canonical Runtime Flow: PLAN, RECOMMENDATION, and CONFIRM

> Compatibility note: nama file lama dipertahankan demi kestabilan referensi.
>
> Secara normatif, dokumen ini adalah **canonical runtime flow watchlist Weekly Swing**, bukan dokumen execution engine, bukan order placement, dan bukan domain broker execution.
>
> Isi normatif tetap mengikuti baseline aktif `PLAN -> RECOMMENDATION -> CONFIRM`.

## Purpose

Dokumen ini menetapkan urutan canonical Weekly Swing watchlist untuk tiga lapisan output yang berbeda:

1. **PLAN**
2. **RECOMMENDATION**
3. **CONFIRM**

Dokumen ini adalah owner untuk urutan canonical antar lapisan tersebut dan boundary normatif di antaranya.

## A. Timeline (LOCKED)

### A1. PLAN (EOD)
Pada akhir siklus EOD untuk suatu `trade_date`, sistem membentuk PLAN final yang immutable.

### A2. RECOMMENDATION (PLAN-derived)
Setelah PLAN final tersedia, sistem **MAY** membentuk RECOMMENDATION dari immutable PLAN output untuk `trade_date` yang sama.

RECOMMENDATION:
- dibentuk tanpa membaca CONFIRM;
- dapat tersedia walaupun CONFIRM belum dijalankan;
- dapat bernilai kosong walaupun PLAN masih memiliki item pada `TOP_PICKS` dan/atau `SECONDARY`.

### A3. CONFIRM (Overlay)
User atau sistem dapat menjalankan CONFIRM terhadap ticker yang masih valid sebagai candidate PLAN pada `trade_date` yang sama.

CONFIRM:
- membaca PLAN immutable;
- tidak membaca recommendation sebagai syarat eligibility candidate;
- tidak membentuk PLAN baru;
- tidak mengubah RECOMMENDATION yang sudah ada.

## B. PLAN (EOD) (LOCKED)

PLAN adalah source-of-truth utama untuk Weekly Swing watchlist pada `trade_date` yang sama.

PLAN final **MUST** immutable sebelum RECOMMENDATION atau CONFIRM dijalankan.

PLAN final **MUST** menjadi referensi utama bagi:
1. RECOMMENDATION
2. CONFIRM

## C. RECOMMENDATION (PLAN-derived) (LOCKED)

RECOMMENDATION adalah lapisan watchlist yang dibentuk hanya dari immutable PLAN output untuk `trade_date` yang sama.

RECOMMENDATION:
- **MUST** berasal hanya dari PLAN;
- **MUST NOT** membaca hasil CONFIRM;
- **MUST NOT** mengubah membership PLAN candidate;
- **MAY** tersedia tanpa CONFIRM;
- **MAY** kosong walaupun `TOP_PICKS` dan/atau `SECONDARY` tidak kosong.

## D. CONFIRM (Overlay) (LOCKED)

CONFIRM eligibility **MUST** ditentukan oleh membership ticker pada candidate PLAN yang valid, bukan oleh membership recommendation.

Dengan demikian:
- ticker recommended dapat di-confirm;
- ticker non-recommended tetapi masih valid sebagai candidate PLAN juga dapat di-confirm;
- ticker di luar candidate PLAN **MUST NOT** di-confirm.

Jika ticker berada pada recommendation set lalu memiliki hasil CONFIRM yang valid, maka hasil CONFIRM **MAY** dipakai consumer sebagai **strengthening signal**.

Jika ticker tidak berada pada recommendation set tetapi masih valid sebagai candidate PLAN, maka hasil CONFIRM hanya bermakna sebagai **candidate validation**.

## E. Output Canonical (LOCKED)

Weekly Swing memiliki tiga output canonical yang berbeda:
- PLAN Output
- RECOMMENDATION Output
- CONFIRM Output

Consumer **MAY** menampilkan kombinasi state berikut:
- PLAN only
- PLAN + RECOMMENDATION
- PLAN + CONFIRM (candidate still PLAN-rooted)
- PLAN + RECOMMENDATION + CONFIRM

Kombinasi berikut tidak valid:
- RECOMMENDATION tanpa PLAN item
- CONFIRM tanpa candidate PLAN

## Final Invariants (LOCKED)

1. PLAN **MUST** final dan immutable sebelum RECOMMENDATION dibentuk.
2. RECOMMENDATION **MUST** berasal hanya dari immutable PLAN output.
3. RECOMMENDATION **MUST** dapat tersedia tanpa CONFIRM.
4. RECOMMENDATION **MAY** kosong walaupun group prioritas PLAN tidak kosong.
5. CONFIRM eligibility **MUST** berasal dari candidate PLAN membership.
6. CONFIRM **MUST NOT** mengubah PLAN.
7. CONFIRM **MUST NOT** mengubah RECOMMENDATION membership, rank, score, label, atau hash.
8. Jika ticker recommended lalu confirmed, hasil CONFIRM hanya bertindak sebagai strengthening signal dan bukan pembentuk recommendation.
