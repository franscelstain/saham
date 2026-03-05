# Policies — Index

> **Status:** LOCKED (Normative)
> **Doc Role:** Policies catalog & minimum rules


## Purpose
Katalog policy watchlist dan aturan minimum per policy.

## Scope
Dipakai sebelum masuk ke policy spesifik agar pembaca tidak lompat langsung ke algoritma.

## Inputs
- Pembaca yang akan membuat, membaca, atau mengaudit policy.

## Outputs
- Aturan navigasi dan baseline kontrak policy.

**Scope:** Folder ini berisi kumpulan policy watchlist.  
**Audience:** IMPLEMENTER / OPERATOR / REVIEWER.

## Start here
Urutan masuk yang benar:
1. `../policy.md`
2. [`_shared/README.md`](_shared/README.md)
3. [`_shared/01_POLICY_FRAMEWORK_OVERVIEW.md`](_shared/01_POLICY_FRAMEWORK_OVERVIEW.md) sampai [`_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md`](_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md)
4. policy spesifik yang akan dikerjakan

## Struktur
- [`_shared/`](_shared/README.md) — framework global lintas policy.
- [`weekly_swing/`](weekly_swing/README.md) — dokumen spesifik policy Weekly Swing.

## Katalog policy
- [`weekly_swing/`](weekly_swing/README.md) — Weekly Swing (EOD). Mulai dari [`weekly_swing/01_WS_OVERVIEW.md`](weekly_swing/01_WS_OVERVIEW.md).

## Rule
- Policy baru tidak boleh langsung lompat ke algoritma tanpa punya kontrak minimum global.
- Jika policy belum punya registry parameter, validator, execution canonical, dan contract tests, policy itu belum complete.
