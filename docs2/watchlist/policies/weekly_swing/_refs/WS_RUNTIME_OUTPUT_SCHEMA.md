# WS Runtime Output Schema — Reference

## Purpose
Merangkum bentuk payload runtime Weekly Swing untuk kebutuhan API/UI/audit.

> **Status:** reference-only. Dokumen ini berada di `_refs/` dan tidak menjadi sumber aturan utama. Aturan normatif tetap mengikuti dokumen bernomor pada folder WS utama.

## Scope
Dokumen ini merangkum bentuk payload output PLAN dan CONFIRM yang dipakai sebagai acuan pembacaan response.
Ini **bukan** schema tabel/database maupun bentuk record persistence.

## Inputs
- Engineer serializer/API/UI, reviewer, dan auditor.

## Outputs
- Peta schema referensi output runtime Weekly Swing.

Field `meta` dan `items[]` adalah **view model** yang boleh berasal dari agregasi beberapa tabel/record (mis. plan_run/plan_item + metrics + confirm overlay).
Prinsip utamanya tetap mengikuti kontrak WS utama: **CONFIRM tidak boleh mengubah PLAN** dan output CONFIRM harus terpisah.

**Contoh pasangan referensi:** lihat `../examples/WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json` (PLAN hash sebelum/sesudah harus identik menurut kontrak utama).

---

## 1) PLAN Output (reference shape)

### JSON Schema (contoh shape canonical)
```json
{
  "meta": {
    "policy": "WEEKLY_SWING",
    "asof_eod_date": "YYYY-MM-DD",
    "trade_date": "YYYY-MM-DD",
    "paramset_id": "string-or-int",
    "paramset_hash": "sha256-hex",
    "plan_hash": "sha256-hex",
    "data_batch_hash": "sha256-hex",
    "fail_code": null,
    "fail_reason_codes": [],
    "generated_at": "YYYY-MM-DDTHH:MM:SSZ",
    "source": {
      "vendor": "string",
      "dataset_id": "string",
      "coverage_ratio": 0.0
    }
  },
  "items": [
    {
      "ticker": "XXXX",
      "rank": 1,
      "group_semantic": "TOP_PICKS",
      "score_total": 0.0,
      "scores": {
        "score_momentum": 0.0,
        "score_breakout": 0.0,
        "score_volume": 0.0,
        "score_risk": 0.0
      },
      "levels": {
        "entry_ref": 0.0,
        "entry_band_low": 0.0,
        "entry_band_high": 0.0,
        "stop_price": 0.0,
        "tp1_price": 0.0
      },
      "flags": {
        "eligible": true,
        "hidden": false
      },
      "reasons": [
        {
          "code": "string",
          "severity": "INFO",
          "message": "string",
          "payload": {}
        }
      ]
    }
  ],
  "summary": {
    "eligible_count": 0,
    "top_picks_count": 0,
    "secondary_count": 0,
    "watch_only_count": 0,
    "avoid_count": 0,
    "no_trade": false,
    "no_trade_reason": {
      "code": "string",
      "message": "string"
    }
  }
}
```
**Contoh file referensi:** lihat `../examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json` untuk payload PLAN yang sesuai dengan bentuk referensi ini (termasuk `meta.plan_hash`).

### Field semantics (reference summary)
- `meta.policy`: selalu `"WEEKLY_SWING"`.
- `meta.asof_eod_date`: tanggal data EOD yang menjadi basis PLAN.
- `meta.trade_date`: tanggal eksekusi manual (umumnya `asof_eod_date + 1 trading day`).
- `meta.paramset_id`: identitas paramset yang dipakai (id/version).
- `meta.paramset_hash`: hash paramset canonical (sha256 hex).
- `meta.plan_hash`: hash PLAN canonical (sha256 hex) sesuai kontrak hash policy.
- `meta.data_batch_hash`: hash batch data input (sha256 hex) yang dipakai membentuk PLAN (untuk audit/traceability).
- `meta.generated_at`: timestamp saat PLAN dibuat.
- `meta.source.coverage_ratio`: rasio coverage data yang dipakai untuk gate readiness (0..1).

- `items[]`:
  - `ticker`: kode ticker unik.
  - `rank`: peringkat global setelah sorting canonical.
  - `group_semantic`: salah satu:
    - `TOP_PICKS`
    - `SECONDARY`
    - `WATCH_ONLY`
    - `AVOID`
  - `score_total`: skor akhir 0..1 (setelah clamp).
  - `scores.*`: sub-skor yang dipakai membentuk `score_total` (0..1).
  - `levels.*`: level harga deterministik:
    - `entry_ref`: acuan entry untuk perhitungan level (mis. entry_band_mid).
    - `entry_band_low`, `entry_band_high`: rentang entry.
    - `stop_price`: level stop.
    - `tp1_price`: target profit 1.
  - `flags.eligible`: true bila lulus guard & readiness.
  - `flags.hidden`: true bila item tidak ditampilkan (mis. hide cap), namun tetap bisa ada di audit internal.
  - `reasons[]`: daftar alasan deterministic (code, severity, message singkat, payload).

