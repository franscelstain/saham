# Portfolio (TradeAxis)

Dokumen ini adalah **kontrak inti** fitur Portfolio di TradeAxis: bagaimana posisi tercipta dari plan watchlist, bagaimana lifecycle dikelola, dan bagaimana profit dianalisa secara deterministik.

## Scope

### Tujuan
- Menampilkan semua **posisi aktif** lintas policy (Weekly Swing, Dividend Swing, Intraday Light, Position Trade).
- Mengelola lifecycle posisi: OPEN → MANAGE → CLOSE.
- Menghasilkan analitik profit yang bisa diaudit (realized/unrealized, win rate, expectancy, dll).

### Non-goals
- Portfolio **bukan** mesin pemilih kandidat. Itu tugas Watchlist.
- Portfolio **tidak** boleh menghitung ulang sinyal watchlist untuk posisi yang sudah OPEN.

## Prinsip & Invariant (Kontrak Keras)

1. **Plan Snapshot Immutable saat OPEN**
   - Saat entry terbentuk (BUY filled), portfolio menyimpan `plan_snapshot_json` dan itu tidak berubah sampai posisi ditutup.

2. **No Recompute Signal**
   - Setelah posisi OPEN, keputusan manajemen (SL/TP/timebox) mengikuti rule policy portfolio + plan snapshot, bukan re-run watchlist.

3. **Idempotent Ingest**
   - Ingest trade/fill harus idempotent berdasarkan `external_ref`/`trade_hash`.
   - Tidak boleh terjadi double insert yang mengubah P/L.

4. **Deterministik**
   - Valuation dan P/L harus bisa diulang dengan input yang sama (EOD canonical, fee model, rounding rules).

## Terminologi

- **Policy**: keluarga aturan lifecycle (mis. `WEEKLY_SWING`, `INTRADAY_LIGHT`).
- **Strategy**: strategi konkret yang bisa punya 1+ ticker & alloc (dari Watchlist).
- **Plan**: output watchlist yang berisi intent + entry/TP/SL/timebox/alloc/reason.
- **Position**: kepemilikan aktif sebuah ticker hasil eksekusi plan (open sampai close).
- **Leg**: komponen entry tambahan (ADD) atau partial close (REDUCE).
- **Fill/Trade**: eksekusi broker (BUY/SELL) yang masuk ke ledger.

## Data Model (Minimal Contract)

> Nama tabel bisa menyesuaikan implementasi; yang penting field kontraknya.

### Entity: Position
Field wajib:
- `position_id` (pk)
- `account_id`
- `ticker_code`
- `policy_code`
- `strategy_code`
- `plan_id` (atau `plan_ref`)
- `status` ∈ {PLANNED, OPEN, REDUCED, CLOSED, CANCELLED}
- `opened_at`, `closed_at` (nullable)
- `qty_open`, `avg_buy_price`
- `plan_snapshot_json` (immutable)
- `meta_json` (opsional)

### Entity: PortfolioTrade / Fill
Field wajib:
- `trade_id` (pk)
- `account_id`
- `ticker_code`
- `trade_date`
- `side` ∈ {BUY, SELL}
- `qty`, `price`
- `fee_amount`, `tax_amount`
- `external_ref` (nullable) / `broker_ref` (nullable)
- `trade_hash` (unik untuk idempotent)
- `meta_json` (opsional)

### Entity: PositionEvent (Audit Trail)
Field wajib:
- `event_id` (pk)
- `position_id`
- `event_type`
- `event_at`
- `payload_json`

Event types minimal:
- `POSITION_PLANNED`
- `ENTRY_FILLED`
- `ADD_FILLED`
- `REDUCE_FILLED`
- `EXIT_FILLED`
- `STOP_UPDATED`
- `TAKE_PROFIT_UPDATED`
- `TIMEBOX_EXPIRED`
- `POSITION_CLOSED`
- `CANCELLED`

