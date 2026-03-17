# Weekly Swing Runtime Output Examples

## Purpose

Dokumen ini berisi contoh bentuk runtime output untuk membantu pembacaan kontrak normatif Weekly Swing. Dokumen ini tidak menjadi owner final terhadap shape, invariant, atau acceptance behavior output.

## Reading Rule

Setiap contoh pada dokumen ini harus dibaca sebagai ilustrasi terhadap kontrak normatif yang sudah ditetapkan di dokumen owner yang relevan.

Jika terdapat perbedaan antara contoh pada dokumen ini dan dokumen owner normatif, maka dokumen owner normatif selalu menang dan contoh harus diperbarui.

## Owner Mapping Reminder

Untuk menilai apakah contoh output valid, pembaca wajib memeriksa owner berikut:
- runtime field dan persistence shape -> `03_WS_DATA_MODEL_MARIADB.md`
- PLAN branch behavior -> `08_WS_PLAN_ALGORITHM.md`
- deterministic selection / grouping -> `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
- CONFIRM label behavior -> `10_WS_CONFIRM_OVERLAY.md`
- recommendation contract -> `23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
- recommendation algorithm -> `24_WS_RECOMMENDATION_ALGORITHM.md`
- acceptance akhir -> `13_WS_CONTRACT_TEST_CHECKLIST.md`

Contoh pada dokumen ini tidak boleh dipakai untuk menambah field, reason, label, atau branch baru yang belum hidup pada owner tersebut.

## Example Map

| Example | File |
|---|---|
| PLAN runtime | `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json` |
| RECOMMENDATION runtime | `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EXAMPLE_A.json` |
| RECOMMENDATION runtime (richer) | `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EXAMPLE_B.json` |
| RECOMMENDATION empty | `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EMPTY.json` |
| RECOMMENDATION empty (detailed) | `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EMPTY_DETAILED.json` |
| CONFIRM recommended candidate | `examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json` |
| CONFIRM non-recommended candidate | `examples/WS_CONFIRM_OUTPUT_NON_RECOMMENDED_CANDIDATE.json` |
| PLAN + RECOMMENDATION pair | `examples/WS_PLAN_RECOMMENDATION_PAIR_EXAMPLE_A.json` |
| PLAN + CONFIRM (candidate still PLAN-rooted) pair | `examples/WS_PLAN_CONFIRM_RUNTIME_PAIR_EXAMPLE_A.json` |
| PLAN + RECOMMENDATION + CONFIRM triple | `examples/WS_PLAN_RECOMMENDATION_CONFIRM_TRIPLE_EXAMPLE_A.json` |
| Composite consumer view | `examples/WS_PLAN_REC_CONFIRM_COMPOSITE_EXAMPLE.json` |

## PLAN Output Example

Lihat `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`.

Field minimal yang harus terlihat:
- `meta.trade_date`
- `items[].ticker`
- `items[].plan_rank`
- `items[].group_semantic`
- `summary.visible_count`

PLAN example harus dipakai untuk menunjukkan source-of-truth candidate membership.

## RECOMMENDATION Output Example

Lihat `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EXAMPLE_A.json` dan `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EXAMPLE_B.json`.

Field minimal yang harus terlihat:
- `meta.capital_mode`
- `items[].recommendation_score`
- `items[].recommendation_rank`
- `items[].recommended_flag`
- `summary.recommended_count`

RECOMMENDATION examples harus menunjukkan bahwa recommendation:
- berasal dari PLAN;
- tidak membaca confirm;
- dapat memuat item `NOT_SELECTED` dalam source universe yang sama.

## RECOMMENDATION Empty Output Example

Lihat `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EMPTY.json` dan `examples/WS_RECOMMENDATION_RUNTIME_OUTPUT_EMPTY_DETAILED.json`.

Contoh ini harus membuktikan bahwa:
- recommendation set dapat kosong;
- `empty_recommendation_flag = true`;
- source PLAN tetap dapat memiliki item pada `TOP_PICKS` dan/atau `SECONDARY`.

## CONFIRM Output Example — Recommended Candidate

Lihat `examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`.

Contoh ini dipakai untuk menunjukkan confirm yang hidup berdampingan dengan recommendation tanpa mengubah recommendation payload.

## CONFIRM Output Example — Non-Recommended Candidate

Lihat `examples/WS_CONFIRM_OUTPUT_NON_RECOMMENDED_CANDIDATE.json`.

Contoh ini dipakai untuk menunjukkan bahwa confirm tetap sah untuk ticker non-recommended selama ticker tersebut masih valid sebagai candidate PLAN.

## Composite View Example — PLAN + RECOMMENDATION + CONFIRM

Lihat `examples/WS_PLAN_RECOMMENDATION_CONFIRM_TRIPLE_EXAMPLE_A.json` dan `examples/WS_PLAN_REC_CONFIRM_COMPOSITE_EXAMPLE.json`.

Composite view hanya untuk consumer/view. Source semantics PLAN, RECOMMENDATION, dan CONFIRM tetap harus dipisahkan.

## Do / Do Not

### Do
- gunakan examples untuk membantu pembaca memahami field dan state combination;
- gunakan examples untuk memvisualkan edge case penting;
- gunakan examples untuk cross-check acceptance.

### Do Not
- jangan perlakukan examples sebagai owner rule;
- jangan tambahkan field baru hanya karena examples menampilkannya;
- jangan pakai payload confirm sebagai dasar pembentukan recommendation.
