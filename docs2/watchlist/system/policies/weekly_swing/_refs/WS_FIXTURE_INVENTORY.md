# WS Fixture Inventory (Reference)

## Purpose
Dokumen ini menyediakan inventaris fixture referensial untuk membantu pembaca menelusuri fixture yang dipakai dalam Weekly Swing. Dokumen ini bukan owner test minimum, tetapi dipakai sebagai peta praktis saat engineer menyiapkan, memilih, atau membaca pengujian.

## Scope
Cakupan dokumen ini dibatasi pada inventaris fixture, pola pemakaian, contoh nama fixture, dan tujuan baca cepat per kategori fixture. Validitas fixture tetap ditentukan oleh dokumen normatif bernomor dan contract test checklist.

## Normative owner
Owner normatif untuk test minimum, acceptance, dan kewajiban penggunaan fixture tetap berada pada contract test checklist dan dokumen bernomor yang dirujuk olehnya. Dokumen ini hanya berfungsi sebagai katalog bantu-baca dan panduan pemilihan fixture saat implementasi atau review.

## How to use this inventory
Gunakan dokumen ini untuk tiga kebutuhan praktis:
1. memilih fixture yang tepat untuk skenario uji yang sedang dibangun,
2. memahami jenis perilaku yang biasanya diperiksa oleh fixture tertentu,
3. menentukan dokumen normatif mana yang perlu dibuka saat hasil fixture menimbulkan keraguan.

Urutan baca yang paling aman adalah: tentukan tujuan uji, pilih kategori fixture yang sesuai, buka satu fixture kecil untuk diagnosis cepat, lalu kembalikan interpretasi hasil ke checklist normatif dan dokumen bernomor.

## Fixture inventory

### A. Validator fixtures
Dipakai saat engineer ingin memastikan input atau paramset tidak lolos secara diam-diam padahal bentuknya salah.

Contoh fixture nyata:
- `paramset_valid.json` — baseline minimal yang valid,
- `paramset_missing_required_key.json` — contoh key wajib hilang,
- `paramset_unknown_key.json` — contoh key liar,
- `paramset_type_drift.json` — contoh drift tipe data,
- `paramset_bad_enum.json` — contoh enum tidak sah,
- `paramset_missing_audit_field.json` — contoh metadata audit belum lengkap.

Kapan dipakai:
- sebelum menulis validator baru,
- saat refactor parser paramset,
- saat bug muncul karena input salah lolos ke runtime.

### B. PLAN fixtures
Dipakai saat engineer ingin memastikan PLAN tetap deterministik untuk input yang sama.

Contoh fixture nyata:
- `PLAN_FIXTURE_A_TIES_V1.json` — fixture PLAN kaya untuk ties, quantile cutoff, plan levels, dan hashing,
- `plan_items_artificial_ties.json` — fixture kecil untuk tie-break,
- `plan_items_guard_fail.json` — fixture kecil untuk guard fail,
- `plan_items_forced_watch_only.json` — fixture kecil untuk forced watch-only,
- `plan_items_no_trade_hide_all.json` — fixture NO_TRADE saat semua gugur,
- `plan_items_no_trade_min_eligible.json` — fixture NO_TRADE saat eligible kurang dari minimum,
- `scored_items_quantile_cutoff.json` — fixture cutoff kuantil.

Kapan dipakai:
- saat membangun ranking atau grouping,
- saat memeriksa tie-break deterministik,
- saat bug hanya muncul pada skenario guard fail atau NO_TRADE.

### C. CONFIRM fixtures
Dipakai saat engineer ingin memeriksa overlay intraday tanpa menulis ulang hasil PLAN.

Contoh fixture nyata:
- `confirm_immutability_pair.json` — pair untuk memastikan PLAN tidak berubah,
- `confirm_snapshots_two.json` — dua snapshot untuk membaca perilaku overlay,
- `confirm_payload_with_orderbook_fields.json` — payload dengan field tambahan yang masih diperbolehkan,
- `confirm_payload_with_unknown_top_level_field.json` — payload dengan field top-level liar yang biasanya dipakai untuk mengecek apakah strictness berjalan sesuai kontrak normatif.

Kapan dipakai:
- saat membangun parser input intraday,
- saat memeriksa stale snapshot atau strictness,
- saat bug hanya muncul pada payload CONFIRM tertentu.

### D. Hash/vector fixtures
Dipakai saat engineer ingin cross-check perilaku hashing terhadap payload yang sudah dikenal.

Contoh fixture nyata:
- `hash_contract_vectors.json` — vector hash referensial untuk memeriksa payload canonical,
- `PLAN_FIXTURE_A_TIES_V1.json` — juga berguna karena memuat canonical payload dan expected hash.

Kapan dipakai:
- saat mengubah canonicalization,
- saat ada mismatch `plan_hash`,
- saat ingin memastikan perubahan payload benar-benar mengubah hash.

### E. Governance / backtest fixtures
Dipakai saat reviewer ingin menelusuri konsistensi bukti atau artefak evaluasi.

Contoh fixture nyata:
- `artifact_reference_guard_minimal.json` — guard referensi artefak,
- `bt_coverage_guard_minimal.json` — coverage minimal,
- `bt_eval_metrics_minimal.json` — sample metrics,
- `bt_oos_proof_minimal.json` — sample OOS proof,
- `universe_equivalence_sample.json` — sample equivalence prod vs BT,
- `plan_universe_snapshot_sample.json` — sample snapshot universe PLAN.

