# 11 — Weekly Swing Intraday Snapshot Tables

## Purpose

Dokumen ini adalah owner normatif untuk semantics persistence snapshot / intraday yang dimiliki watchlist dan dipakai oleh CONFIRM Weekly Swing.

## Scope

Dokumen ini mengunci:

- tabel snapshot watchlist-owned yang dipakai CONFIRM,
- field normatif minimum pada tabel tersebut,
- lifecycle dan append-only behavior,
- boundary antara representasi snapshot watchlist dan kontrak upstream `market_data`,
- serta relationship terhadap confirm overlay behavior.

Dokumen ini tidak menjadi owner kontrak publication, readiness, atau validity upstream.

## Upstream Boundary

Asal data upstream yang digunakan untuk membentuk snapshot diasumsikan telah memenuhi kontrak authoritative pada domain `market_data`.

Dokumen ini tidak mendefinisikan ulang:

- publication semantics upstream,
- readiness semantics upstream,
- validity semantics upstream,
- atau definisi data upstream yang dikonsumsi.

Dokumen ini hanya mengatur representasi snapshot yang dimiliki watchlist setelah data tersebut diterima atau dicatat untuk kebutuhan CONFIRM.

## Table A — `watchlist_confirm_snapshots`

**Purpose**  
Header snapshot intraday untuk satu pengambilan snapshot CONFIRM pada `trade_date` tertentu.

**Normative Fields**  
- `snapshot_id` — identitas header snapshot.
- `policy_code` — canonical internal policy code strategy (`WS` untuk Weekly Swing).
- `trade_date` — tanggal trading yang sedang dievaluasi.
- `captured_at` — waktu data diambil dari sumber.
- `inserted_at` — waktu snapshot masuk ke persistence watchlist.
- `source` — asal input snapshot pada sisi watchlist (mis. `manual`).
- `note` — catatan operasional opsional.
- `snapshot_hash` — fingerprint opsional untuk audit snapshot header/payload.
- `created_at` — timestamp pencatatan row.

**Field Semantics**  
- `captured_at` adalah waktu pengambilan menurut sumber; `inserted_at` adalah waktu pencatatan oleh watchlist.
- `effective_captured_at` bukan kolom persistence. Nilai ini adalah turunan runtime: `LEAST(captured_at, inserted_at)` untuk evaluasi freshness.
- `snapshot_hash` boleh dipakai untuk audit, tetapi tidak menggantikan `plan_hash` dan tidak mengubah kontrak immutability PLAN.

**Lifecycle (LOCKED)**  
- Tabel ini append-only; `UPDATE` dan `DELETE` dilarang.
- Satu header snapshot dapat memiliki banyak item pada `watchlist_confirm_snapshot_items`.

**Relationship to Confirm Overlay**  
CONFIRM membaca tabel ini untuk menentukan ketersediaan snapshot, freshness boundary, dan metadata snapshot pada output CONFIRM.

**Related Implementation Artifacts**  
- `../../../db/02_DB_SCHEMA_MARIADB.md`
- `../../../db/03_DB_INDEXES_AND_CONSTRAINTS.md`
- `../../../db/05_DB_DDL_MARIADB.sql`

## Table B — `watchlist_confirm_snapshot_items`

**Purpose**  
Detail per ticker untuk snapshot intraday yang dipakai oleh CONFIRM.

**Normative Fields**  
- `snapshot_item_id` — identitas item snapshot.
- `snapshot_id` — foreign key ke header snapshot.
- `ticker_code` — identitas ticker canonical pada snapshot.
- `ticker_id` — identitas internal ticker bila tersedia.
- `last_price` — harga terakhir pada snapshot.
- `chg_pct` — perubahan persen pada snapshot.
- `volume_shares` — volume saham pada snapshot.
- `turnover_idr` — nilai transaksi pada snapshot.
- `item_hash` — fingerprint item untuk audit bila implementasi memakainya.
- `created_at` — timestamp pencatatan row.

**Field Semantics**  
- `ticker_code` adalah identitas akhir yang dipakai untuk binding ke kandidat PLAN pada level runtime/output.
- `ticker_id` boleh null dan tidak menggantikan kebutuhan `ticker_code` sebagai identitas audit yang dapat dibaca lintas artifacts.
- `last_price`, `chg_pct`, `volume_shares`, dan `turnover_idr` adalah input snapshot yang boleh dipakai oleh overlay CONFIRM.
- Order-book ladder bukan bagian dari persistence contract tabel ini.

**Lifecycle (LOCKED)**  
- Tabel ini append-only; `UPDATE` dan `DELETE` dilarang.
- `(snapshot_id, ticker_code)` wajib unik untuk mencegah satu ticker muncul ganda dalam snapshot yang sama.

**Relationship to Confirm Overlay**  
CONFIRM membaca item snapshot untuk binding kandidat, evaluasi freshness yang bergantung pada header snapshot, dan rule overlay yang memakai `last_price` serta aggregate metrics terkait.

**Related Implementation Artifacts**  
- `../../../db/02_DB_SCHEMA_MARIADB.md`
- `../../../db/03_DB_INDEXES_AND_CONSTRAINTS.md`
- `../../../db/05_DB_DDL_MARIADB.sql`

## Ownership Rule

Semantics tabel snapshot watchlist-owned ditetapkan di dokumen ini dan dibaca bersama `10_WS_CONFIRM_OVERLAY.md` serta `03_WS_DATA_MODEL_MARIADB.md`. SQL artifacts hanya merealisasikan shape yang dikunci di sini dan tidak menggantikan dokumen ini sebagai owner semantics.

## Final Rule

Jika sebuah field, table shape, atau usage behavior pada snapshot hanya tampak di SQL artifact, example, atau fixture tanpa owner normatif yang jelas di dokumen ini atau dokumen owner Weekly Swing terkait, maka behavior tersebut belum dianggap bagian resmi dari kontrak Weekly Swing.
