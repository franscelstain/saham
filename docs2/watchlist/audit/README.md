# Watchlist Audit Docs

Folder ini berisi guardrail audit untuk domain `watchlist`.

## Fungsi Folder Audit

Audit docs dipakai untuk:
- mengunci scope aktif;
- menjaga boundary `PLAN / RECOMMENDATION / CONFIRM`;
- menilai sinkronisasi system docs;
- mencegah watchlist melebar ke portfolio, execution, atau market-data internals;
- memastikan dokumen tetap realistis untuk data provider gratis atau input manual.

## Bukan Source of Truth Bisnis

Audit docs bukan owner rule bisnis.

Source of truth untuk membangun sistem tetap berada di:
- [`../system/`](../system/)

## File Inti Audit

- `WATCHLIST_AUDIT_FOUNDATION.md`
- `WATCHLIST_SCOPE_LOCK.md`
- `WATCHLIST_OWNER_MATRIX.md`
- `WATCHLIST_AUDIT_CHECKLIST_FINAL.md`
- `WATCHLIST_AUDIT_SHORT.md`
- `WATCHLIST_AUDIT_PROMPT_STANDARD.md`
- `WATCHLIST_CHANGE_IMPACT_MATRIX.md`

## Cara Pakai

1. finalkan audit foundation;
2. audit owner docs system inti;
3. patch system docs yang FAIL/PARTIAL;
4. audit ulang dengan checklist final;
5. freeze `weekly_swing` jika boundary sudah kuat.


## Baseline Status

Folder audit ini diperlakukan sebagai audit baseline aktif untuk watchlist. Revisi berikutnya harus dinilai terhadap baseline ini dan tidak boleh mengubah scope inti tanpa revisi eksplisit pada foundation.


## Additional Layer

Folder `implementation/` berisi audit untuk implementation guidance watchlist dan default-nya dibaca sebagai **Layer B**. Layer ini terpisah dari audit system docs.

Layer C hanya aktif bila ada bukti code/app/runtime nyata yang cukup. Examples, fixtures, SQL support, dan schema docs tidak otomatis mengaktifkan Layer C.


## Layer Activation Reference

Gunakan [`LAYER_ACTIVATION_RULE.md`](../LAYER_ACTIVATION_RULE.md) untuk menentukan apakah paket harus dibaca sebagai Layer A, B, atau C.
