# 22 — WS Recommendation Overview

## Purpose

Dokumen ini menetapkan tujuan, scope, boundary, dan posisi normatif layer **RECOMMENDATION** dalam Weekly Swing watchlist.

RECOMMENDATION adalah lapisan watchlist yang dibentuk **hanya** dari output **PLAN/EOD** yang sudah immutable untuk `trade_date` yang sama. RECOMMENDATION dipakai untuk memprioritaskan ticker yang paling layak disarankan oleh sistem pada hari tersebut.

## Scope

Dokumen ini mencakup:
1. tujuan layer RECOMMENDATION;
2. hubungan RECOMMENDATION dengan PLAN;
3. hubungan RECOMMENDATION dengan CONFIRM;
4. hubungan RECOMMENDATION dengan optional capital input;
5. aturan boundary agar RECOMMENDATION tetap berada dalam domain watchlist.

## Relationship to PLAN

RECOMMENDATION **MUST** dibentuk hanya dari immutable PLAN output untuk `trade_date` yang sama.

## Relationship to CONFIRM

RECOMMENDATION:
- dapat tersedia walaupun CONFIRM belum dijalankan;
- tidak membaca hasil CONFIRM;
- tidak berubah akibat hasil CONFIRM.

Jika sebuah ticker berada pada recommendation set lalu juga memiliki hasil CONFIRM yang valid, maka hasil CONFIRM **MAY** dibaca consumer sebagai **strengthening signal**.

## Relationship to Optional Capital Input

RECOMMENDATION **MAY** berjalan dalam dua mode:
1. `CAPITAL_FREE`
2. `CAPITAL_AWARE`

## Final Rule

1. RECOMMENDATION **MUST** berasal hanya dari immutable PLAN output.
2. RECOMMENDATION **MUST** dapat tersedia tanpa CONFIRM.
3. RECOMMENDATION **MAY** kosong walaupun `TOP_PICKS` dan/atau `SECONDARY` tidak kosong.
4. RECOMMENDATION **MUST NOT** dipengaruhi oleh hasil CONFIRM.
5. CONFIRM **MUST NOT** mengubah recommendation membership, rank, score, atau label.
