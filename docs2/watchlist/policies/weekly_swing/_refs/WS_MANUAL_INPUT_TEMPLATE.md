# Manual Input Template (CONFIRM) — Weekly Swing (Reference)

## Purpose
Dokumen ini menyediakan template operasional referensial untuk menyiapkan input manual CONFIRM. Dokumen ini bukan owner kontrak input, bukan owner strictness, dan bukan pengganti aturan overlay normatif.

## Scope
Cakupan dokumen ini dibatasi pada contoh bentuk input manual yang praktis dipakai saat operasi atau review. Validitas final input tetap mengikuti dokumen normatif bernomor yang mengatur snapshot, overlay, dan runtime contract.

## Normative owner
Owner normatif untuk field, validasi, strictness, dan hasil overlay tetap berada pada dokumen bernomor Weekly Swing. Template ini hanya membantu penyusunan input manual yang konsisten dengan kontrak tersebut.

## Ringkasan field yang dipakai
Bagian ini merangkum field yang lazim dipakai saat menyiapkan input manual CONFIRM. Ringkasan ini tidak menetapkan daftar field final yang mengikat di luar dokumen normatif.

| Field | Fungsi praktis | Catatan baca |
|---|---|---|
| `ticker` | mengikat snapshot ke kandidat PLAN | sebaiknya cocok dengan identitas kandidat PLAN agar pembacaan overlay tidak salah sasaran |
| `last_price` | dasar evaluasi drift atau harga saat ini | jangan dicampur dengan harga referensi lain |
| `captured_at` | waktu data diambil | dipakai untuk menghitung umur snapshot |
| `effective_captured_at` | waktu efektif yang dipakai untuk penilaian stale | lebih aman dipakai untuk TTL bila tersedia |
| `volume_shares` | ukuran aktivitas berbasis volume | jangan ditukar dengan turnover |
| `turnover_idr` | ukuran aktivitas berbasis nilai | berguna untuk validasi kelengkapan aggregate |
| `source_name` / `snapshot_source` | jejak asal input | memudahkan audit manual |

## Catatan penggunaan
Catatan pada bagian ini membantu operator atau reviewer menggunakan template secara konsisten. Catatan ini tidak menetapkan schema persistence, strictness contract, atau rule overlay final.

### Kapan template ini dipakai
- saat snapshot intraday perlu dimasukkan manual untuk review cepat,
- saat engineer ingin menyiapkan input uji untuk overlay CONFIRM,
- saat reviewer perlu memeriksa satu kasus tanpa menunggu pipeline penuh.

### Kapan template ini tidak cukup
- saat aturan validasi detail perlu dipastikan dari dokumen normatif,
- saat hasil CONFIRM dipakai sebagai dasar acceptance resmi,
- saat diperlukan jaminan penuh terhadap field contract yang hidup di dokumen bernomor.

## Template SQL
Template SQL di bawah ini adalah contoh operasional yang dapat disesuaikan dengan implementasi selama tetap patuh pada kontrak normatif Weekly Swing. Template ini tidak otomatis menjadi satu-satunya bentuk query resmi.

```sql
-- contoh insert snapshot manual untuk review CONFIRM
INSERT INTO ws_confirm_manual_snapshot (
    ticker,
    last_price,
    captured_at,
    effective_captured_at,
    volume_shares,
    turnover_idr,
    snapshot_source,
    created_by
) VALUES (
    'BBCA',
    9450,
    '2026-03-13 10:15:00',
    '2026-03-13 10:15:00',
    125000,
    1181250000,
    'manual-review',
    'reviewer'
);
```

### Cara membaca template SQL
- `ticker` sebaiknya menunjuk kandidat PLAN yang benar,
- `last_price` dipakai untuk evaluasi overlay, bukan untuk menulis ulang hasil PLAN,
- `effective_captured_at` lebih aman dipakai sebagai acuan stale bila pipeline memang memisahkannya dari `captured_at`.

## Template CSV
Template CSV di bawah ini adalah contoh bentuk data untuk membantu input manual atau review cepat. Bentuk ini tidak menggantikan kontrak field final yang diatur oleh dokumen normatif.

```csv
ticker,last_price,captured_at,effective_captured_at,volume_shares,turnover_idr,snapshot_source
BBCA,9450,2026-03-13 10:15:00,2026-03-13 10:15:00,125000,1181250000,manual-review
BMRI,5225,2026-03-13 10:17:00,2026-03-13 10:17:00,84000,438900000,manual-review
```

### Cara memakai template CSV
- gunakan satu baris per ticker,
- jangan campur format angka dan format teks secara tidak konsisten,
- pastikan timestamp dapat dibaca konsisten oleh parser yang dipakai.

## Contoh normalisasi angka
Contoh normalisasi angka pada bagian ini bersifat ilustratif agar pembaca memahami bentuk representasi yang umum dipakai. Aturan parsing dan normalisasi final tetap mengikuti implementasi yang patuh pada dokumen normatif.

| Input mentah | Normalisasi yang diinginkan | Alasan |
|---|---|---|
| `9,450` | `9450` | hilangkan pemisah ribuan |
| `1.181.250.000` | `1181250000` | simpan sebagai angka utuh |
| `125000` | `125000` | pertahankan bila sudah bersih |
| ` 2026-03-13 10:15:00 ` | `2026-03-13 10:15:00` | trim spasi |

## Checklist operator singkat
Checklist ini membantu operator meninjau kelengkapan input secara praktis sebelum digunakan. Checklist ini bukan acceptance gate sistem dan tidak menggantikan validasi normatif yang dilakukan oleh kontrak Weekly Swing.

- pastikan ticker cocok dengan kandidat PLAN yang sedang direview,
- pastikan `last_price` terisi dan dapat dibaca sebagai angka,
- pastikan timestamp snapshot jelas dan tidak ambigu,
- pastikan `volume_shares` dan `turnover_idr` tidak tertukar,
- pastikan input manual dipakai untuk overlay CONFIRM, bukan untuk mengubah hasil PLAN.
