# 08 — Weekly Swing PLAN Algorithm

## Purpose

Dokumen ini adalah owner normatif untuk algoritma PLAN strategy Weekly Swing. Dokumen ini menetapkan alur pembentukan PLAN dari input upstream yang sudah downstream-ready menjadi hasil PLAN Weekly Swing yang deterministik, audit-able, dan konsisten dengan runtime output strategy.

## Scope

Dokumen ini mengunci:

- asumsi input downstream-ready yang dikonsumsi Weekly Swing,
- urutan langkah PLAN dari intake sampai final mapping,
- stop conditions dan branch behavior `FAILED` / `NO_TRADE`,
- hubungan antara guard, scoring, selection, grouping, reason codes, dan output runtime,
- precedence saat beberapa kondisi berlaku bersamaan,
- serta ekspektasi minimal output per branch utama.

Dokumen ini tidak mendefinisikan ulang kontrak upstream yang dimiliki `market_data`.

## Upstream Boundary

Dokumen ini mengasumsikan ketersediaan input authoritative dari domain `market_data`. Semantics berikut tetap dimiliki oleh `docs/market_data/`:

- bars / OHLCV,
- indicators,
- publication,
- readiness,
- validity,
- dan kontrak upstream lain yang memasok input ke watchlist.

Dokumen ini hanya menetapkan bagaimana Weekly Swing mengonsumsi input tersebut.

## Required Downstream-Ready Inputs

Input minimum yang dibutuhkan PLAN per ticker adalah:

- identity: `ticker`, `ticker_id`
- pricing / breakout context: `close`, `hh20`
- liquidity / volatility / participation: `dv20_idr`, `atr14_pct`, `vol_ratio`
- scoring fields yang dipakai strategy
- `data_ready` / completeness outcome yang sudah disediakan downstream
- active Weekly Swing paramset yang valid
- `trade_date`, `policy_code`, `param_set_id`, `data_batch_hash`

Jika field wajib untuk langkah tertentu tidak tersedia, perilaku PLAN wajib mengikuti stop-condition atau branch behavior yang dikunci oleh dokumen ini dan dokumen owner terkait.

## Algorithm Ownership Map

Agar tidak terjadi kontrak ganda, ownership dibagi tegas sebagai berikut:

- dokumen ini: urutan langkah PLAN, precedence, branch behavior, dan kapan suatu state dianggap `FAILED`, `NO_TRADE`, `AVOID`, `WATCH_ONLY`, `TOP_PICKS`, atau `SECONDARY`
- `03_WS_DATA_MODEL_MARIADB.md`: shape runtime / persistence output
- `04_WS_PARAMSET_JSON_CONTRACT.md`: shape paramset
- `05_WS_PARAMETER_REGISTRY_COMPLETE.md`: daftar parameter dan makna parameter
- `06_WS_PARAMSET_VALIDATOR_SPEC.md`: validator paramset
- `07_WS_REASON_CODES_AND_HASH.md`: dictionary reason-code contract dan hash contract
- `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`: determinism selection, tie-breaker, cutoff mechanics, dan final ordering
- `13_WS_CONTRACT_TEST_CHECKLIST.md`: acceptance minima

## Canonical PLAN Pipeline (LOCKED)

Urutan pipeline berikut wajib dan tidak boleh ditukar diam-diam:

1. run intake and prerequisite check
2. candidate binding
3. eligibility and data-readiness gate
4. hard guard evaluation
5. score computation
6. forced `WATCH_ONLY` evaluation
7. deterministic selection
8. final group mapping and reason assignment
9. run summary and output assembly

Semua implementasi Weekly Swing wajib bisa ditelusuri ke urutan ini.

## Step 1 — Run Intake and Prerequisite Check

**Purpose**  
Memastikan PLAN hanya berjalan jika run-level prerequisites tersedia.

**Inputs**  
`trade_date`, active paramset Weekly Swing yang valid, data batch downstream-ready.

**Rule (LOCKED)**  
- Paramset aktif wajib lolos validator Weekly Swing.
- Jika precondition run-level gagal, hasil run adalah `FAILED`, bukan `NO_TRADE`.
- `FAILED` dipakai untuk kegagalan kontraktual / readiness yang membuat PLAN tidak layak dibangun.
- `NO_TRADE` hanya dipakai untuk run valid yang tidak menghasilkan daftar ticker tampil.

