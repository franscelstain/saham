# Manual Input Template (CONFIRM) — Weekly Swing (Reference)

## Purpose
Dokumen ini menyediakan template operasional referensial untuk menyiapkan input manual CONFIRM. Dokumen ini bukan owner kontrak input, bukan owner strictness, dan bukan pengganti aturan overlay normatif.

## Scope
Cakupan dokumen ini dibatasi pada contoh bentuk input manual yang praktis dipakai saat operasi atau review. Validitas final input tetap mengikuti dokumen normatif bernomor yang mengatur snapshot, overlay, runtime contract, dan snapshot tables.

## Normative owner
Owner normatif untuk field, validasi, strictness, dan hasil overlay tetap berada pada dokumen bernomor Weekly Swing. Template ini hanya membantu penyusunan input manual yang konsisten dengan kontrak tersebut.

## Ringkasan field yang dipakai
Bagian ini merangkum field yang lazim dipakai saat menyiapkan input manual CONFIRM. Ringkasan ini tidak menetapkan daftar field final yang mengikat di luar dokumen normatif.

### Header snapshot
| Field | Fungsi praktis | Catatan baca |
|---|---|---|
| `policy_code` | menandai strategy owner snapshot | gunakan `WS` untuk Weekly Swing |
| `trade_date` | mengikat snapshot ke tanggal trading yang dievaluasi | harus sama dengan target CONFIRM |
| `captured_at` | waktu data diambil | dipakai dalam evaluasi umur snapshot |
| `inserted_at` | waktu data dicatat ke storage | bila tidak diisi manual, biasanya otomatis oleh DB |
| `source` | jejak asal input | mis. `manual-review` |
| `note` | catatan operasional | opsional |

### Item snapshot
| Field | Fungsi praktis | Catatan baca |
|---|---|---|
| `ticker_code` | mengikat snapshot ke kandidat PLAN | sebaiknya cocok dengan identitas ticker kandidat PLAN |
| `ticker_id` | identitas internal ticker bila tersedia | opsional |
| `last_price` | dasar evaluasi drift atau harga saat ini | jangan dicampur dengan harga referensi lain |
| `chg_pct` | perubahan persen pada snapshot | aggregate intraday, bukan orderbook |
| `volume_shares` | ukuran aktivitas berbasis volume | jangan ditukar dengan turnover |
| `turnover_idr` | ukuran aktivitas berbasis nilai | berguna untuk validasi kelengkapan aggregate |

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
-- contoh insert header snapshot manual untuk review CONFIRM
INSERT INTO watchlist_confirm_snapshots (
    policy_code,
    trade_date,
    captured_at,
    inserted_at,
    source,
    note
) VALUES (
    'WS',
    '2026-03-13',
    '2026-03-13 10:15:00',
    '2026-03-13 10:16:00',
    'manual-review',
    'snapshot untuk review CONFIRM'
);

-- contoh insert item snapshot (gunakan snapshot_id hasil insert header)
INSERT INTO watchlist_confirm_snapshot_items (
    snapshot_id,
    ticker_code,
    ticker_id,
    last_price,
    chg_pct,
    volume_shares,
    turnover_idr
) VALUES (
    1001,
    'BBCA',
    NULL,
    9450,
    1.2500,
    125000,
    1181250000
);
```

### Cara membaca template SQL
- insert header dan items dipisah sesuai schema persistence aktual,
- `ticker_code` sebaiknya menunjuk kandidat PLAN yang benar,
- `last_price` dipakai untuk evaluasi overlay, bukan untuk menulis ulang hasil PLAN,
- `effective_captured_at` tidak disimpan sebagai kolom; nilai itu dihitung runtime dari `LEAST(captured_at, inserted_at)` sesuai dokumen normatif.

## Template CSV
Template CSV di bawah ini adalah contoh bentuk data untuk membantu input manual atau review cepat. Bentuk ini tidak menggantikan kontrak field final yang diatur oleh dokumen normatif.

```csv
policy_code,trade_date,captured_at,inserted_at,source,ticker_code,last_price,chg_pct,volume_shares,turnover_idr
WS,2026-03-13,2026-03-13 10:15:00,2026-03-13 10:16:00,manual-review,BBCA,9450,1.2500,125000,1181250000
WS,2026-03-13,2026-03-13 10:15:00,2026-03-13 10:16:00,manual-review,BMRI,5225,0.8500,84000,438900000
```

### Cara memakai template CSV
- gunakan satu baris per ticker untuk import yang nantinya dipecah menjadi header + item sesuai implementasi,
- jangan campur format angka dan format teks secara tidak konsisten,
- pastikan timestamp dapat dibaca konsisten oleh parser yang dipakai.

## Contoh normalisasi angka
Contoh normalisasi angka pada bagian ini bersifat ilustratif agar pembaca memahami bentuk representasi yang umum dipakai. Aturan parsing dan normalisasi final tetap mengikuti implementasi yang patuh pada dokumen normatif.

| Input mentah | Normalisasi yang diinginkan | Alasan |
|---|---|---|
| `9,450` | `9450` | hilangkan pemisah ribuan |
| `1.181.250.000` | `1181250000` | simpan sebagai angka utuh |
| `1,2500` | `1.2500` | gunakan titik desimal yang konsisten untuk `chg_pct` |
| ` 2026-03-13 10:15:00 ` | `2026-03-13 10:15:00` | trim spasi |

## Checklist operator singkat
Checklist ini membantu operator meninjau kelengkapan input secara praktis sebelum digunakan. Checklist ini bukan acceptance gate sistem dan tidak menggantikan validasi normatif yang dilakukan oleh kontrak Weekly Swing.

- pastikan `policy_code` dan `trade_date` cocok dengan run CONFIRM yang direview,
- pastikan `ticker_code` cocok dengan kandidat PLAN yang sedang direview,
- pastikan `last_price` terisi dan dapat dibaca sebagai angka,
- pastikan timestamp snapshot jelas dan tidak ambigu,
- pastikan `volume_shares` dan `turnover_idr` tidak tertukar,
- pastikan input manual dipakai untuk overlay CONFIRM, bukan untuk mengubah hasil PLAN.