---

## 2) CONFIRM Output (reference shape)

### JSON Schema (contoh shape canonical)
```json
{
  "meta": {
    "policy": "WEEKLY_SWING",
    "checked_at": "YYYY-MM-DDTHH:MM:SSZ",
    "snapshot_ts": "YYYY-MM-DDTHH:MM:SSZ",
    "snapshot_age_sec": 0,
    "source": {
      "vendor": "string",
      "snapshot_id": "string"
    }
  },
  "items": [
    {
      "ticker": "XXXX",
      "label": "CONFIRMED",
      "reasons": [
        {
          "code": "string",
          "severity": "INFO",
          "message": "string",
          "payload": {}
        }
      ]
    }
  ],
  "summary": {
    "confirmed_count": 0,
    "neutral_count": 0,
    "caution_count": 0,
    "delay_count": 0
  }
}
```

**Contoh file referensi:** lihat `../examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json` untuk payload CONFIRM yang sesuai dengan bentuk referensi ini.

### Field semantics (reference summary)
- `meta.policy`: selalu `"WEEKLY_SWING"`.
- `meta.checked_at`: waktu sistem menghasilkan confirm result.
- `meta.snapshot_ts`: timestamp snapshot input.
- `meta.snapshot_age_sec`: `checked_at - snapshot_ts` (detik).
- Jika `meta.snapshot_age_sec > snapshot_max_age_sec (LOCKED: 900)`, maka kondisi stale bersifat snapshot-level dan seluruh `items[]` yang dikembalikan wajib berlabel `DELAY` dengan reason `WS_STALE`.
- `items[]`:
  - `ticker`: kode ticker yang ada di PLAN.
  - `label`: salah satu:
    - `CONFIRMED`
    - `NEUTRAL`
    - `CAUTION`
    - `DELAY`
  - `reasons[]`: alasan deterministic (code, severity, message, payload).
  - Untuk CONFIRM, field output item yang sah hanya `ticker`, `label`, dan `reasons[]`; alias seperti `confirm_label`, `confirm_reasons`, atau `reason_codes` tidak boleh dipakai di output final.
- `ignored_fields` (optional): list nama field non-contract yang ditemukan pada payload input dan **diabaikan** (PASS + ignore).

---

## 3) Canonical rules (reference summary)

### 3.1 General
- Output harus valid JSON sesuai kontrak WS utama.
- Field tambahan di luar bentuk ini hanya boleh muncul bila diizinkan oleh dokumen policy WS utama.
- Angka harus berupa number JSON, bukan string angka.
- Timestamp mengikuti format yang ditetapkan oleh dokumen eksekusi WS utama.

### 3.2 PLAN rules

#### 3.2.1 Precision & rounding (reference summary)
- `score_total` dan semua `scores.*` **dibulatkan 4 decimal** pada output UI/API.
- Semua harga pada `levels.*` **dibulatkan 4 decimal**.
- `meta.coverage_ratio` dibulatkan 4 decimal.
- Dilarang mengubah precision ini per lingkungan (dev/prod).

#### 3.2.2 Canonicalization for `meta.plan_hash` (reference summary)
`meta.plan_hash` pada praktiknya mengikuti kontrak hash WS utama; ringkasan bentuknya sebagai berikut:

1) Bentuk data yang di-hash adalah array `items[]` setelah **ranking final**.
2) Item yang diikutkan dalam hash:
   - semua item yang muncul di `items[]` (termasuk `WATCH_ONLY` / `AVOID` bila tetap ditampilkan).
   - Jika `meta.fail_code = "NO_TRADE"`, maka `items[]` **wajib** `[]` (lihat kontrak NO_TRADE). Dengan demikian canonical payload untuk `plan_hash` adalah payload kosong.
3) Untuk setiap item, field yang diikutkan **hanya**:
   - `ticker`
   - `rank`
   - `group_semantic`
   - `score_total`
   - `levels.entry_ref`, `levels.entry_band_low`, `levels.entry_band_high`, `levels.stop_price`, `levels.tp1_price`
   - `reasons[]` → untuk setiap reason hanya: `code`, `severity` (tanpa `message` dan tanpa `payload`)
   - `flags.eligible`, `flags.hidden`
