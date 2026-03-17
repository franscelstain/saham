# Weekly Swing Worked Example E2E

## Purpose

Dokumen ini menyajikan worked example end-to-end untuk membantu pembaca memahami bagaimana dokumen normatif Weekly Swing diterapkan secara runtut. Dokumen ini tidak menjadi source of truth terhadap invariant, acceptance, atau business rule.

## Reading Rule

Worked example pada dokumen ini harus dibaca sebagai demonstrasi implementatif terhadap kontrak normatif yang telah ditetapkan pada dokumen owner yang relevan.

Jika terdapat perbedaan antara worked example ini dan dokumen owner normatif, maka dokumen owner normatif selalu menang dan worked example harus diperbarui.

## Owner Reminder

Owner normatif untuk contoh ini tetap berada pada:

- `02_WS_CANONICAL_RUNTIME_FLOW.md`
- `03_WS_DATA_MODEL_MARIADB.md`
- `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
- `10_WS_CONFIRM_OVERLAY.md`
- `23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
- `24_WS_RECOMMENDATION_ALGORITHM.md`
- `25_WS_RECOMMENDATION_REASON_CODES_AND_TESTS.md`

---

## Scenario 1 — Normal Day: PLAN -> RECOMMENDATION -> CONFIRM

### Step 1 — PLAN Snapshot

Misalkan PLAN untuk `2026-03-16` menghasilkan source candidate berikut:

| ticker | plan_rank | group | score_total | entry_band | stop_loss | tp1 | dv20_idr_mn | note |
|---|---:|---|---:|---|---|---|---:|---|
| AAA | 1 | TOP_PICKS | 87.4 | 1000–1020 | 965 | 1075 | 18500 | momentum + likuiditas kuat |
| BBB | 2 | TOP_PICKS | 84.1 | 1240–1260 | 1190 | 1315 | 14600 | valid, tapi RR lebih sempit |
| CCC | 3 | SECONDARY | 79.8 | 540–550 | 522 | 575 | 6200 | valid, tapi kualitas total di bawah AAA/BBB |

Interpretasi PLAN:
- ketiga ticker berada pada candidate PLAN yang sah;
- AAA dan BBB berada pada group prioritas `TOP_PICKS`;
- CCC berada pada `SECONDARY`;
- belum ada recommendation dan belum ada confirm pada tahap ini.

### Step 2 — RECOMMENDATION (CAPITAL_FREE)

Recommendation membaca PLAN immutable yang sama dan menghasilkan:

| ticker | recommendation_score | recommendation_rank | recommendation_label | recommended_flag | reason_codes |
|---|---:|---:|---|---|---|
| AAA | 91.2 | 1 | RECOMMENDED | true | WS_REC_SELECTED, WS_REC_CAPITAL_FREE |
| BBB | 88.6 | 2 | RECOMMENDED | true | WS_REC_SELECTED, WS_REC_CAPITAL_FREE |
| CCC | 76.4 | 3 | NOT_SELECTED | false | WS_REC_NOT_SELECTED, WS_REC_RANK_OUTSIDE_DYNAMIC_TARGET |

Interpretasi recommendation:
- recommendation source universe berasal dari PLAN;
- `TOP_PICKS` tidak otomatis berarti seluruh item pasti recommended;
- `SECONDARY` boleh tetap tidak terpilih walaupun masih sah sebagai candidate PLAN;
- recommendation belum membaca confirm apa pun.

### Step 3 — CONFIRM Overlay

Pada sesi confirm di hari yang sama, input manual / API gratis menghasilkan:

| ticker | plan_candidate | recommended | confirm_status | confirm_reason | meaning |
|---|---:|---:|---|---|---|
| AAA | yes | yes | CONFIRM_OK | breakout + volume acceptable | strengthening signal |
| BBB | yes | yes | CONFIRM_NEUTRAL | trigger belum bersih | recommendation tetap ada, confirm hanya metadata |
| CCC | yes | no | CONFIRM_OK | breakout valid | candidate validation only |

