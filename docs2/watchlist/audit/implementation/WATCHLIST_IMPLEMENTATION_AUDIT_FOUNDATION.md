# Watchlist Implementation Audit Foundation

## Purpose

Dokumen ini mengunci prinsip audit untuk implementasi watchlist. Audit ini dipakai untuk menilai apakah code, API, persistence, dan delivery layer tetap tunduk pada baseline system docs watchlist.

## Scope

Audit implementasi watchlist mencakup:
- mapping module ke baseline `weekly_swing`;
- runtime artifact flow `PLAN -> RECOMMENDATION -> CONFIRM`;
- API/consumer contract guidance;
- persistence boundary antar artefak watchlist;
- implementation test coverage;
- delivery readiness watchlist;
- hook awal untuk review code/app nyata.

Audit implementasi watchlist tidak mencakup:
- portfolio state;
- execution order placement;
- realized/unrealized PnL;
- market-data ingestion internals;
- provider fetch/retry/scheduler upstream.

## Locked Scope

1. Watchlist implementation **MUST** tetap berada pada domain saran.
2. Implementasi saat ini **MUST** fokus pada `weekly_swing` saja.
3. Data input **MAY** berasal dari provider gratis atau input manual; implementasi **MUST** tetap stabil untuk constraint itu.
4. Baseline system docs di [`../../system/`](../../system/) adalah source of truth untuk implementasi.

## Layer Boundary Rule

Default pembacaan folder implementation audit adalah **Layer B (implementation guidance)**.
Layer C baru aktif bila ZIP memuat bukti implementasi nyata yang cukup, misalnya code aplikasi, payload runtime dari app nyata, API response nyata, persistence runtime nyata, atau hasil review app yang bisa ditelusuri.
Examples, fixtures, schema docs, SQL support, dan sample JSON **tidak otomatis** mengaktifkan Layer C.

## Core Implementation Rules

1. `PLAN` implementation **MUST** menjadi akar artefak watchlist.
2. `RECOMMENDATION` implementation **MUST** dibentuk hanya dari `PLAN` immutable.
3. `CONFIRM` implementation **MUST** berasal dari candidate PLAN binding.
4. `CONFIRM` implementation **MUST NOT** membentuk atau mengubah recommendation.
5. API watchlist **MUST NOT** mengekspos perilaku yang menyiratkan buy/sell execution.
6. Persistence watchlist **MUST** memisahkan artefak PLAN, RECOMMENDATION, dan CONFIRM.

## Additional Boundary Expectations

1. Service/repository boundaries **SHOULD** menjaga separation of concerns antar PLAN, RECOMMENDATION, dan CONFIRM.
2. Serializer/presenter boundaries **MUST NOT** menjadi owner logika bisnis watchlist.
3. Validator untuk manual input **SHOULD** tetap eksplisit agar implementasi tetap stabil untuk provider gratis atau input manual.
4. Review code/app nyata **SHOULD** tetap memetakan temuan ke module, payload, persistence, atau test boundary yang jelas.

## Audit Outputs

Audit implementasi minimal harus dapat menjawab:
- apakah module mapping sudah tunduk pada system docs;
- apakah payload aplikasi masih sesuai kontrak;
- apakah persistence boundary tetap bersih;
- apakah test implementation cukup menutup rule inti;
- apakah delivery checklist cukup untuk memulai coding/review;
- apakah hasil review code/app nyata masih dapat ditelusuri ke baseline bisnis yang benar.

## Final Rule

Audit implementasi watchlist adalah guardrail terhadap implementasi aplikasi. Audit ini tidak boleh mengambil alih owner rule bisnis dari [`../../system/`](../../system/).


## Real-App Evidence Rule

Jika tersedia artefak code, API response, log runtime, atau screenshot hasil review aplikasi, audit implementasi **SHOULD** menilai bukti tersebut secara langsung dan tidak berhenti pada guidance dokumen saja.

Jika bukti code/app belum tersedia **dan Layer C memang tidak aktif**, audit **MUST** menandai item real-app evidence sebagai `N/A`, bukan `PARTIAL`. Lihat [`WATCHLIST_LAYER_C_APPLICABILITY_RULE.md`](./WATCHLIST_LAYER_C_APPLICABILITY_RULE.md).

Jika bukti code/app belum tersedia **padahal Layer C aktif**, audit **MAY** memakai contoh pola review, tetapi status area Layer C dapat ditahan di `PARTIAL` sampai ada bukti implementasi nyata yang cukup.
