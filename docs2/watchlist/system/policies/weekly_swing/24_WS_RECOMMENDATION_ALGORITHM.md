# 24 — WS Recommendation Algorithm

## Purpose

Dokumen ini menetapkan algoritma normatif untuk layer **RECOMMENDATION** pada Weekly Swing.

## A. Source Universe (LOCKED)

Recommendation source universe **MUST** dibentuk dari item PLAN yang sah untuk `trade_date` yang sama.

Source universe default recommendation adalah item PLAN pada group prioritas strategy, yaitu:
- `TOP_PICKS`
- `SECONDARY`

## B. Capital-Free Mode (LOCKED)

Jika capital input tidak tersedia, recommendation **MUST** tetap berjalan dalam `CAPITAL_FREE` mode.

## C. Capital-Aware Mode (LOCKED)

Jika capital input tersedia, recommendation **MAY** berjalan dalam `CAPITAL_AWARE` mode.

## D. Recommendation Score Components (LOCKED)

`recommendation_score` **MUST** dibentuk dari komponen yang hanya bersumber dari PLAN dan policy aktif.

## E. Selection Pipeline (LOCKED)

1. bind immutable PLAN output
2. form source universe recommendation
3. compute `recommendation_score`
4. sort items using deterministic ranking keys
5. resolve dynamic recommendation count
6. mark final `recommended_flag`
7. assemble recommendation output

## F. Dynamic Recommendation Count (LOCKED)

Jumlah ticker recommendation **MUST** ditentukan secara algoritmik, bukan hard-coded manual per hari.
Recommendation count **MAY** bernilai nol.

## G. Empty Recommendation Rule (LOCKED)

Recommendation set **MAY** kosong, termasuk ketika source universe recommendation tidak kosong.

## H. Tie-Breaker (LOCKED)

Jika dua atau lebih item memiliki `recommendation_score` yang sama, sistem **MUST** menerapkan tie-breaker deterministik.

## I. Determinism Rule (LOCKED)

Untuk PLAN snapshot yang sama, policy yang sama, dan optional capital input yang sama, recommendation output **MUST** identik pada replay.

## K. Final Rule

1. recommendation hanya membaca immutable PLAN output;
2. recommendation source universe default berasal dari `TOP_PICKS` dan `SECONDARY`;
3. recommendation count harus dinamis dan dapat bernilai nol;
4. recommendation ranking harus deterministic;
5. recommendation output harus replayable tanpa membaca CONFIRM.