Interpretasi akhir:
- AAA = **recommended + confirmed** → confirm menguatkan, bukan membentuk recommendation baru;
- BBB = **recommended + confirm neutral** → recommendation tidak hilang;
- CCC = **non-recommended + confirmed** → confirm tetap sah karena CCC masih candidate PLAN.

### Step 4 — Composite Consumer View

Consumer dapat menampilkan ringkasan seperti ini:

| ticker | semantic |
|---|---|
| AAA | RECOMMENDED_AND_CONFIRMED |
| BBB | RECOMMENDED_CONFIRM_NEUTRAL |
| CCC | NON_RECOMMENDED_CANDIDATE_CONFIRMED |

Catatan penting:
- semantic consumer view bukan owner rule;
- source semantics tetap terpisah antara PLAN, RECOMMENDATION, dan CONFIRM.

---

## Scenario 2 — Recommendation Empty While Prioritized Groups Exist

### PLAN Snapshot

Misalkan PLAN masih menghasilkan:

| ticker | plan_rank | group | score_total |
|---|---:|---|---:|
| DDD | 1 | TOP_PICKS | 76.2 |
| EEE | 2 | SECONDARY | 73.9 |

### RECOMMENDATION Result

Dalam `CAPITAL_AWARE` mode dengan `input_capital = 450000`, policy recommendation dapat menghasilkan:

- `recommended_count = 0`
- `empty_recommendation_flag = true`

Contoh penyebab yang sah:
- DDD gagal threshold final recommendation setelah capital fitness dipertimbangkan;
- EEE tidak memenuhi minimum feasibility untuk suggestion lot;
- dynamic recommendation count final menjadi nol.

Ini **valid** walaupun PLAN masih memiliki item di `TOP_PICKS` / `SECONDARY`.

### CONFIRM Consequence

Jika DDD tetap valid sebagai candidate PLAN dan user memasukkan confirm, maka CONFIRM pada DDD tetap sah.

Rule yang dibuktikan:
- recommendation kosong bukan error;
- recommendation kosong tidak membatalkan eligibility confirm terhadap candidate PLAN.

---

## Scenario 3 — Non-Recommended Candidate Confirmed

Misalkan ticker FFF memiliki state berikut:

| field | value |
|---|---|
| plan_candidate | true |
| plan_group_semantic | SECONDARY |
| recommended_flag | false |
| confirm_status | CONFIRM_OK |

Interpretasi yang benar:
- FFF **tetap valid** untuk confirm karena masih candidate PLAN;
- FFF **tidak otomatis** menjadi recommended;
- confirm pada FFF hanya bermakna sebagai **candidate validation only**.

Rule yang dibuktikan:
- confirm eligibility berasal dari candidate PLAN, bukan recommendation membership.

---

## Scenario 4 — Capital-Free vs Capital-Aware Without Confirm Leakage

### Same PLAN Input

Gunakan PLAN source universe yang sama:
- AAA
- BBB
- CCC

### Capital-Free Output

Hasil recommendation:
- AAA = recommended
- BBB = recommended
- CCC = not selected

### Capital-Aware Output (`input_capital = 300000`)

Hasil recommendation dapat berubah menjadi:
- AAA = recommended, `suggested_lots = 2`
- BBB = not selected, `WS_REC_MIN_LOT_NOT_AFFORDABLE`
- CCC = not selected

### Rule Proven

Perubahan recommendation set di sini sah karena:
- source PLAN sama;
- capital mode berbeda;
- confirm tetap tidak dibaca.

Dengan demikian, dokumen ini membuktikan dua hal sekaligus:
1. recommendation dapat berbeda antara `CAPITAL_FREE` dan `CAPITAL_AWARE`;
2. perbedaan tersebut tidak boleh disebabkan oleh confirm.

---

## Final Reminder

Worked example ini hanya membantu pembacaan. Untuk penilaian final, pembaca wajib kembali ke owner normatif.