**Outputs**  
Run context yang valid atau status run `FAILED`.

## Step 2 — Candidate Binding

**Purpose**  
Membentuk candidate universe downstream-ready yang akan diproses Weekly Swing.

**Inputs**  
Universe ticker downstream-ready untuk `trade_date=T`.

**Rule (LOCKED)**  
- Weekly Swing tidak boleh membuat candidate baru di luar universe yang sudah downstream-ready.
- Candidate binding hanya boleh memakai identity authoritative yang sudah tersedia downstream.
- Ticker duplikat untuk run yang sama harus dianggap schema / upstream drift dan tidak boleh disenyapkan diam-diam.

**Outputs**  
Candidate list tunggal per run.

## Step 3 — Eligibility and Data-Readiness Gate

**Purpose**  
Memisahkan run failure, no-trade, dan kandidat yang layak diproses lebih lanjut.

**Inputs**  
Candidate list, completeness/readiness outcome, paramset aktif.

**Rule (LOCKED)**  
- Coverage / readiness hard-fail mengikuti kontrak strategy dan dokumen selection deterministic.
- Jika coverage hard-fail terpenuhi, status run wajib `FAILED`.
- Jika run valid tetapi `eligible_total < no_trade.min_eligible_count`, status run wajib `NO_TRADE`.
- `NO_TRADE` wajib menghasilkan `items = []`, tanpa fallback picks.

**Outputs**  
Eligible pool atau status run `FAILED` / `NO_TRADE`.

## Step 4 — Hard Guard Evaluation

**Purpose**  
Mengeliminasi kandidat yang secara strategy memang harus diblok sebelum selection.

**Inputs**  
Eligible pool, threshold guard aktif.

**Rule (LOCKED)**  
- Guard berat menghasilkan `group_semantic = AVOID`.
- Penyebab guard harus memakai reason code strategy yang sudah hidup di contract.
- Kandidat `AVOID` tidak boleh naik lagi menjadi `WATCH_ONLY`, `TOP_PICKS`, atau `SECONDARY`.
- Precedence guard lebih tinggi daripada selection.

**Outputs**  
Pool `pass_guard = true` dan pool `AVOID`.

## Step 5 — Score Computation

**Purpose**  
Menghitung `score_total` untuk kandidat yang lolos guard.

**Inputs**  
Pool `pass_guard = true`, formula dan parameter scoring Weekly Swing.

**Rule (LOCKED)**  
- Dokumen ini mengunci bahwa scoring dilakukan setelah guard berat dan sebelum selection.
- Ownership formula score komponen dan combine rule tetap di area owner yang relevan; implementasi tidak boleh menyelipkan formula lain di luar kontrak Weekly Swing.
- `score_total` harus dapat diaudit dan dipakai ulang pada deterministic selection.
- Seluruh item yang masuk selection wajib berasal dari hasil score computation pada run yang sama.

**Outputs**  
Scored pool dengan `score_total` dan komponen pendukung yang memang kontraktual.

## Score Binding Rule (LOCKED)

- `score_total` yang dipakai selection wajib berasal dari satu hasil scoring final per item per run.
- Implementasi tidak boleh memakai skor alternatif khusus kuota, skor preview, atau skor UI-only sebagai pengganti `score_total` selection.
- Jika komponen score kontraktual tersedia, nilai tersebut harus konsisten dengan `score_total` yang dipakai selection dan audit.
- Jika ada drift antara field score komponen dan `score_total`, run harus dianggap cacat implementasi dan tidak boleh dianggap valid diam-diam.

## Step 6 — Forced WATCH_ONLY Evaluation

**Purpose**  
Menahan kandidat yang layak dipantau tetapi tidak layak menjadi pick.

**Inputs**  
Scored pool, thresholds forced watch-only.

**Rule (LOCKED)**  
- Forced `WATCH_ONLY` dievaluasi sebelum selection final.
- Kandidat yang terkena forced `WATCH_ONLY` tidak boleh di-upgrade lagi oleh ranking / kuota selection.
- Forced `WATCH_ONLY` wajib memakai reason strategy yang sah.
- Forced `WATCH_ONLY` memiliki precedence di bawah `AVOID`, tetapi di atas hasil selection.

