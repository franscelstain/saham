# 18 — WS Backtest Artifact Manifest (LOCKED)

## Purpose
Menjadi allowlist resmi untuk artefak backtest, calibration, dan proof yang dianggap **exist**, **valid**, dan **boleh dirujuk** oleh dokumen Weekly Swing.

Dokumen ini mencegah drift akibat penyebutan tabel/file/artefak yang tidak resmi atau tidak lagi dipakai.

## Scope

Dokumen ini adalah allowlist artefak resmi WS; artefak di luar daftar ini dianggap non-scope sampai dinyatakan resmi.

Dokumen ini berlaku untuk:
- artefak tabel backtest resmi,
- artefak proof produksi resmi,
- dan dokumen pengendali yang sah untuk artefak tersebut.

Dokumen ini tidak memasukkan fixture test sebagai artefak produksi.
Dokumen ini juga tidak memutihkan artefak lama yang belum masuk allowlist.

## Inputs
- desain backtest WS,
- kebutuhan calibration/evidence/promote,
- dan dokumen proof lintas sistem.

## Outputs
- daftar artefak resmi yang boleh dirujuk,
- klasifikasi artefak,
- aturan referensi silang,
- aturan penanganan artefak non-scope.

## Prerequisites
- [`17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`](17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md)
- [`19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md`](19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md)

## 1) Official WS Backtest Tables (LOCKED)
Tabel berikut adalah artefak backtest resmi Weekly Swing:
1. `watchlist_bt_param_grid`
2. `watchlist_bt_eval`
3. `watchlist_bt_picks_ws`
4. `watchlist_bt_universe_ws`
5. `watchlist_bt_cutoffs_ws`
6. `watchlist_bt_oos_eval_ws`

Aturan:
- nama tabel harus dipakai persis seperti di atas,
- penyebutan alias/deskripsi boleh, tapi nama artefak resminya tidak boleh berubah,
- dan artefak lain tidak boleh dianggap resmi hanya karena “mirip” fungsinya.

## 2) Official Production Proof Artifact (LOCKED)

Shape proof resmi boleh diekspor ke media berbeda, tetapi konsumen tidak boleh mengandalkan nama file ad-hoc sebagai kontrak.

Artefak proof produksi resmi yang dibutuhkan untuk equivalence adalah:
1. export PLAN universe snapshot dengan shape mengikuti:
   - [`db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`](db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md)

Catatan:
- ini adalah **shape/contract artefak proof**, bukan nama tabel fisik baku,
- implementasi boleh mengekspor ke file JSON/CSV atau bentuk lain selama shape-nya identik terhadap kontrak schema export resmi.

## 3) Official Supporting Governance Docs (LOCKED)

Dokumen pendukung di daftar ini membantu governance artefak, tetapi tidak otomatis mengubah status artefak fisik menjadi resmi.

Dokumen resmi yang mengendalikan artefak di atas adalah:
- [`12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`](12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md)
- [`14_WS_BT_COVERAGE_MATRIX_LOCKED.md`](14_WS_BT_COVERAGE_MATRIX_LOCKED.md)
- [`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)
- [`16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`](16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md)
- [`17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`](17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md)
- [`_refs/WS_OOS_EVIDENCE_NOTE.md`](_refs/WS_OOS_EVIDENCE_NOTE.md) — catatan referensi bukti, bukan sumber aturan utama
- [`db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`](db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md)

## 4) Explicit Non-members (LOCKED)
Artefak berikut **bukan** anggota manifest ini kecuali kelak ditambahkan secara eksplisit:
- fixtures pada folder [`fixtures/`](fixtures/README.md)
- examples pada folder [`examples/`](examples/README.md)
- ledger/history notes
- export ad-hoc yang tidak punya kontrak resmi
- tabel/folder/file yang hanya pernah muncul di diskusi lama

## 5) Reference Rule (LOCKED)

Setiap penyebutan artefak resmi di dokumen lain harus memakai nama manifest yang sama dan tidak boleh memakai sinonim yang menciptakan artefak bayangan.

Jika dokumen Weekly Swing menyebut artefak sebagai:
- wajib,
- resmi,
- dipakai promote,
- dipakai proof,
- atau dipakai calibration,

maka artefak itu wajib memenuhi salah satu dari dua kondisi berikut:
1. tercantum pada bagian `Official WS Backtest Tables`, atau
2. tercantum pada bagian `Official Production Proof Artifact`.

Jika tidak, maka dokumen tersebut melakukan **artifact reference violation**.

## 6) Relationship with Deprecation Ledger
Jika sebuah artefak pernah disebut tetapi tidak ada di manifest ini, maka default status-nya adalah:
- non-scope / deprecated

dan artefak tersebut harus dicatat pada:
- [`19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md`](19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md)

## 7) Acceptance Rule (LOCKED)
Sebuah patch Weekly Swing hanya dianggap bersih secara artefak jika:
- semua artefak wajib berada di manifest ini,
- tidak ada artefak “hantu” yang disebut sebagai resmi,
- dan semua artefak non-scope tercatat pada ledger.

## Next
- [`19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md`](19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md)