Kapan dipakai:
- sebelum review hasil riset,
- saat promote paramset dari BT,
- saat ingin audit cepat jejak evidence dan artefak.

## Fixture to Purpose Mapping (Reference)

| Fixture / category | Biasanya dipakai untuk | Pertanyaan yang dijawab | Dokumen normatif yang perlu dibuka |
|---|---|---|---|
| `paramset_valid.json` + validator fixtures | input/paramset validation | Apakah input salah ditolak dengan benar? | `04_WS_PARAMSET_JSON_CONTRACT.md`, `13_WS_CONTRACT_TEST_CHECKLIST.md` |
| `PLAN_FIXTURE_A_TIES_V1.json` | PLAN determinism, plan levels, hash | Apakah run PLAN kaya tetap stabil? | `02_WS_CANONICAL_RUNTIME_FLOW.md`, `07_WS_REASON_CODES_AND_HASH.md` |
| `plan_items_artificial_ties.json` | tie-break diagnosis | Apakah ranking tetap deterministik saat skor sama? | `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md` |
| `plan_items_guard_fail.json` | guard fail | Apakah kandidat gagal ditempatkan dengan reason yang benar? | `02_WS_CANONICAL_RUNTIME_FLOW.md` |
| `plan_items_forced_watch_only.json` | watch-only forcing | Apakah kandidat yang lewat guard tetap bisa diturunkan menjadi watch-only? | `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md` |
| `confirm_immutability_pair.json` | overlay / immutability | Apakah CONFIRM membaca PLAN tanpa menulis ulang? | `10_WS_CONFIRM_OVERLAY.md`, `13_WS_CONTRACT_TEST_CHECKLIST.md` |
| `confirm_payload_with_unknown_top_level_field.json` | strictness / invalid schema drift | Apakah payload liar ditolak dengan benar? | `10_WS_CONFIRM_OVERLAY.md`, `13_WS_CONTRACT_TEST_CHECKLIST.md` |
| `hash_contract_vectors.json` | hash contract | Apakah payload canonical menghasilkan hash yang benar? | `07_WS_REASON_CODES_AND_HASH.md`, `13_WS_CONTRACT_TEST_CHECKLIST.md` |
| Governance / backtest fixtures | audit artefak | Apakah jejak evaluasi dan bukti tersedia? | `17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`, `18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md` |

## Fixture usage patterns

### Pola 1 — Bangun test dari tujuan, bukan dari nama file
Mulailah dari pertanyaan uji seperti “ingin memeriksa determinism PLAN” atau “ingin memeriksa stale snapshot pada CONFIRM”, lalu pilih fixture category yang cocok. Ini menghindari situasi di mana engineer membuka fixture acak yang terlalu kaya untuk bug yang sebenarnya sempit.

### Pola 2 — Pakai fixture kecil untuk diagnosis, fixture kaya untuk walkthrough
- Untuk bug ranking/tie-break, mulai dari `plan_items_artificial_ties.json`.
- Untuk bug guard atau forced watch-only, mulai dari fixture PLAN kecil yang spesifik.
- Untuk bug lintas langkah atau hash, baru naik ke `PLAN_FIXTURE_A_TIES_V1.json`.

### Pola 3 — Pisahkan fixture pembaca hasil dari fixture pembentuk kontrak
Fixture membantu menguji perilaku yang sudah dikontrakkan oleh dokumen normatif. Jika engineer menemukan perilaku baru dari fixture tetapi perilaku itu tidak hidup di dokumen bernomor, yang harus diperiksa lebih dulu adalah apakah fixture terlalu kaya asumsi atau dokumen normatif memang belum lengkap.

### Pola 4 — Saat debugging, sempitkan dulu sebelum membesarkan fixture
Jika bug hanya muncul pada satu aspek, jangan langsung memakai fixture paling kaya. Mulai dari fixture yang memuat hanya field yang dibutuhkan untuk mereproduksi masalah. Setelah perilaku dasar benar, baru gunakan fixture yang lebih kaya untuk memastikan interaksi antarbagian tetap aman.


### Pola 5 — Saat bug kompleks, susun fixture ladder
Untuk bug yang melibatkan lebih dari satu lapisan, gunakan urutan berikut:
1. mulai dari fixture validator atau fixture fixture runtime canonical kecil untuk memastikan perilaku dasar memang salah,
2. naik ke fixture yang sedikit lebih kaya untuk melihat apakah interaksi antarfield atau antarlangkah mulai berpengaruh,
3. pakai fixture kaya seperti `PLAN_FIXTURE_A_TIES_V1.json` atau pair example hanya setelah perilaku dasar sudah terisolasi.

Pendekatan ini membantu engineer membedakan bug kontrak, bug parser, bug grouping, dan bug overlay tanpa langsung tenggelam di fixture yang terlalu besar.

Untuk kewajiban test minimum, gunakan `13_WS_CONTRACT_TEST_CHECKLIST.md`.

## Recommendation Fixture Inventory

Fixture recommendation harus mencakup empty-set behavior, capital-aware allocation, deterministic replay, dan coexistence antara recommendation dan confirm.


## Recommendation and Confirm Boundary Fixtures

- `fixtures/recommendation_empty_but_prioritized_groups_nonempty.json`
- `fixtures/candidate_confirm_non_recommended.json`
- `fixtures/recommended_and_confirmed_pair.json`
- `fixtures/recommended_and_confirmed_pair_detailed.json`
- `fixtures/recommendation_capital_free_vs_capital_aware_pair.json`