**Outputs**  
Pool forced `WATCH_ONLY` dan pool yang masih eligible untuk selection.

## Step 7 — Deterministic Selection

**Purpose**  
Memilih kandidat final secara deterministik untuk `TOP_PICKS` dan `SECONDARY`.

**Inputs**  
Selection-eligible pool, `score_total`, active cutoffs, dynamic target rules.

**Rule (LOCKED)**  
- Determinism selection wajib mengikuti `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`.
- Tidak boleh ada metode selection kedua di luar qualified pools + quantile cutoff + target dinamis.
- Tie handling, ordering, cutoff, dan target dinamis tunduk penuh pada dokumen 09.
- Kegagalan selection pada run yang tetap valid harus berujung `NO_TRADE`, bukan `FAILED`, jika memang tidak ada item tampil yang layak.

**Outputs**  
Candidate final untuk `TOP_PICKS`, `SECONDARY`, atau state `NO_TRADE`.

## Step 8 — Final Group Mapping and Reason Assignment

**Purpose**  
Menyatukan semua branch outcome menjadi output PLAN tunggal yang konsisten.

**Inputs**  
Pool `AVOID`, forced `WATCH_ONLY`, hasil selection final, reason-code contract.

**Rule (LOCKED)**  
Precedence final wajib:

1. `FAILED` run-level
2. `NO_TRADE` run-level
3. `AVOID` per item
4. forced `WATCH_ONLY`
5. selection result (`TOP_PICKS` / `SECONDARY`)
6. hidden-but-eligible `WATCH_ONLY`

Aturan tambahan:
- `AVOID` tidak boleh dioverride oleh branch di bawahnya.
- forced `WATCH_ONLY` tidak boleh di-upgrade menjadi `TOP_PICKS` / `SECONDARY`.
- Semua reason code harus ada di dictionary reason-code contract.
- Tidak boleh membuat label group baru tanpa pembaruan normatif.

**Outputs**  
Per-item `group_semantic`, `reasons[]`, dan nilai runtime lain yang memang kontraktual.

## Step 9 — Run Summary and Output Assembly

**Purpose**  
Menyusun payload PLAN akhir yang siap dipersist dan dikonsumsi UI/API.

**Inputs**  
Final mapped items, run status, run metrics, hash contract.

**Rule (LOCKED)**  
- Shape output wajib mengikuti `03_WS_DATA_MODEL_MARIADB.md`.
- Jika status run `NO_TRADE`, payload tetap valid tetapi `items = []`.
- `meta.plan_hash` dan hash-related behavior tunduk pada `07_WS_REASON_CODES_AND_HASH.md`.
- Examples, fixtures, atau SQL tidak boleh memperkenalkan field runtime baru di luar owner contract.

**Outputs**  
Runtime PLAN canonical: `meta`, `items`, `summary`.

## Run-Level Decision Table (LOCKED)

| Condition | Final run status | Item list | Note |
|---|---|---|---|
| Paramset invalid / prerequisite run-level gagal | `FAILED` | tidak boleh dipakai sebagai PLAN valid | kegagalan kontraktual |
| Candidate binding drift / duplicate identity | `FAILED` | tidak boleh dipakai sebagai PLAN valid | drift tidak boleh disenyapkan |
| Coverage / readiness hard-fail | `FAILED` | tidak boleh dipakai sebagai PLAN valid | bukan kasus no-trade |
| Run valid, `eligible_total < min_eligible_count` | `NO_TRADE` | wajib `[]` | tidak boleh fallback picks |
| Run valid, eligible ada, tetapi tidak ada item selection tampil | `NO_TRADE` | wajib `[]` | run tetap valid |
| Run valid, selection menghasilkan item tampil | `SUCCESS` | boleh non-empty | payload PLAN canonical |

## Item-Level Group Resolution Table (LOCKED)

| Branch condition | Final group_semantic | Override allowed? | Note |
|---|---|---|---|
| Hard guard fail | `AVOID` | tidak | precedence tertinggi per-item |
| Forced watch-only hit | `WATCH_ONLY` | tidak oleh selection | di bawah `AVOID` |
| Selected in primary quota | `TOP_PICKS` | tidak oleh branch lebih rendah | harus dari selection valid |
| Selected in secondary quota | `SECONDARY` | tidak oleh branch lebih rendah | harus dari selection valid |
| Eligible tetapi tidak terpilih / hidden | `WATCH_ONLY` | tidak jadi pick tanpa selection | bukan guard fail |

