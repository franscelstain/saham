# Runtime Output Examples — Weekly Swing (Reference)

## Reference status
Dokumen ini membantu engineer, reviewer, dan UI developer melihat bentuk output runtime Weekly Swing yang biasanya muncul dalam praktik. Dokumen ini bukan owner kontrak output; owner normatif tetap berada pada dokumen bernomor dan file contoh JSON di folder `examples/` dipakai sebagai payload referensial yang patuh kontrak.

## Purpose
Gunakan dokumen ini untuk tiga kebutuhan praktis:
1. melihat shape PLAN dan CONFIRM tanpa harus membuka kontrak penuh lebih dulu,
2. memahami field mana yang biasanya dibaca oleh UI, reviewer, atau audit,
3. memilih contoh JSON yang paling dekat dengan skenario implementasi yang sedang dikerjakan.

## Inputs
- dokumen normatif output runtime dan persistence Weekly Swing,
- file JSON contoh di folder `examples/`,
- contract test checklist saat bentuk output sedang diverifikasi.

## Outputs
- quick shape guide PLAN dan CONFIRM,
- contoh pembacaan field yang paling sering dipakai,
- pointer ke payload JSON yang paling berguna untuk implementasi.

## A. Quick shape guide

### PLAN output shape (reference)
PLAN runtime umumnya memuat tiga blok besar:
- `meta`: konteks run seperti `policy`, `asof_eod_date`, `trade_date`, `paramset_id`, `plan_hash`, `generated_at`, dan `fail_code` bila run gagal atau NO_TRADE,
- `items[]`: kandidat hasil seleksi berisi `ticker`, `rank`, `group_semantic`, `score_total`, `scores`, `levels`, `flags`, dan `reasons`,
- `summary`: ringkasan hasil run seperti jumlah kandidat layak, pembagian `top_picks` / `secondary` / `watch_only`, dan sinyal `no_trade`.

Shape ini berguna saat engineer membangun serializer runtime, saat reviewer ingin membaca hasil PLAN dengan cepat, atau saat UI perlu memisahkan metadata run dari data kandidat per-item.

### CONFIRM output shape (reference)
CONFIRM runtime umumnya memuat tiga blok besar:
- `meta`: konteks pembacaan snapshot seperti `policy`, `checked_at`, `snapshot_ts`, `snapshot_age_sec`, dan informasi sumber snapshot,
- `items[]`: hasil overlay per ticker berisi `ticker`, `label`, dan `reasons`,
- `summary`: ringkasan jumlah outcome seperti `confirmed_count`, `neutral_count`, `caution_count`, dan `delay_count`.

Shape ini berguna saat engineer membangun endpoint CONFIRM, saat reviewer ingin membaca outcome intraday secara cepat, atau saat UI perlu membedakan hasil PLAN dari hasil overlay intraday.

## B. Concrete examples from `examples/`

### 1. PLAN runtime example
File: `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`

Snippet ringkas yang biasanya cukup untuk desain endpoint atau kartu UI:

```json
{
  "meta": {"policy": "weekly_swing", "trade_date": "2026-03-13", "plan_hash": "..."},
  "items": [
    {"ticker": "AAA", "rank": 1, "group_semantic": "TOP_PICKS", "score_total": 0.75},
    {"ticker": "BBB", "rank": 2, "group_semantic": "TOP_PICKS", "score_total": 0.70}
  ],
  "summary": {"top_picks": 2, "secondary": 1, "watch_only": 1, "avoid": 1}
}
```

Contoh ini menunjukkan run PLAN yang berhasil dengan lima kandidat dan pembagian group yang jelas:
- `AAA` dan `BBB` berada di `TOP_PICKS`,
- `CCC` berada di `SECONDARY`,
- `DDD` berada di `WATCH_ONLY`,
- `EEE` berada di `AVOID` karena gagal guard likuiditas.

Field yang paling sering dipakai implementasi:
- `meta.plan_hash` untuk jejak audit run,
- `items[].rank` dan `items[].group_semantic` untuk urutan serta grouping,
- `items[].scores` untuk inspeksi alasan scoring,
- `items[].levels` untuk referensi entry band, stop, dan target awal,
- `summary` untuk ringkasan cepat pada UI atau laporan.

### 2. CONFIRM runtime example
File: `examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`

Snippet ringkas yang biasanya cukup untuk desain overlay atau tampilan status intraday:

```json
{
  "meta": {"policy": "weekly_swing", "snapshot_age_sec": 90},
  "items": [
    {"ticker": "AAA", "label": "CONFIRMED"},
    {"ticker": "BBB", "label": "CAUTION"}
  ],
  "summary": {"confirmed_count": 1, "caution_count": 1, "delay_count": 0}
}
```

Contoh ini menunjukkan lima outcome CONFIRM yang berbeda dalam satu snapshot:
- `AAA` = `CONFIRMED`,
- `BBB` = `CAUTION`,
- `CCC` = `NEUTRAL`,
- `DDD` = `DELAY` karena harga runtime tidak tersedia,
- `EEE` = `DELAY` karena field aggregate wajib belum lengkap.

Field yang paling sering dipakai implementasi:
- `meta.snapshot_age_sec` untuk membaca umur snapshot,
- `items[].label` untuk outcome singkat di UI,
- `items[].reasons[].code` dan `payload` untuk penjelasan hasil,
- `summary` untuk melihat distribusi outcome overlay.

### 3. PLAN–CONFIRM pair example
File: `examples/WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json`

Mini pair di bawah ini cukup untuk melihat invariant yang paling penting:

```json
{
  "plan_hash_before": "abc123...",
  "plan_hash_after": "abc123...",
  "confirm_items": [
    {"ticker": "AAA", "label": "CONFIRMED"},
    {"ticker": "BBB", "label": "CAUTION"}
  ]
}
```

Contoh pasangan ini berguna saat engineer atau reviewer ingin memastikan batas PLAN dan CONFIRM tetap terjaga. Bagian `invariant` menunjukkan `plan_hash_before` dan `plan_hash_after` tetap identik, sehingga pembaca bisa melihat secara konkret bahwa CONFIRM dibaca sebagai overlay di atas PLAN, bukan penulisan ulang PLAN.

## C. Persistence reading notes
Dokumen ini tidak menetapkan schema persistence final, tetapi pembaca biasanya perlu memperhatikan pola field berikut saat menelusuri artefak simpan:
- identitas run (`policy`, `trade_date`, `generated_at`, `checked_at`),
- identitas item (`ticker`, `rank`, `group_semantic`, `label`),
- jejak audit (`plan_hash`, reason code, snapshot metadata),
- outcome ringkas yang nanti perlu ditampilkan ulang atau diaudit.

Saat implementasi storage, gunakan dokumen normatif persistence sebagai owner final. Dokumen ini hanya membantu pembaca mengenali bentuk data yang lazim mengalir dari runtime ke artefak simpan.

## D. How to use this document during implementation
- Saat membangun endpoint PLAN, mulai dari contoh PLAN runtime untuk melihat blok `meta`, `items[]`, dan `summary` yang biasanya perlu dipisahkan dengan jelas.
- Saat membangun endpoint CONFIRM, mulai dari contoh CONFIRM runtime untuk melihat bagaimana label hasil dan reason payload biasanya disajikan.
- Saat membangun UI atau contract test, gunakan pair example untuk memeriksa bahwa hasil CONFIRM tidak menulis ulang PLAN.
- Saat ada perbedaan antara contoh dan dokumen normatif, dokumen normatif yang berlaku dan contoh di sini harus disesuaikan.
