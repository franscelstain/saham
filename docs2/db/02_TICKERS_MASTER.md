# 02 — Tickers Master Contract

## Purpose
Kontrak master tickers untuk membangun universe.

## Required table (logical)
Nama bebas (mis. `tickers`), minimal menyediakan:

## Required columns (minimum)
- `ticker_id` (BIGINT/INT, PK)
- `ticker_code` (VARCHAR, unique)
- `is_active` (ENUM('Yes','No') atau BOOLEAN)
- `board` / `exchange` (optional, untuk filter policy)

## Required behaviors
- Universe default: semua ticker dengan `is_active='Yes'` (kecuali exclude list dari paramset).