## Minimal Output Expectation per Branch (LOCKED)

### A. Run `FAILED`
- hasil run tidak boleh diperlakukan sebagai PLAN valid untuk UI/API normal,
- status run harus eksplisit `FAILED`,
- penyebab failure harus dapat ditelusuri via reason / failure code contract yang relevan.

### B. Run `NO_TRADE`
- payload tetap valid,
- `items` wajib kosong,
- tidak boleh menyisipkan placeholder ticker sebagai pengganti selection kosong.

### C. Item `AVOID`
- item boleh muncul hanya jika output strategy memang menampilkan group `AVOID`,
- wajib punya reason strategy yang sah,
- tidak boleh ikut kuota `TOP_PICKS` / `SECONDARY`.

### D. Item `WATCH_ONLY`
- boleh berasal dari forced watch-only atau hidden-but-eligible,
- reason set harus konsisten dengan jalur masuknya,
- tidak boleh menyamarkan guard fail.

### E. Item `TOP_PICKS` / `SECONDARY`
- wajib berasal dari deterministic selection,
- ordering final wajib konsisten dengan dokumen 09,
- item wajib mewarisi `score_total` final dari run yang sama.

## Worked Normative Pseudoflow (LOCKED)

```text
start run
  validate active paramset
  validate downstream-ready batch
  if run prerequisite fails => FAILED

  bind candidate universe for T
  if duplicate or broken identity => FAILED

  evaluate readiness / coverage
  if hard-fail => FAILED
  if eligible_total < min_eligible_count => NO_TRADE with items=[]

  evaluate hard guards
  mark guard-fail items as AVOID

  compute score_total for guard-pass items
  if scoring drift / inconsistent score binding => FAILED

  evaluate forced WATCH_ONLY
  remove forced-watch-only items from selection pool

  run deterministic selection on remaining pool
  if no visible item selected => NO_TRADE with items=[]

  map per-item final groups using precedence:
    AVOID > forced WATCH_ONLY > selection result > hidden WATCH_ONLY

  build PLAN runtime output
  attach reason codes and plan_hash
end run
```

## Non-Ambiguous Branch Rules (LOCKED)

### A. FAILED vs NO_TRADE
- `FAILED` = run kontraktual / readiness failure; PLAN tidak layak dibentuk.
- `NO_TRADE` = run valid, tetapi tidak ada item tampil yang layak.
- Implementasi tidak boleh menukar dua status ini.

### B. AVOID vs WATCH_ONLY
- `AVOID` = guard fail / block condition.
- `WATCH_ONLY` = kandidat valid untuk dipantau tetapi bukan pick.
- `WATCH_ONLY` tidak boleh dipakai untuk menyamarkan guard fail.

### C. TOP_PICKS vs SECONDARY
- Keduanya hanya boleh berasal dari selection result yang valid.
- Tidak boleh diisi langsung hanya karena score tinggi tanpa melalui pipeline selection.

## Relationship to Deterministic Selection

Dokumen ini mengunci alur PLAN. Rincian determinism selection, ranking, tie-breaker, cutoff, target dinamis, dan final ordering tunduk pada `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`.

## Relationship to Output Shape

Bentuk output PLAN, summary, dan persistence consequences tunduk pada `03_WS_DATA_MODEL_MARIADB.md` dan `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`.

## Relationship to Acceptance

Acceptance minimal yang menguji behavior dokumen ini wajib merujuk `13_WS_CONTRACT_TEST_CHECKLIST.md`. Example, fixture, SQL, atau implementasi teknis tidak boleh diperlakukan sebagai owner behavior pengganti.

## Final Rule

Tidak ada behavior PLAN yang boleh dianggap resmi hanya karena muncul di example, fixture, atau implementasi teknis apabila behavior tersebut tidak dapat ditelusuri ke dokumen ini, `03_WS_DATA_MODEL_MARIADB.md`, `07_WS_REASON_CODES_AND_HASH.md`, atau `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`.
