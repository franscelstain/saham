# 04 — WS API Guidance

## Purpose

Dokumen ini memberi panduan API/read-model untuk aplikasi watchlist Weekly Swing tanpa masuk ke domain execution atau portfolio.

Dokumen ini adalah **implementation translation only**.  
Semua shape API wajib tunduk pada baseline freeze dan owner docs Weekly Swing.

## Scope Lock

- watchlist only
- weekly_swing only
- bukan portfolio
- bukan execution
- bukan market-data internals

## API Design Principles

1. endpoint watchlist bersifat read/suggestion domain
2. endpoint watchlist tidak boleh membuat transaksi beli/jual
3. endpoint watchlist tidak boleh expose field execution-only
4. endpoint watchlist tidak boleh expose holdings/PnL/portfolio semantics sebagai authority domain
5. response `RECOMMENDATION` harus berasal dari PLAN
6. response `CONFIRM` tidak boleh memutasi `RECOMMENDATION`

## Recommended Endpoints

### A. PLAN Read
- `GET /watchlist/weekly-swing/plan?trade_date=YYYY-MM-DD`

Tujuan:
- mengembalikan artifact PLAN atau summary sah dari PLAN

### B. RECOMMENDATION Read
- `GET /watchlist/weekly-swing/recommendation?trade_date=YYYY-MM-DD`

Tujuan:
- mengembalikan artifact RECOMMENDATION yang dibentuk dari PLAN

### C. CONFIRM Submit
- `POST /watchlist/weekly-swing/confirm`

Tujuan:
- menerima input confirm yang sah terhadap candidate PLAN

### D. CONFIRM Read
- `GET /watchlist/weekly-swing/confirm?trade_date=YYYY-MM-DD&ticker=...`

Tujuan:
- membaca output confirm untuk ticker tertentu

### E. Composite Read
- `GET /watchlist/weekly-swing/view?trade_date=YYYY-MM-DD`

Tujuan:
- mengembalikan gabungan state watchlist untuk consumer tanpa mencampur source semantics

## Minimum Response Contract

### PLAN Response Minimum
Wajib punya minimal:
- `strategy_code`
- `policy_code`
- `policy_version`
- `schema_version`
- `trade_date`
- `param_set_id`
- `source_artifact_type = PLAN`
- `groups`
- `candidates`
- `no_trade` atau ekuivalen bila relevan
- `reason_codes` bila relevan

### RECOMMENDATION Response Minimum
Wajib punya minimal:
- `strategy_code`
- `policy_code`
- `policy_version`
- `schema_version`
- `trade_date`
- `param_set_id`
- `source_artifact_type = RECOMMENDATION`
- `source_plan_reference`
- `capital_mode`
- `selected_items`
- `reason_codes`
- `is_empty` atau indikator empty-state yang ekuivalen
- `empty_reason_codes` bila kosong

### CONFIRM Response Minimum
Wajib punya minimal:
- `strategy_code`
- `policy_code`
- `policy_version`
- `trade_date`
- `source_artifact_type = CONFIRM`
- `source_plan_reference`
- `ticker`
- `confirm_eligibility_basis`
- `confirm_status` atau hasil ekuivalen
- `reason_codes`
- `snapshot_reference` atau timestamp input confirm yang sah

### Composite Response Minimum
Wajib jelas memisahkan:
- section `plan`
- section `recommendation`
- section `confirm`

Composite view tidak boleh membuat seolah-olah confirm telah mengubah recommendation.

## Confirm Request Minimum

`POST /watchlist/weekly-swing/confirm` minimal menerima field yang relevan untuk:
- `strategy_code`
- `trade_date`
- `ticker`
- `source_plan_reference` atau kunci ekuivalen
- manual/snapshot input yang sah sesuai baseline confirm

Unknown top-level field harus ditolak bila policy contract menyatakannya demikian.

## Forbidden API Semantics (LOCKED)

1. confirm endpoint **must not** mengembalikan recommendation ranking yang sudah diubah karena confirm
2. recommendation endpoint **must not** memasukkan ticker yang tidak berasal dari candidate PLAN
3. confirm endpoint **must not** memakai recommendation membership sebagai syarat eligibility
4. endpoint watchlist **must not** membawa field broker/order placement
5. endpoint watchlist **must not** menjadi endpoint portfolio exposure/holding/PnL
6. composite endpoint **must not** mencampur source semantics sehingga confirm terlihat sebagai source recommendation

## Error Contract Minimum

### 400 — malformed / contract shape error
Contoh:
- field wajib hilang
- format field salah
- unknown top-level field
- policy_code tidak sesuai

### 404 — source artifact tidak ditemukan
Contoh:
- source PLAN tidak ditemukan
- recommendation artifact tidak ditemukan untuk trade_date tertentu

### 409 — immutable artifact conflict / publish conflict
Contoh:
- mencoba memproses terhadap source reference yang bentrok secara state/immutability

### 422 — request well-formed but semantically invalid
Contoh:
- ticker bukan candidate PLAN
- capital mode tidak didukung
- snapshot/manual input tidak sah terhadap contract confirm

## Mandatory API Behavior

1. recommendation kosong adalah hasil valid
2. confirm tetap boleh berjalan untuk candidate PLAN walau recommendation kosong
3. non-recommended candidate tetap dapat di-confirm bila masih valid sebagai candidate PLAN
4. response confirm harus gagal bila ticker bukan candidate PLAN
5. response recommendation tidak boleh bergantung pada confirm

## Example Cases That Must Exist In Implementation

### Valid
- confirm request valid untuk candidate non-recommended

### Invalid
- confirm request invalid karena ticker bukan candidate PLAN
- confirm request invalid karena unknown top-level field
- recommendation response empty walau prioritized groups pada PLAN tidak kosong tetap harus dianggap valid

## Manual Input Support

Aplikasi harus mendukung input manual untuk confirm sepanjang payload sesuai kontrak dan tetap tunduk pada rule confirm eligibility dari PLAN.

## Final Rule

API watchlist Weekly Swing yang sah harus:
- memisahkan PLAN / RECOMMENDATION / CONFIRM
- menjaga source semantics tiap artifact
- menolak pelebaran ke execution/portfolio
- menolak confirm atas ticker yang bukan candidate PLAN
- tidak pernah memperlakukan CONFIRM sebagai source pembentuk RECOMMENDATION