### (Opsional tapi sangat disarankan) Entity: PortfolioDailySnapshot
Tujuan: analitik drawdown/volatility dan evaluasi policy per hari.
Field:
- `snapshot_date`
- `account_id`
- `position_id` (atau per ticker aggregate)
- `ticker_code`
- `qty`
- `mark_price`
- `unrealized_pnl`
- `policy_code`, `strategy_code`

## State Machine (Universal)

### Status Flow (minimal)
- PLANNED → OPEN → (ADD/REDUCE)* → CLOSED
- PLANNED → CANCELLED

### Aturan universal
- Status OPEN tercipta ketika ada BUY fill yang valid untuk posisi itu.
- CLOSED ketika qty net menjadi 0 dan ada event `POSITION_CLOSED`.
- Semua perubahan penting harus membentuk `PositionEvent`.

## Pricing & Valuation

### Source of Truth harga
- **EOD canonical** untuk mark-to-market harian (bila tersedia).
- Jika EOD canonical tidak ada:
  - gunakan aturan **stale policy** (TBD): carry-forward last canonical / skip valuation.

> TODO: final-kan aturan stale/carry-forward karena ini mempengaruhi drawdown/volatility.

## Profit & P/L (Kontrak Final – Net)

### Definisi
- **Buy Cost (net)** = `qty*price + buy_fee + buy_tax(opsional)`
- **Sell Proceeds (net)** = `qty*price - sell_fee - sell_tax`
- **Realized P/L** = `sell_proceeds(net) - matched_buy_cost(net)`
- **Unrealized P/L** = `mark_value - remaining_buy_cost_basis`

### Matching Rule
Pilih salah satu dan kunci:
- FIFO
- Weighted Average (AVG)

> TODO: tentukan pilihan final.

### Rounding & Tick
- Aturan rounding untuk fee/tax, qty, price: **TBD** (harus deterministik).

### Corporate Actions
- Split / reverse split: menyesuaikan qty & cost basis tanpa mengubah P/L ekonomis.
- Cash dividend: masuk ke cash ledger atau performance terpisah (TBD).

## Policy Interface (Portfolio)

Portfolio mengimplementasikan policy lifecycle yang berbeda-beda, tetapi kontrak pemanggilannya seragam:

- `validateBuy(planSnapshot, marketContext) -> Decision`
- `eodRiskEvents(position, planSnapshot, eodContext) -> list<Event>`
- `exitRules(position, planSnapshot, context) -> ExitDecision`
- `cooldownRules(positionHistory, context) -> CooldownDecision`

Detail tiap policy ada di `docs/portfolio/policy/*.md`.

## Analytics Contract

### Metrik minimal (wajib)
Per **closed position**:
- `realized_pnl_net`
- `realized_pnl_pct`
- `hold_days`
- `max_drawdown_pct` (butuh snapshots)
- `exit_reason_code` (TP/SL/TIMEBOX/MANUAL)

Per **policy** dan **periode** (mingguan/bulanan):
- `win_rate`
- `avg_win`, `avg_loss`
- `expectancy`
- `profit_factor`
- `avg_hold_days`

> TODO: definisikan rumus tepat untuk `expectancy`, `profit_factor`, `drawdown`.

## Exit Advisory Framework (Sell / Hold Advisor)

Portfolio berperan sebagai **exit advisor** untuk posisi yang sudah OPEN. Output-nya deterministik: untuk input yang sama, keputusan harus sama.

### Decision (Enum) — Canonical

Portfolio **MUST** menghasilkan `decision` dalam salah satu nilai berikut (canonical):

- `HOLD` — tidak ada aksi.
- `SELL_NOW` — keluar full secepatnya sesuai rule.
- `SELL_PARTIAL` — keluar sebagian sesuai rule, sisanya tetap dikelola.
- `UPDATE_STOP` — update stop (tanpa aksi jual saat ini).

