# 07 — WS Delivery Checklist

## Purpose

Checklist ini dipakai saat menerjemahkan Weekly Swing dari system docs ke aplikasi watchlist.

## Pre-Build
- [ ] baseline `weekly_swing` sudah difreeze
- [ ] audit baseline watchlist digunakan sebagai guardrail
- [ ] owner docs sudah dipahami

## Build
- [ ] `PLAN` engine dipisah dari `RECOMMENDATION`
- [ ] `RECOMMENDATION` engine tidak membaca `CONFIRM`
- [ ] `CONFIRM` engine memakai candidate `PLAN` binding
- [ ] consumer view tidak mengubah source semantics

## Read / API
- [ ] endpoint watchlist hanya bersifat read/suggestion
- [ ] tidak ada endpoint buy/sell di domain ini
- [ ] confirm input manual tervalidasi

## Persistence
- [ ] artifact watchlist dipisah: PLAN / RECOMMENDATION / CONFIRM
- [ ] tidak ada persistence holdings/portfolio di domain ini

## Testing
- [ ] deterministic tests lulus
- [ ] contract tests lulus
- [ ] empty recommendation tests lulus
- [ ] non-recommended candidate confirm tests lulus
- [ ] no-mutation tests lulus

## Release Readiness
- [ ] system docs dan implementation guidance sinkron
- [ ] tidak ada leakage ke portfolio
- [ ] tidak ada leakage ke market-data internals
