# Policy: NO_TRADE (SOP)

Dokumen ini hanya mendefinisikan aturan spesifik policy. Aturan universal wajib lihat `watchlist.md` bagian **Global Contract**.

Kontrak lintas-policy ada di `watchlist.md`.

---

## 0) Tujuan
Menonaktifkan NEW ENTRY secara deterministik ketika kondisi “bukan saatnya entry”.

---

## 1) Prinsip
- NO_TRADE adalah policy yang dipilih (aktif), bukan side effect.
- Saat aktif:
  - `groups.top_picks=[]`
  - `groups.secondary=[]`
  - `recommendations=[]`
  - `groups.watch_only` dan `groups.avoid` boleh diisi untuk monitoring 

---

## Hard triggers (LOCKED)
`NO_TRADE` **tidak punya trigger otomatis**. Policy ini adalah **manual off-switch** yang dipilih user.

Jika canonical EOD belum ready, itu **bukan** alasan memakai `NO_TRADE`; kondisi tersebut ditangani oleh Global Contract (`EOD_NOT_READY`):
- `recommendations=[]` wajib
- groups boleh monitoring dengan flag `EOD_NOT_READY`

## Invalidation & monitoring (EOD-only)
- NO_TRADE adalah policy manual-only: tidak ada entry, tidak ada recommendations.

## CONFIRM hints (intraday, non-binding)
CONFIRM hanya boleh approve/reject/adjust timing; tidak boleh mengubah PLAN.

- Jika user memilih NO_TRADE, CONFIRM tidak relevan (selalu no entry).