Nilai lain seperti `TAKE_PROFIT`, `CUT_LOSS`, `REDUCE_RISK`, `FORCED_EXIT`, `TRAIL_STOP_UPDATE` **MUST NOT** muncul sebagai `decision`. Nilai-nilai tersebut diekspresikan melalui:
- `reason_codes[]` (mis. `TAKE_PROFIT_HIT`, `STOP_HIT`, `TIMEBOX_EXPIRED`, `PORTFOLIO_HEAT_LIMIT`), dan/atau
- `actions[]` (mis. `SELL FULL`, `SELL PARTIAL`, `UPDATE_STOP`).


### Reason Codes (Enum)
Keputusan wajib punya `reason_codes[]` (bisa lebih dari satu) dan urutannya mengikuti prioritas rule.

Core (lintas policy):
- `STOP_HIT` — harga menembus SL / hard stop.
- `TAKE_PROFIT_HIT` — target profit tercapai.
- `TIMEBOX_EXPIRED` — timebox posisi habis, harus exit.
- `EOD_CUTOFF` — batas sesi (intraday) mendekati penutupan.
- `PORTFOLIO_HEAT_LIMIT` — total risiko portfolio melewati batas.
- `NEGATIVE_EV` — ekspektasi (berdasarkan scorecard policy) sudah negatif untuk posisi ini.
- `THESIS_INVALID` — invalidasi thesis berdasar rule plan (guard/level), bukan recompute indikator.
- `MANUAL_OVERRIDE` — user override (harus tercatat event + payload).

Policy-specific (opsional):
- `DIVIDEND_WINDOW_END` — window event dividen berakhir.
- `OVERNIGHT_NOT_ALLOWED` — intraday tidak boleh menginap.

### Confidence (Rule-based)
`confidence` adalah angka 0–100 yang **MUST** diturunkan dari rule (bukan prediksi).

Default mapping (deterministik):
- Hard exit rules (`STOP_HIT`, `TIMEBOX_EXPIRED`, `EOD_CUTOFF`, `OVERNIGHT_NOT_ALLOWED`) → 95–100
- `TAKE_PROFIT_HIT` → 80–95
- `CONCENTRATION_LIMIT`, `PORTFOLIO_HEAT_LIMIT` → 70–90
- `NEGATIVE_EV`, `THESIS_INVALID` → 60–85
- `HOLD` (no action) → 50–70

Jika lebih dari satu reason aktif, confidence mengambil nilai maksimum dari reason prioritas tertinggi.

### Action Schema (Deterministik)
`actions[]` wajib spesifik, bisa dieksekusi oleh user:

- `{"type":"SELL","mode":"FULL","qty":...,"notes":...}`
- `{"type":"SELL","mode":"PARTIAL","qty":...,"notes":...}`
- `{"type":"UPDATE_STOP","stop_price":...,"notes":...}`

### Trigger Semantics (Anti Salah Tafsir)
Agar deterministik, semua trigger **MUST** menyebut basis harga dan waktu.

**Timezone:** Asia/Jakarta (WIB).

**Price Basis (default):**
- `LIVE_LAST` = last traded price.
- `EOD_CLOSE` = harga penutupan EOD canonical.

Kontrak default:
- Untuk evaluasi intraday/live: gunakan `LIVE_LAST`.
- Untuk evaluasi EOD batch: gunakan `EOD_CLOSE`.

**STOP_HIT:**
- Trigger jika `price_basis <= stop_price`.

**TAKE_PROFIT_HIT:**
- Trigger jika `price_basis >= target_price`.

**TIMEBOX_EXPIRED:**
- Trigger jika `now >= timebox_end_at`.

**EOD_CUTOFF:**
- Trigger jika `now >= session_cutoff_at` untuk policy yang melarang hold melewati cutoff.

> Policy **MAY** override `price_basis` (mis. gunakan bid untuk SELL) tetapi override itu **MUST** tertulis eksplisit di dokumen policy.

### Rule Priority (Deterministic Ordering)
Portfolio harus mengevaluasi rule dengan urutan tetap berikut (paling tinggi dulu). Rule pertama yang memaksa aksi keluar mengunci keputusan, kecuali policy secara eksplisit mendukung ladder (TP → partial+trail).

