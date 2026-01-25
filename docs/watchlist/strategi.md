# TradeAxis Watchlist Policies — Bedanya & Strategi Biar Sama-Sama Profit

Dokumen ini menjelaskan perbedaan tiap **policy** watchlist di TradeAxis dan cara eksekusinya supaya *masing-masing* punya peluang profit yang realistis.

> Prinsip utama: **policy = kerangka kerja**. Profit datang dari **disiplin eksekusi** (window, anti-chasing, cut loss/time stop, sizing).

---

## 1) WEEKLY_SWING

### Tujuan
Swing **2–7 hari bursa**. Entry ideal dari setup EOD yang kuat, dieksekusi disiplin di jam tertentu.

### Ciri Utama
- Fokus “mingguan”, bukan intraday.
- Menghindari entry pada hari yang rawan/kurang efisien.

### Rule Pembeda (inti)
- **No new entry:** **Senin & Jumat** (`WS_DOW_NO_ENTRY`)
- Entry window default: **09:20–10:30** dan **13:35–14:30**
- **Anti-chasing:** jika harga eksekusi > `close*(1+2%)` → **WATCH_ONLY**
- **Gap-up guard:** jika preopen > `close*(1+3%)` → **WATCH_ONLY**
- Ada **time stop** (T+2 / T+3) kalau tidak follow-through

### Strategi Profit yang Realistis
1. Entry cuma di **window**, pakai **limit**, jangan ngejar.
2. Prioritaskan kandidat yang statusnya **NEW ENTRY eligible**, bukan WATCH_ONLY.
3. Jika sampai **T+2 belum jalan** (misal belum +1% atau gagal follow-through), **keluar sesuai rule**.
4. Jangan “mengubah trade jadi investasi” hanya karena belum naik.

---

## 2) DIVIDEND_SWING

### Tujuan
Main peluang **dividen + swing pendek**, tapi dengan pagar ketat supaya tidak terjebak gap/event risk.

### Ciri Utama
- Bukan “dividend capture nekat”.
- Butuh data event + snapshot yang valid.

### Rule Pembeda (inti)
- Event gate: `cum_date` harus dalam **3–12 trading days ke depan**
- **Preopen snapshot wajib**; kalau tidak ada → **WATCH_ONLY** (`DS_PREOPEN_PRICE_MISSING`)
- **Anti-chasing lebih ketat:** 1.5%
- **Gap-up guard lebih ketat:** 2%
- Breakout diblok bila sudah “late-cycle” (mis. `days_to_cum <= 4`)

### Strategi Profit yang Realistis
1. Jangan paksa entry kalau snapshot/preopen belum ada. Itu bukan bug, itu guard.
2. Main aman: cari swing **sebelum ex-date** (profit dari run-up, bukan berharap “capture”).
3. Pilih yang “worth it”: yield kecil biasanya bikin edge tipis (biaya + slippage bisa makan).

---

## 3) INTRADAY_LIGHT

### Tujuan
Intraday cepat (maks ±3 jam). Bukan scalping brutal, tapi “momentum ringan”.

### Ciri Utama
- Hanya valid kalau snapshot intraday/preopen tersedia.
- Liquidity harus top.

### Rule Pembeda (inti)
- **Snapshot intraday wajib**; tanpa itu → invalid/ditahan (`IL_SNAPSHOT_MISSING`)
- Liquidity bucket **A saja**
- ATR% dibatasi (mis. <= 6%)
- **Anti-chasing super ketat:** 1%
- **Gap guard:** 1.5%
- Wajib **flat sebelum close**

### Strategi Profit yang Realistis
1. Pakai saat kamu **siap mantengin**. Kalau tidak bisa, jangan.
2. Biasanya **1 posisi saja** (max_positions=1).
3. Entry di window, exit cepat kalau tidak jalan (time stop 60–90 menit).
4. Tujuannya win-rate dan disiplin, bukan cari 10% sehari.

---

## 4) POSITION_TRADE

### Tujuan
Trend-follow **2–8 minggu**. Lebih jarang trade, lebih “stabil” kalau market trend.

### Ciri Utama
- Butuh trend yang benar-benar sehat.
- Target RR lebih tinggi.

### Rule Pembeda (inti)
- Trend gate keras: `close > MA200` dan `MA50 > MA200`
- RR minimum biasanya lebih tinggi (>= 2.0 kalau level lengkap)
- Bisa ada partial TP + trailing (mis. ATR 2.5)
- Default **tidak pyramiding** (tidak menambah posisi bertahap)

### Strategi Profit yang Realistis
1. Jangan dipakai untuk target mingguan. Ini buat **market yang sedang risk-on/trending**.
2. Masuk rapi, lalu biarkan trailing bekerja.
3. Hindari overtrade. Position trade menang karena “winner dibiarkan panjang”.

---

## 5) NO_TRADE

### Tujuan
Proteksi modal: **tidak membuka posisi baru**.

### Kapan Dipakai
- Data EOD/canonical belum beres.
- Market regime risk-off / breadth jelek.
- Trigger proteksi sistem aktif.

### “Strategi Profit”
Profit di sini artinya **tidak rugi** saat probabilitas jelek.
- Kalau ada posisi existing → fokus **reduce/exit/trailing**, bukan tambah.

---

# Cara Biar Semua Policy Punya Peluang Profit (aturan main universal)

## A) Pilih policy sesuai kondisi & komitmen waktu
- Bisa mantengin? → **INTRADAY_LIGHT**
- Mau swing 2–7 hari? → **WEEKLY_SWING**
- Ada event dividen valid + snapshot ada? → **DIVIDEND_SWING**
- Market trend rapi? → **POSITION_TRADE**
- Data/market jelek? → **NO_TRADE**

## B) Jangan lawan reason codes
Kalau output WATCH_ONLY karena:
- `*_GAP_UP_BLOCK`
- `*_CHASE_BLOCK`
- `*_PREOPEN_PRICE_MISSING`
itu bukan “saran halus”. Itu **pagar** supaya kamu tidak beli di tempat bodoh.

## C) Entry cuma di execution window
Tujuannya:
- mengurangi slippage
- menghindari opening chaos
- disiplin plan > emosional

## D) Cut cepat kalau setup tidak jalan (time stop)
Pola konsisten:
- loser kecil
- winner dibiarkan

Kalau tidak jalan sesuai skenario, **keluar**. Jangan ubah thesis.

## E) Hormati sizing/max positions policy
- Weekly/Dividend biasanya max 2
- Intraday 1
- Position 3
Tujuannya mencegah overexposure & mental overload.

---

# Checklist Cepat Saat Membaca Output Preopen

1. Pastikan `policy_id`/`policy_code` sesuai yang kamu panggil.
2. Lihat status:
   - **NEW_ENTRY eligible** → kandidat eksekusi
   - **WATCH_ONLY / NO_TRADE** → kandidat pantau / jangan entry
3. Cek `trade_disabled_reason` (atau reason serupa).
4. Cek `eligibility_block_codes` untuk tahu “kenapa” diblok.
5. Eksekusi hanya kalau:
   - tidak kena gap/chase block
   - data snapshot ada (khusus IL/DS)
   - masih dalam window eksekusi

---

## Catatan
Dokumen ini mengikuti konsep guardrails yang umum ada di `docs/watchlist/*`. Nama field output bisa sedikit berbeda tergantung schema versi kamu, tapi intinya sama:
- **policy → rules**
- **reason codes → diagnosis**
- **window + guard + time stop → mesin disiplin**

