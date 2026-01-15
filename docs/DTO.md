# Data Transfer Object (DTO) adalah objek sederhana untuk membawa data dari satu layer ke layer lain
tanpa:
    1. logika bisnis,
    2. query database,
    3. efek samping.

Isinya cuma data + struktur yang jelas.

# Kenapa DTO penting di sistem trade kamu
Karena sistem kamu:
    1. banyak tahap (data → guard → signal → planning → output),
    2. banyak istilah (RR, BE, entry, SL, TP, status, reason),
    3. dan nanti dipakai ulang di Watchlist dan Portfolio.

# Tanpa DTO:
    1. data jadi array campur aduk,
    2. field gampang ketukar,
    3. kamu sendiri bingung “ini data datang dari mana, berubah di mana”

# DTO vs Model vs Array (biar nggak ketukar)

**DTO**
    1. Tujuan: bawa data antar layer
    2. Isinya: data mentah / data hasil proses
    3. Tidak ada query, tidak ada business rule
    4. Cocok untuk Watchlist → Trade engine → Portfolio

**Model (Eloquent)**
    1. Tujuan: representasi tabel
    2. Isinya: data + relasi + query
    3. Tidak cocok dipakai langsung di core trading logic (berat & side effect)

**Array**
    1. Fleksibel tapi rawan salah
    2. Tidak jelas kontrak datanya
    3. Mudah bikin bug halus (key typo, missing field, dsb)

# Kenapa DTO bikin SRP kamu jalan

Dengan DTO:
    1. Repository → menghasilkan DTO (bukan array liar)
    2. Guard → terima DTO, return GuardResult
    3. SignalClassifier → terima DTO, return SignalDecision
    4. TradePlanner → terima DTO + SignalDecision, return TradePlan

Setiap layer:
    1. tahu apa inputnya
    2. tahu apa outputnya
    3. dan tidak tahu detail layer lain

# Apakah DTO wajib di Laravel?
Tidak wajib, tapi sangat direkomendasikan untuk:
    1. sistem kompleks (trading, finance),
    2. logic yang dipakai lintas menu (Watchlist & Portfolio),
    3. dan code yang mau kamu pahami lagi 6 bulan ke depan.

## DTO adalah “bungkus data” supaya logika trading kamu tidak tergantung array mentah atau model database.
## DTO sengaja diletakkan di luar Service karena DTO bukan milik satu service.
## DTO itu kontrak data lintas layer, bukan implementasi logic.
## Perannya membawa data dari satu layer ke layer lain secara eksplisit dan konsisten
## DTO tidak punya logika, tidak query DB, tidak tahu siapa yang memakainya.

Karena itu:
    1. dia dipakai oleh banyak Service
    2. bahkan bisa dipakai Controller, Service, Guard, Planner, Portfolio

## Hubungan DTO dengan SRP (ini kuncinya)
SRP = satu alasan untuk berubah
    1. DTO berubah kalau struktur data berubah
    2. Service berubah kalau aturan bisnis berubah
Kalau DTO di dalam Service:
    1. satu perubahan struktur data
    2. bisa “memaksa” banyak perubahan service
Kalau DTO berdiri sendiri:
    1. perubahan lebih terkontrol
    2. dependency lebih jelas