1. `STOP_HIT` → `SELL_NOW` (wajib)
2. `TIMEBOX_EXPIRED` / `EOD_CUTOFF` → `SELL_NOW` (wajib)
3. `TAKE_PROFIT_HIT` → default `SELL_NOW` (atau `SELL_PARTIAL` jika ladder mode aktif)
4. `PORTFOLIO_HEAT_LIMIT` → `SELL_PARTIAL` / `REDUCE_RISK`
5. `NEGATIVE_EV` → `SELL_PARTIAL` atau `SELL_NOW` (policy-defined)
6. `TRAIL_STOP_UPDATE` → update stop (tanpa jual)
7. else `HOLD`

### Binding Decision (Disiplin)
- Jika output `SELL_NOW` muncul dari prioritas 1–2, keputusan dianggap **final** untuk evaluasi sesi itu.
- Jika output berasal dari `TAKE_PROFIT_HIT`, policy boleh memilih:
  - **Strict TP**: exit full, atau
  - **TP Ladder**: partial exit + trailing stop (harus tertulis di policy doc).

### Allowed Live Updates (Tidak Melanggar Kontrak)
Portfolio boleh menerima update harga live/intraday untuk:
- menghitung `mark_price`, `unrealized_pnl`, `high_watermark`,
- meng-update `trailing_stop` **hanya** jika policy mendefinisikan rule trailing.

Portfolio **tidak boleh**:
- melakukan recompute watchlist / classifier untuk “mengganti” alasan entry,
- mengubah `plan_snapshot_json` untuk posisi OPEN.

### Output DTO Minimal (per position)
- `position_id`, `ticker_code`, `policy_code`, `strategy_code`
- `decision`, `confidence` (0–100, rule-based)
- `reason_codes[]`
- `actions[]`
- `levels`: `entry`, `stop`, `target`, `timebox_end_at`
- `pnl`: `unrealized_pnl_net`, `unrealized_pnl_pct`, `realized_pnl_net` (jika ada partial close)
- `risk`: `risk_R`, `heat_contrib` (opsional tapi disarankan)


## Risk Budget & Position Sizing (Professional Defaults)

Portfolio bukan hanya memberi saran SELL/HOLD, tetapi juga menjaga **survivability**: membatasi kerugian, drawdown, dan overexposure. Bagian ini mendefinisikan kontrak sizing & risk budget yang dipakai lintas policy.

### Risk Units
- `risk_R` (per position) = `abs(entry_price - stop_price)` (per lembar/saham) atau nilai ekuivalen per lot.
- `risk_amount` (per position) = `risk_R * qty` (dalam rupiah).
- `heat_contrib` (per position) = `risk_amount / equity` (dalam %).

> `equity` adalah modal/ekuitas yang dipakai untuk trading (TBD definisi: cash + market value - liabilities).

### Global Risk Budget (Lintas Policy)
Kontrak default (bisa di-config):
- `max_heat_total_pct` — batas total heat semua posisi OPEN (contoh: 2%–5% equity).
- `max_heat_per_position_pct` — batas heat per posisi (contoh: 0.5%–1.5% equity).
- `max_positions_total` — batas jumlah posisi OPEN total.
- `max_positions_per_policy` — batas per policy (opsional).
- `sector_cap_pct` — batas eksposur per sektor/theme (opsional, tapi disarankan).

Jika melanggar budget:
- Portfolio menghasilkan `decision = SELL_PARTIAL` / `REDUCE_RISK` dengan `reason_codes` termasuk `PORTFOLIO_HEAT_LIMIT`.

### Position Sizing (Deterministik)
Sizing harus deterministik dan bisa dijelaskan:
- `qty_target = floor((equity * max_heat_per_position_pct) / risk_R)`
- `qty_final = min(qty_target, liquidity_cap_qty)` (lihat Liquidity Guard)

> Semua pembulatan harus jelas (floor/round) dan konsisten.

