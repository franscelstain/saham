# Worked Example E2E — Weekly Swing (Reference Walkthrough)

## Reference status
Dokumen ini adalah walkthrough contoh untuk membantu pembaca memahami alur Weekly Swing dari guards sampai output akhir. Dokumen ini bukan replay proof normatif dan bukan owner expected result resmi.

## Purpose
Gunakan dokumen ini saat engineer atau reviewer ingin melihat bagaimana satu kelompok kandidat dapat bergerak dari data awal, melewati guard, membentuk skor, masuk grouping, lalu dibaca ulang oleh CONFIRM.

## Inputs
- dokumen normatif guard, PLAN, grouping, dan CONFIRM,
- contoh runtime output di folder `examples/`,
- fixture kecil atau fixture kaya bila pembaca ingin menelusuri ulang contoh.

## Outputs
- gambaran konkret perubahan status kandidat dari awal sampai akhir,
- contoh angka sederhana yang mudah dibaca,
- pointer ke artefak yang relevan bila pembaca ingin replay contoh.

## Paramset used
Contoh ini memakai asumsi sederhana yang konsisten dengan arah Weekly Swing: guard likuiditas aktif, ATR berada pada zona yang masih layak, grouping memisahkan kandidat utama dari kandidat pendukung, dan CONFIRM membaca snapshot intraday tanpa mengubah PLAN.

## Kandidat contoh
| Ticker | Ringkasan awal | Tujuan contoh |
|---|---|---|
| A | data siap, likuid, momentum dan breakout kuat | kandidat yang bertahan sampai CONFIRM |
| B | data siap, cukup baik, tetapi tidak sekuat A | kandidat yang lolos PLAN tetapi outcome CONFIRM lebih hati-hati |
| C | data siap tetapi gagal di guard awal | kandidat yang berhenti sebelum scoring lanjut |

## Step 1 — Guards
Misalkan tiga kandidat awal memiliki ringkasan berikut:

| Ticker | dv20_idr | atr14_pct | vol_ratio | Hasil guard |
|---|---:|---:|---:|---|
| A | 3,000,000,000 | 3.0 | 1.3 | lolos |
| B | 2,400,000,000 | 3.8 | 1.1 | lolos |
| C | 300,000,000 | 3.2 | 0.9 | gagal likuiditas |

Pembacaan praktis:
- A dan B lanjut ke scoring karena masih berada pada zona yang layak,
- C berhenti di sini karena likuiditas terlalu rendah untuk strategi mingguan.

## Step 2 — Component scores
Untuk kandidat yang lolos, misalkan komponen skor dibaca seperti berikut:

| Ticker | score_momentum | score_breakout | score_volume | score_risk |
|---|---:|---:|---:|---:|
| A | 0.50 | 1.00 | 0.50 | 1.00 |
| B | 0.40 | 0.80 | 0.75 | 0.85 |

Pembacaan praktis:
- A kuat pada breakout dan risk profile,
- B masih menarik tetapi tidak sekuat A pada breakout.

## Step 3 — score_total
Setelah agregasi sederhana, misalkan hasil total menjadi:

| Ticker | score_total |
|---|---:|
| A | 0.75 |
| B | 0.70 |

Di titik ini urutan relatif sudah terlihat. A berada di atas B, sedangkan C tidak ikut lagi karena gugur di guard. Pembaca bisa melihat bahwa ranking terbentuk dari akumulasi komponen, bukan dari satu sinyal tunggal.

## Step 4 — Grouping / Dynamic Selection
Misalkan rule grouping menghasilkan:

| Ticker | score_total | Group |
|---|---:|---|
| A | 0.75 | `TOP_PICKS` |
| B | 0.70 | `SECONDARY` |
| C | — | `AVOID` |

Pembacaan praktis:
- A menjadi kandidat utama,
- B tetap muncul, tetapi tidak setara dengan A,
- C tidak masuk pool kandidat aktif karena sudah gagal di guard.

## Step 5 — PLAN levels
Untuk dua kandidat yang lolos ke hasil PLAN, misalkan level referensinya dibaca sebagai berikut:

| Ticker | entry_ref | entry_band_low | entry_band_high | stop_price | tp1_price |
|---|---:|---:|---:|---:|---:|
| A | 100.0 | 99.0 | 101.0 | 94.0 | 112.0 |
| B | 100.0 | 99.0 | 101.0 | 90.8 | 118.4 |

