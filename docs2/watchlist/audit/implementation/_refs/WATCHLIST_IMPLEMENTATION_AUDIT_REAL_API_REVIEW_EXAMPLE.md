# Watchlist Implementation Audit — Real API Review Example

## Purpose
Contoh pola audit ketika reviewer menerima payload API nyata dari aplikasi watchlist.

## Fokus Review
- response tetap watchlist only;
- recommendation tetap dapat tampil tanpa confirm;
- confirm non-recommended tetap ditandai sebagai candidate validation only;
- serializer tidak menambah rule bisnis.

## Minimal Temuan yang Dicari
1. field mana yang berasal dari PLAN;
2. field mana yang berasal dari RECOMMENDATION;
3. field mana yang berasal dari CONFIRM;
4. apakah ada field campuran yang tidak jelas source-nya.