### Kill Switch (Safety Rails)
Jika kondisi buruk terjadi, portfolio wajib memberi sinyal pembatasan:
- `policy_kill_switch` jika drawdown policy melewati batas.
- `global_kill_switch` jika drawdown total melewati batas.

Output:
- `decision = REDUCE_RISK` / `SELL_NOW` (untuk posisi tertentu) dan/atau `trading_mode = RISK_OFF` (TBD channel output).

## Liquidity, Slippage, dan Realistic Exits

Profit "di kertas" tidak sama dengan profit yang bisa diambil. Portfolio harus memperhitungkan constraint eksekusi (minimal) untuk memberi saran yang realistis.

### Liquidity Guard (Minimal)
Per posisi, portfolio menghitung:
- `avg_value_traded` (TBD sumber: EOD volume * price, atau intraday)
- `max_participation_pct` (mis. 1%–5% dari value traded)
- `liquidity_cap_qty` = batas qty yang wajar untuk dieksekusi tanpa impact besar.

Jika qty melebihi cap:
- Aksi exit disarankan sebagai `SELL_PARTIAL` bertahap (multi-step actions) atau warning di `actions[]`.

### Slippage Model (Minimal)
Portfolio menyediakan estimasi slippage deterministik:
- `slippage_bps` per policy (mis. intraday lebih tinggi).
- `expected_exit_price = mark_price * (1 - slippage_bps/10000)` untuk SELL (dan kebalikannya untuk BUY jika digunakan).

P/L estimasi harus bisa ditampilkan dalam dua versi:
- `pnl_gross` (tanpa slippage) dan
- `pnl_net_realistic` (dengan slippage + fee/tax).

## Fee, Tax, Rounding, dan Tick Rules (Final Spec Placeholder)

Bagian ini wajib dikunci untuk menghindari perbedaan kecil antar run dan memastikan P/L deterministik.

### Fee/Tax Allocation (Default)
- BUY: `cost_basis += fee + tax_buy(if any)`
- SELL: `proceeds -= fee + tax_sell`

### Rounding
- Semua nominal rupiah dibulatkan dengan aturan jelas (TBD: floor/round/ceil).
- Semua qty dibulatkan ke lot size (TBD untuk BEI: 1 lot = 100 lembar).

### Tick Rules
- Target/stop price harus disesuaikan ke tick size yang valid (TBD: gunakan TickRule).

## Drawdown, Streak, dan Risk-of-Ruin (Analytics)

Portfolio harus menyimpan metrik survive, bukan cuma cuan.

### Metrics wajib
- `max_drawdown_pct` (total & per policy)
- `max_losing_streak` (total & per policy)
- `time_underwater_days` (opsional)

### Policy Health Score (TBD)
- Jika DD atau losing streak melewati threshold: aktifkan `policy_kill_switch`.

> TODO: definisikan threshold default per policy (intraday vs weekly vs position).



## Regime Layer (RISK_ON / NEUTRAL / RISK_OFF)

Trader profesional biasanya memakai **mode sistem** untuk mengatur agresivitas tanpa “menciptakan sinyal baru”.

### Trading Mode (Enum)
- `RISK_ON` — normal/agresif sesuai policy.
- `NEUTRAL` — konservatif: sizing diperkecil, entry ketat (TBD).
- `RISK_OFF` — defensif: tidak tambah risiko, fokus reduce/close posisi.

### Mode Inputs (Proxies) — TBD Sources
Portfolio boleh memakai proxy *market-level* untuk mengatur mode (bukan untuk memilih ticker):
- market breadth (naik/turun mayoritas)
- volatility proxy (range melebar)
- indeks acuan (IHSG/LQ45) vs level guard
- realized drawdown rolling (portfolio total)

### Mode Effects (Deterministik)
Saat mode berubah, efeknya harus konsisten:
- `RISK_ON`: gunakan risk budget default.
- `NEUTRAL`: turunkan `max_heat_total_pct` dan `max_heat_per_position_pct` (mis. x0.7).
- `RISK_OFF`: turunkan lebih keras (mis. x0.3) dan aktifkan `reduce-first` rule:
  - posisi profit kecil/flat disarankan keluar lebih cepat,
  - posisi yang melanggar heat wajib dikurangi.

