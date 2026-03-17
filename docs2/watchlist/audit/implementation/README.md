# Watchlist Implementation Audit

Folder ini berisi baseline audit untuk **implementation guidance watchlist (Layer B)** dan guardrail saat review implementasi nyata mulai tersedia. Audit ini terpisah dari audit `system/`.

Tujuan folder ini:
- menjaga implementasi tetap tunduk pada baseline [`../../system/`](../../system/);
- menjaga aplikasi watchlist tetap **watchlist only**;
- memastikan implementasi `weekly_swing` tidak bocor ke portfolio, execution, atau market-data internals;
- memberi checklist review untuk module, API, persistence, test, delivery, dan transisi ke review code/app nyata.

Folder ini **bukan** owner rule bisnis watchlist. Source of truth tetap berada di [`../../system/`](../../system/).

Dokumen inti:
1. `WATCHLIST_IMPLEMENTATION_AUDIT_FOUNDATION.md`
2. `WATCHLIST_IMPLEMENTATION_CHECKLIST_FINAL.md`
3. `WATCHLIST_IMPLEMENTATION_PROMPT_STANDARD.md`
4. `WATCHLIST_IMPLEMENTATION_CHANGE_IMPACT_MATRIX.md`

Dokumen tambahan:
5. `WATCHLIST_IMPLEMENTATION_AUDIT_SHORT.md`
6. `_refs/WATCHLIST_IMPLEMENTATION_AUDIT_EXAMPLE.md`

Status saat ini:
- folder ini adalah **implementation audit baseline**;
- baseline ini dipakai pertama-tama untuk menilai **implementation guidance**;
- review code/app nyata baru menjadi audit Layer C bila ZIP memang memuat bukti implementasi nyata yang cukup;
- tanpa code/app/runtime evidence nyata, audit **harus tetap dibaca sebagai Layer B**, bukan Layer C;
- baseline ini tidak boleh mengubah freeze baseline bisnis `weekly_swing` pada [`../../system/`](../../system/).


## Code/App Real Review Notes

Saat artefak code atau aplikasi nyata sudah tersedia, audit implementasi **SHOULD** menilai temuan terhadap service boundary, serializer/presenter boundary, validator manual input, persistence boundary, dan payload runtime yang benar-benar dihasilkan aplikasi.

Aktivasi review code/app nyata **tidak otomatis** terjadi hanya karena ada examples, fixtures, SQL support, schema markdown, atau sample payload. Artefak seperti itu tetap dihitung sebagai support Layer A/B sampai ada bukti implementasi nyata yang cukup.

Contoh pola review nyata tersedia di folder `_refs/`.
