# Watchlist Implementation Audit Example

## Context
Contoh ini menunjukkan bentuk hasil audit implementasi watchlist terhadap implementation guidance atau hasil review code. Dokumen ini bukan owner checklist; owner utama tetap berada pada `WATCHLIST_IMPLEMENTATION_CHECKLIST_FINAL.md`.

## Example A — Guidance Package Review
- Nilai: 9.4 / 10
- Verdict: Sangat matang
- Scope: `weekly_swing` implementation guidance

### PASS
- Mapping PLAN / RECOMMENDATION / CONFIRM dipisah jelas.
- Recommendation dibentuk dari PLAN immutable.
- Confirm tetap berbasis candidate PLAN.
- API tidak menyiratkan buy/sell execution.
- Persistence memisahkan ketiga artefak watchlist.

### PARTIAL
- Serializer/presenter boundary belum dijelaskan cukup eksplisit.
- Manual input validator belum ditulis sebagai modul terpisah.

## Example B — Code Review Finding Pattern
- Nilai: 8.7 / 10
- Verdict: Layak kuat
- Scope: service + API review

### PASS
- Service PLAN dan RECOMMENDATION dipisah.
- Empty recommendation tetap didukung.

### FAIL
- Satu service confirm membaca recommendation result untuk eligibility awal.
- Satu serializer menulis label recommendation tambahan yang bukan berasal dari source artifact.

### Patch Direction
1. pindahkan eligibility CONFIRM kembali ke PLAN candidate binding;
2. pindahkan formatting-only concern ke serializer, hapus rule tambahan dari serializer.

## Example C — Manual Input Stability Review
- Nilai: 9.1 / 10
- Verdict: Sangat matang
- Scope: manual/free input path

### PASS
- Validator manual input terpisah dari recommendation engine.
- Tidak ada asumsi feed premium tersembunyi.
- Manual input tetap menghasilkan flow `PLAN -> RECOMMENDATION -> CONFIRM` yang konsisten.


## Example D — Real API Payload Review Pattern
- Nilai: 9.6 / 10
- Verdict: Sangat matang
- Scope: API response + serializer review

### PASS
- Recommendation muncul tanpa confirm.
- Empty recommendation tetap valid di response.
- Confirm pada non-recommended candidate tetap dipresentasikan sebagai candidate validation only.

### PARTIAL
- Satu field penjelas tambahan belum jelas apakah source-nya dari serializer atau service.

### Patch Direction
1. tandai source tiap field explanation;
2. pastikan serializer hanya memformat, bukan menambah rule.

## Example E — Real Persistence Review Pattern
- Nilai: 9.5 / 10
- Verdict: Sangat matang
- Scope: persistence artifact review

### PASS
- PLAN, RECOMMENDATION, dan CONFIRM dipersist terpisah.
- Recommendation artifact tidak membaca confirm artifact.

### FAIL
- Satu materialized helper view mencampur field recommendation dan confirm sebagai source row utama.

### Patch Direction
1. pisahkan helper view menjadi consumer-view saja;
2. pertahankan source artifact normatif tetap terpisah.

## Example F — Real Manual Input Flow Review Pattern
- Nilai: 9.4 / 10
- Verdict: Sangat matang
- Scope: manual input validator + confirm path

### PASS
- Validator manual input hidup sebagai layer terpisah.
- Confirm eligibility tetap berasal dari candidate PLAN.

### FAIL
- Satu shortcut helper memakai recommendation result untuk menyaring ticker sebelum validator manual input selesai.

### Patch Direction
1. pindahkan filter awal ke PLAN candidate binding;
2. biarkan validator manual input fokus ke payload validity saja.