Output tambahan (opsional):
- `portfolio_mode` di response summary.

## Concentration & Correlation Controls

Risk budget saja tidak cukup jika semua posisi terkonsentrasi di sektor/tema yang sama.

### Concentration Caps (Default Contract)
- `max_sector_exposure_pct` — batas market value per sektor.
- `max_theme_exposure_pct` — batas theme (mis. bank, batubara, nikel) (TBD mapping).
- `max_single_ticker_exposure_pct` — batas per ticker.

Jika melanggar:
- `decision = SELL_PARTIAL` untuk posisi yang paling mudah dikurangi (policy-defined),
- `reason_codes` memuat `PORTFOLIO_HEAT_LIMIT` atau `CONCENTRATION_LIMIT` (TBD enum jika ditambahkan).

### Correlation Proxy (Simple, Deterministic)
Tanpa menghitung korelasi statistik penuh, portfolio boleh memakai proxy:
- sektor yang sama dianggap correlated,
- sub-industri/komoditas yang sama dianggap correlated.

Rule sederhana:
- batasi jumlah posisi OPEN dalam sektor/theme yang sama (`max_positions_per_sector`).

## Post-Trade Compliance & Journaling (Process Edge)

Portfolio harus membantu user meningkatkan edge dengan mencatat apakah eksekusi sesuai rencana.

### Compliance Flags (Per Position / Per Exit)
- `plan_followed` ∈ {YES, NO}
- `violation_codes[]` (jika NO), contoh:
  - `LATE_EXIT`
  - `MOVED_STOP_WIDER`
  - `IGNORED_TIMEBOX`
  - `OVER_SIZED`
  - `CHASING_ENTRY`
  - `REVENGE_HOLD`

### Auto Journal (Minimal)
Setiap posisi CLOSED menghasilkan ringkasan:
- plan snapshot ringkas,
- entry/exit timeline,
- realized P/L net,
- exit_reason,
- compliance result,
- 1–2 bullet “what to improve” (rule-based).

## Performance Attribution & Review Workflow

Trader pro membedah kinerja, bukan cuma lihat total cuan.

### Attribution Dimensions (Wajib)
- per `policy_code`
- per `strategy_code`
- per `exit_reason_code`
- per `day_of_week_entry` dan `day_of_week_exit`
- per `hold_bucket` (intraday / 1–2d / 3–5d / >5d)

### Edge Metrics (Tambahan)
- `return_per_heat` = realized_pnl_net / average_heat_used (TBD)
- `hit_rate_by_reason`
- `timebox_rate` (berapa % trade keluar karena timebox)

### Review Cadence (Template)
- Weekly:
  - top 3 winners/losers + alasan exit
  - compliance violations (jumlah & jenis)
  - policy health: drawdown, win rate, expectancy
  - action items minggu depan (per policy)

> TODO: definisikan output endpoint / report untuk review ini.


## UI Contract (Portfolio Screen)

- Default view: **Active Positions (ALL policies)**.
- Setiap baris posisi wajib menampilkan: `policy_code`, `strategy_code`, `opened_at`, `qty`, `avg`, `mark`, `unrealized`, `plan TP/SL`, `timebox`.
- Filter cepat: policy, status, ticker, range tanggal open.
- Detail position: plan snapshot + timeline events + ringkasan aturan policy.

## API/DTO Contract (Ringkas)

> Mengikuti aturan DTO & SRP internal TradeAxis (lihat docs terkait).

Endpoints minimal:
- `GET /portfolio/positions?status=OPEN`
- `GET /portfolio/positions/{id}`
- `POST /portfolio/trades/ingest` (idempotent)
- `POST /portfolio/positions/{id}/close` (manual exit jika diizinkan policy)
- `GET /portfolio/performance?range=...&group_by=policy`

> TODO: detail format response success/error di doc DTO.
