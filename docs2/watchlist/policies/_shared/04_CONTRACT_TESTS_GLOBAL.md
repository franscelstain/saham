# 04 — Contract Tests (Global)

Test global yang wajib ada:
- Anti-drift schema: kolom table watchlist sesuai dokumen schema.
- Paramset validator: semua param wajib, tipe benar, provenance lengkap.
- Reason codes & fail codes: setiap code yang dipakai output harus ada di dictionary.
- Hash reproducibility: input sama menghasilkan hash sama (canonical string locked).

Test policy-spesifik berada di folder policy masing-masing (contoh: Weekly Swing Doc 11).
