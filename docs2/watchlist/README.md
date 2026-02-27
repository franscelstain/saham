# Watchlist Documentation

Folder utama: `docs/watchlist/`.

Dokumentasi ini mencakup sistem watchlist secara global (struktur, governance, database watchlist, dan dokumen per-policy).

## Prinsip sistem (scope saat ini: Weekly Swing EOD)
- Berbasis **EOD (end-of-day)** untuk menghasilkan rekomendasi **Weekly Swing**.
- Target kualitas aplikasi **95–100%**.
- Dua tahap tegas:
  - **PLAN** dibuat dari data EOD hari ini untuk rekomendasi **besok** (next trading day), disimpan sebagai snapshot.
  - **CONFIRM** memakai data runtime (jam saat ini) sebagai pengecekan keyakinan yang sifatnya sesaat (bisa berubah 5–10 menit) dan **tidak boleh mengubah atau mempengaruhi hasil PLAN**.
- Keputusan eksekusi tetap **manual** di luar aplikasi; **CONFIRM** hanya saran tambahan untuk meningkatkan keyakinan.

## Navigasi
- `policy.md` — governance policy dan standar dokumentasi (lintas strategi).
- `db/` — schema + seed database watchlist (dibaca berurutan mulai 01).
- `policies/` — katalog policy (lihat `policies/README.md`).
