# Watchlist Audit Short

Gunakan audit cepat ini sebelum audit penuh.

## Quick Pass / Fail

- [ ] scope masih watchlist only
- [ ] policy aktif masih weekly_swing only
- [ ] tidak melebar ke portfolio
- [ ] tidak melebar ke execution
- [ ] tidak melebar ke market-data internals
- [ ] recommendation dari PLAN only
- [ ] recommendation bisa kosong
- [ ] confirm dari candidate PLAN
- [ ] non-recommended candidate bisa confirm
- [ ] confirm tidak mengubah recommendation
- [ ] blueprint implementasi masih sinkron

## Quick Verdict

- semua centang aman -> lanjut audit penuh
- ada 1 boundary besar gagal -> jangan nilai tinggi
- ada scope leak -> langsung turunkan nilai keras
