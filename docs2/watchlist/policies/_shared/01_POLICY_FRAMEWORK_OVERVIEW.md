# 01 — Policy Framework Overview (Global)

Dokumen ini menjelaskan kontrak global untuk seluruh policy watchlist.

Yang dianggap **global**:
- Struktur `paramset` (kontrak JSON level global)
- Validator & aturan anti-drift
- Urutan eksekusi canonical lintas policy (PLAN selalu EOD snapshot; CONFIRM tidak mengubah PLAN)
- Kontrak auditability (reason codes, fail codes, batch hash)

Dokumen policy spesifik (contoh Weekly Swing) berada di:
- `../weekly_swing/` (baca berurutan dari `../weekly_swing/01_WS_BOOK_OVERVIEW.md`).