4) Urutan item **wajib**: `rank ASC, ticker ASC`.
5) Urutan `reasons[]` per item **wajib**: severity desc (`BLOCK`> `WARN`> `INFO`) lalu `code ASC`.
6) Angka wajib memakai string format fixed:
   - `score_total` 4 decimal
   - `levels.*` 4 decimal
7) Representasi canonical payload (LOCKED):
   - payload adalah string JSON hasil serialisasi dari array item canonical (hasil langkah 1–6) dengan aturan:
     - key order deterministik: JSON object keys diurutkan alfabetis (`sort_keys=true`).
     - tanpa whitespace: gunakan separators `(',', ':')`.
     - encoding UTF-8.
   - Dengan kata lain, implementasi referensi adalah setara dengan `json.dumps(items, separators=(',',':'), sort_keys=True, ensure_ascii=False)`. 
8) Hash dihitung sebagai `SHA256(utf8_bytes(payload))` dan hasilnya ditulis sebagai hex lowercase.

Catatan (LOCKED):
- Jika implementasi tidak memakai JSON, hasil hash dianggap **INVALID** kecuali canonical payload string-nya persis sama.

## 4. Reason Model by Layer (LOCKED)

Agar kontrak PLAN ↔ CONFIRM konsisten, model reason dibedakan tegas per layer:

### 1) Persistence run-level
- Gunakan `fail_code` tunggal.
- Berlaku untuk `watchlist_plan_runs` dan `watchlist_confirm_checks`.
- `fail_code` adalah source of truth run-level untuk status seperti `FAILED` atau `NO_TRADE`.

### 2) Persistence item-level
- Gunakan `reason_codes_json`.
- Berlaku untuk `watchlist_plan_items` dan `watchlist_confirm_items`.
- Isi berupa daftar code dictionary yang tersimpan untuk audit.

### 3) Output API / UI
- Gunakan `reasons[]`.
- Setiap item `reasons[]` minimal memuat:
  - `code`
  - `message`
- Dianjurkan juga memuat:
  - `severity`
  - `payload`

### 4) View-model turunan
- Field seperti `summary.no_trade_reason`, jika dipakai, hanyalah view-model turunan dari `fail_code` + dictionary.
- Field tersebut bukan source of truth persistence terpisah.

### 5) Larangan
- `held_reason` tidak dipakai dalam kontrak final.
- `reason_codes` tidak dipakai sebagai output final API / UI.

## 5) Backward/Forward compatibility (LOCKED)
- Jika schema berubah, harus bump **policy schema version** dan dokumentasi harus menyatakan breaking change.
- Tidak boleh “diam-diam” menambah field yang mengubah interpretasi UI/audit.
## LOCKED — NO_TRADE Gate Output

NO_TRADE adalah kondisi ketika **tidak ada** item yang berhasil lolos hingga tahap ranking final (mis. seluruh candidate gagal guard, tersaring selection, atau ter-hide semuanya).

### 1) Output API / UI (LOCKED)
Jika NO_TRADE terjadi, output PLAN API/UI **wajib** memenuhi:
- `meta.fail_code = "NO_TRADE"`
- `meta.fail_reason_codes` **wajib** berisi **tepat satu** reason NO_TRADE:
  - `["WS_NO_TRADE_MIN_ELIGIBLE"]` jika `eligible_total < ws.filters.min_eligible_count`
  - `["WS_NO_TRADE_ALL_FILTERED"]` jika `eligible_total >= ws.filters.min_eligible_count` namun tidak ada item yang lolos untuk ditampilkan
- `items` **wajib** `[]` (kosong). Tidak boleh mengirim item `HIDE`/`WATCH_ONLY`/`AVOID` sebagai “pengganti”.
- `meta.plan_hash` dihitung dari canonical payload kosong `[]` sesuai aturan `3.2.2 Canonicalization for meta.plan_hash`.

### 2) Canonical payload kosong untuk `meta.plan_hash` (LOCKED)
Karena `items = []`, canonical payload untuk hash adalah string JSON persis:
- payload: `[]` (tanpa whitespace)

Implementasi referensi:
- `payload = "[]"`
- `meta.plan_hash = SHA256(utf8_bytes(payload))` (hex lowercase)

Nilai referensi (LOCKED) untuk payload `[]`:
- `SHA256("[]") = 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`

### 3) Konsistensi dengan rule PLAN hashing (LOCKED)
Aturan ini **mengikat** kalimat di `3.2.2`:
- “Jika meta.fail_code = NO_TRADE maka items wajib []”
- sehingga tidak ada mode/variant lain untuk NO_TRADE.