Hasil PLAN yang dibaca reviewer:
- A layak diprioritaskan,
- B tetap layak dipantau atau diposisikan sebagai kandidat pendukung,
- C tidak ikut output kandidat aktif.

## Step 6 — CONFIRM overlay
Misalkan pada hari intraday snapshot memberi hasil berikut:

| Ticker | snapshot_age_sec | Kondisi overlay | Label CONFIRM |
|---|---:|---|---|
| A | 90 | snapshot segar, tidak ada sinyal negatif | `CONFIRMED` |
| B | 420 | snapshot mulai tua dan volume lemah | `CAUTION` |

Pembacaan praktis:
- A tetap layak setelah overlay,
- B tidak otomatis dibuang, tetapi outcome menjadi lebih hati-hati karena snapshot lebih tua dan volume intraday tidak sekuat kandidat utama,
- PLAN tetap sama; yang berubah hanya pembacaan intraday di atas PLAN.

Alasan kenapa B menjadi `CAUTION`, bukan `CONFIRMED`:
- snapshot B masih cukup untuk dibaca, jadi belum masuk `DELAY`,
- tetapi kualitas intraday-nya tidak sekuat A sehingga outcome yang lebih masuk akal adalah hati-hati,
- hasil ini menunjukkan bahwa CONFIRM dapat menurunkan keyakinan tanpa menghapus kandidat dari hasil PLAN.

## Before → after snapshot

| Ticker | Setelah PLAN | Setelah CONFIRM | Inti perubahan |
|---|---|---|---|
| A | `TOP_PICKS`, prioritas utama | `CONFIRMED` | keyakinan tetap kuat |
| B | `SECONDARY`, kandidat pendukung | `CAUTION` | tetap layak dibaca, tetapi keyakinan turun |
| C | `AVOID` / gugur di guard | tidak ikut CONFIRM aktif | berhenti sebelum overlay |

## Final output summary
Ringkasan contoh ini dapat dibaca seperti berikut:
- kandidat A bertahan dari guard sampai CONFIRM dengan outcome yang tetap kuat,
- kandidat B lolos PLAN tetapi hasil CONFIRM menjadi lebih hati-hati,
- kandidat C berhenti di awal dan tidak ikut output aktif.

Jika pembaca membuka contoh runtime JSON, pola ini paling dekat dengan pembacaan bahwa PLAN menentukan siapa yang tampil dan bagaimana prioritas awalnya, sedangkan CONFIRM menentukan seberapa aman kandidat itu dibaca pada snapshot intraday.

Invariant yang terlihat langsung dari contoh ini:
- kandidat yang gugur di guard tidak tiba-tiba muncul kembali sebagai kandidat aktif di CONFIRM,
- kandidat yang lolos PLAN tetap membawa jejak prioritas awalnya meskipun outcome intraday dapat berubah,
- overlay CONFIRM menambah pembacaan intraday tanpa menulis ulang hasil PLAN.

## Artifacts referenced by this walkthrough
Artefak yang paling dekat dengan walkthrough ini adalah:
- `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json` untuk melihat pembagian group dan plan levels,
- `examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json` untuk melihat label outcome CONFIRM,
- fixture PLAN kecil bila pembaca ingin memeriksa guard atau tie-break secara sempit,
- fixture CONFIRM pair bila pembaca ingin mengecek immutability PLAN.

## How to replay this walkthrough
1. buka contoh PLAN runtime dan identifikasi kandidat utama, pendukung, dan yang tidak layak,
2. cocokkan pembacaan itu dengan fixture PLAN kecil jika ingin fokus pada satu perilaku,
3. buka contoh CONFIRM runtime untuk melihat bagaimana label overlay dibentuk,
4. cocokkan hasil contoh dengan dokumen normatif guard, grouping, dan CONFIRM bila ada keraguan,
5. gunakan pair example atau fixture immutability jika ingin menelusuri bahwa PLAN tidak ditulis ulang.

## Test reading notes for this walkthrough
Walkthrough ini berguna sebagai panduan baca pengujian end-to-end, terutama untuk melihat titik verifikasi yang biasanya dibandingkan:
- siapa yang gugur di guard,
- siapa yang tetap aktif di PLAN,
- siapa yang berubah outcome saat CONFIRM,
- dan apakah jejak PLAN tetap utuh setelah overlay.

Bagian ini tidak menetapkan test minimum baru; fungsinya hanya membantu pembaca memahami urutan verifikasi end-to-end.
