# 04 — Contract Tests (Global)

Test global yang wajib ada:
- Anti-drift schema: kolom table watchlist sesuai `../../db/02_DB_SCHEMA_MARIADB.md`.
- Paramset validator: semua param wajib, tipe benar, provenance lengkap.
- Reason codes & fail codes: setiap code yang dipakai output harus ada di dictionary.
- Hash reproducibility: input sama menghasilkan hash sama (canonical string locked).

Test policy-spesifik berada di folder policy masing-masing (contoh: Weekly Swing `../weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md`).
