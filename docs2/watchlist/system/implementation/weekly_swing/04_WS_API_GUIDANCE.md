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
7. nama field minimum pada dokumen ini adalah **canonical field set** untuk build layer B
8. engineer **tidak boleh** mengganti nama field canonical dengan alias internal tanpa adapter yang eksplisit di boundary API

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

## Canonical Response Contract

Bagian ini menutup ruang tafsir build layer B.  
Nama field di bawah adalah **canonical API field set**. Bila service internal memakai nama berbeda, adapter boundary wajib mengubahnya menjadi nama canonical sebelum response keluar dari endpoint watchlist.

### PLAN Response Minimum — Canonical
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
- `no_trade`
- `reason_codes`

Aturan tambahan:
- `no_trade` wajib selalu ada dan bertipe boolean
- `reason_codes` wajib selalu ada dan bertipe array; bila tidak ada alasan, isi `[]`
- `groups` dan `candidates` tidak boleh diganti namanya pada boundary API

### RECOMMENDATION Response Minimum — Canonical
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
- `is_empty`
- `empty_reason_codes`

Aturan tambahan:
- `source_plan_reference` adalah field canonical; jangan diganti menjadi alias lain pada boundary API
- `is_empty` wajib selalu ada dan bertipe boolean
- `empty_reason_codes` wajib selalu ada dan bertipe array; bila tidak kosong, isi `[]`
- `selected_items` wajib selalu ada; recommendation kosong ditulis sebagai `[]`, bukan field hilang

### CONFIRM Response Minimum — Canonical
Wajib punya minimal:
- `strategy_code`
- `policy_code`
- `policy_version`
- `schema_version`
- `trade_date`
- `param_set_id`
- `source_artifact_type = CONFIRM`
- `source_plan_reference`
- `ticker`
- `confirm_eligibility_basis`
- `confirm_status`
- `reason_codes`
- `snapshot_reference`

Aturan tambahan:
- `confirm_status` adalah field canonical; jangan diganti dengan alias lain pada boundary API
- `snapshot_reference` adalah field canonical untuk menunjuk basis input confirm yang sah
- `schema_version` dan `param_set_id` wajib ikut pada response confirm agar runtime keys artifact-level tetap sinkron lintas artifact Weekly Swing
- `reason_codes` wajib selalu ada dan bertipe array

### Composite Response Minimum — Canonical
Wajib jelas memisahkan:
- section `plan`
- section `recommendation`
- section `confirm`

Aturan tambahan:
- nama section harus persis `plan`, `recommendation`, `confirm`
- composite view tidak boleh membuat seolah-olah confirm telah mengubah recommendation
- setiap section wajib mempertahankan source semantics artifact asalnya

## Confirm Request Minimum — Canonical

`POST /watchlist/weekly-swing/confirm` minimal menerima field berikut:
- `strategy_code`
- `trade_date`
- `ticker`
- `source_plan_reference`
- `snapshot_reference`

Aturan tambahan:
- `source_plan_reference` adalah field canonical request; jangan diganti dengan alias lain pada boundary API
- `snapshot_reference` dapat menunjuk input manual atau snapshot sah sesuai baseline confirm
- unknown top-level field harus ditolak
- request tanpa salah satu field minimum di atas harus ditolak

## Optional Fields Policy

1. Field di luar canonical minimum hanya boleh ditambahkan bila **tidak** mengubah source semantics.
2. Field tambahan tidak boleh menggantikan atau menamai ulang field canonical.
3. Field tambahan execution-only, broker-only, order-only, holdings-only, atau PnL-only tetap dilarang.
4. Bila response ingin membawa metadata tambahan, taruh sebagai metadata non-authority dan dokumentasikan eksplisit di app layer.

## Forbidden API Semantics (LOCKED)

1. confirm endpoint **must not** mengembalikan recommendation ranking yang sudah diubah karena confirm
2. recommendation endpoint **must not** memasukkan ticker yang tidak berasal dari candidate PLAN
3. confirm endpoint **must not** memakai recommendation membership sebagai syarat eligibility
4. endpoint watchlist **must not** membawa field broker/order placement
5. endpoint watchlist **must not** menjadi endpoint portfolio exposure/holding/PnL
6. composite endpoint **must not** mencampur source semantics sehingga confirm terlihat sebagai source recommendation
7. endpoint watchlist **must not** menghilangkan field canonical minimum hanya karena nilainya kosong; gunakan boolean/array kosong yang sah

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
6. response API harus mempertahankan field canonical minimum walau hasilnya kosong

## Example Cases That Must Exist In Implementation

### Valid
- confirm request valid untuk candidate non-recommended
- recommendation response valid dengan `selected_items = []`, `is_empty = true`, `empty_reason_codes` berisi alasan yang sah

### Invalid
- confirm request invalid karena ticker bukan candidate PLAN
- confirm request invalid karena unknown top-level field
- recommendation response invalid bila `selected_items` hilang
- confirm response invalid bila `confirm_status` diganti nama field lain pada boundary API

## Manual Input Support

Aplikasi harus mendukung input manual untuk confirm sepanjang payload sesuai kontrak dan tetap tunduk pada rule confirm eligibility dari PLAN.

## Final Rule

API watchlist Weekly Swing yang sah harus:
- memisahkan PLAN / RECOMMENDATION / CONFIRM
- menjaga source semantics tiap artifact
- memakai canonical field minimum pada boundary API
- menolak pelebaran ke execution/portfolio
- menolak confirm atas ticker yang bukan candidate PLAN
- tidak pernah memperlakukan CONFIRM sebagai source pembentuk RECOMMENDATION
