# docs/watchlist/CONTRACT.md

## Tujuan
Dokumen di folder `docs/watchlist/` adalah acuan utama untuk membangun fitur watchlist. Dokumen ini harus sinkron dengan codebase dan test, supaya tidak ada salah tafsir.

## Definisi

### CONTRACT
CONTRACT = aturan yang **mengikat implementasi** (code + output).  
Jika terjadi konflik antara docs dan code, maka **code dianggap salah** sampai disamakan.

CONTRACT mencakup:
- Struktur output (payload keys, types, required/nullable)
- Algoritma/perhitungan (skor, ranking, guard, threshold)
- Reason codes + semantics
- Urutan proses/pipeline (step-by-step)

### LOCKED
LOCKED = bagian dari CONTRACT yang **tidak boleh diubah diam-diam**.  
Perubahan LOCKED hanya sah bila dilakukan lewat “Change Control” (lihat bagian bawah) dan selalu ikut mengubah:
1) docs, 2) code, 3) tests/fixtures.

LOCKED biasanya meliputi:
- weights skor
- clamp/normalization range
- guard thresholds
- RR/stop constraints
- reason codes enumerations
- field names pada payload

## Source of Truth

### Contract documents
Semua file di `docs/watchlist/` adalah CONTRACT, **kecuali**:
- `docs/watchlist/strategi.md` (operasional/pipeline eksekusi, bukan kontrak angka/rumus)
- `docs/watchlist/schema.md` (struktur DB, bukan kontrak angka/rumus)

Catatan:
- Walau strategi.md & schema.md bukan kontrak algoritma, tetap harus konsisten dengan implementasi (mis. command yang disebut harus benar).

## Aturan Konflik (Docs vs Code)
Jika ada perbedaan:
1) Default: **code harus mengikuti docs**
2) Jika ternyata docs yang keliru, lakukan perubahan lewat “Change Control” (docs → code → tests), bukan patch code diam-diam.

## Change Control untuk LOCKED

### Status perubahan
Perubahan LOCKED harus melalui 3 status:
1) **PROPOSAL**: usulan perubahan nilai/rumus, belum mengikat
2) **EVALUATED**: sudah diuji dengan backtest/replay A/B dan metrik tersimpan
3) **PROMOTED**: menjadi LOCKED baru (docs + code + tests sudah update)

### Syarat minimal PROPOSAL
PROPOSAL harus menyebut:
- nilai lama → nilai baru
- alasan (kontradiksi internal, mismatch input/kontrak, atau evidence)
- dampak yang diharapkan (apa yang membaik dan kenapa)

### Wajib A/B untuk perubahan LOCKED
Setiap PROPOSAL LOCKED harus diuji dengan A/B:

- **Baseline**: LOCKED lama
- **Candidate**: LOCKED baru (proposal)

Keduanya dijalankan pada:
- policy yang sama
- range tanggal yang sama
- universe yang sama
- sumber data yang sama

### Wajib snapshot config per run
Setiap run harus menyimpan snapshot parameter yang dipakai:
- policy name
- semua parameter LOCKED (weights/threshold/range)
- identifier versi code (commit hash / build_id / tag)
- tanggal range

### Metrik minimal yang harus dicatat
Minimal per policy per run:
- counts: Top/Secondary/Watch/Avoid/NoTrade
- coverage: % hari ada kandidat (Top+Secondary > 0)
- skor: avg/median/p95 score_total
- reason codes: top N reason paling sering muncul (guards/flags)
- jumlah “hard reject” vs “soft flags” (kalau ada)

Opsional (lebih kuat):
- simulasi hasil trade sederhana (entry/stop/tp) untuk N hari, konsisten dan repeatable

### Kriteria promote
PROPOSAL boleh dipromote jadi LOCKED baru jika:
- metrik tidak memburuk secara material, dan
- ada improvement yang jelas sesuai tujuan policy (coverage, kualitas, guard noise turun, dsb), atau
- perubahan memperbaiki kontradiksi/mismatch yang fatal.

## Aturan Implementasi Kode

### Per-policy vs umum
- Logic yang **berbeda per policy** harus hidup di `app/Trade/Watchlist/Policies/*Policy.php`
- Logic yang **umum** (shared) harus hidup di komponen shared (Evaluator/Validator/Builders), bukan copy-paste di policy atau engine
- `WatchlistEngine` hanya orchestrator, bukan tempat rule policy

### Single Source of Truth untuk CONFIRM
CONFIRM/intraday eligibility harus melalui evaluator strict (satu jalur), bukan duplikasi logic.

## Test Gates (wajib ada)
Project harus punya test yang mengunci CONTRACT:
1) **Contract output tests**
   - required keys, types, nullable/required, no extra keys
2) **Policy score tests / golden master**
   - fixture input kecil per policy, snapshot score_components + score_total + group
3) **Doc-config alignment tests**
   - weights/threshold di code sama dengan LOCKED di docs

## Prinsip
- Boleh berkembang, tapi tidak boleh berubah diam-diam.
- Setiap perubahan LOCKED harus traceable, repeatable, dan terkunci lagi lewat tests.
