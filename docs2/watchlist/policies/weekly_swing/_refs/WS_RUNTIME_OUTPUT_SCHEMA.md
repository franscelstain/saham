# WS Runtime Output Schema (LOCKED)

Dokumen ini mengunci schema output **PLAN** dan **CONFIRM** untuk UI dan audit.

> Prinsip utama: **CONFIRM tidak boleh mengubah PLAN**. Output CONFIRM harus terpisah.

---

## 1) PLAN Output (LOCKED)

### JSON Schema (contoh canonical)
```json
{
  "meta": {
    "policy": "WEEKLY_SWING",
    "asof_eod_date": "YYYY-MM-DD",
    "trade_date": "YYYY-MM-DD",
    "paramset_id": "string-or-int",
    "paramset_hash": "sha256-hex",
    "plan_hash": "sha256-hex",
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

### Field semantics (LOCKED)
- `meta.policy`: selalu `"WEEKLY_SWING"`.
- `meta.asof_eod_date`: tanggal data EOD yang menjadi basis PLAN.
- `meta.trade_date`: tanggal eksekusi manual (umumnya `asof_eod_date + 1 trading day`).
- `meta.paramset_id`: identitas paramset yang dipakai (id/version).
- `meta.paramset_hash`: hash paramset canonical (sha256 hex).
- `meta.plan_hash`: hash PLAN canonical (sha256 hex) sesuai kontrak hash policy.
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
    - `NO_TRADE`
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

## 2) CONFIRM Output (LOCKED)

### JSON Schema (contoh canonical)
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

### Field semantics (LOCKED)
- `meta.policy`: selalu `"WEEKLY_SWING"`.
- `meta.checked_at`: waktu sistem menghasilkan confirm result.
- `meta.snapshot_ts`: timestamp snapshot input.
- `meta.snapshot_age_sec`: `checked_at - snapshot_ts` (detik).
- `items[]`:
  - `ticker`: kode ticker yang ada di PLAN.
  - `label`: salah satu:
    - `CONFIRMED`
    - `NEUTRAL`
    - `CAUTION`
    - `DELAY`
  - `reasons[]`: alasan deterministic (code, severity, message, payload).

---

## 3) Canonical rules (LOCKED)

### 3.1 General
- Output wajib valid JSON.
- Tidak ada field tambahan di luar schema ini kecuali disebut eksplisit sebagai **optional** di dokumen policy WS.
- Angka wajib berupa number JSON, bukan string angka.
- Timestamp wajib ISO-8601 (`YYYY-MM-DDTHH:MM:SSZ`) atau format canonical yang dikunci di dokumen eksekusi.

### 3.2 PLAN rules
- `items[].ticker` wajib unik.
- `rank` mulai dari 1 dan kontigu (1..N).
- `score_total` harus dalam range 0..1 (setelah clamp).
- `scores.*` harus dalam range 0..1.
- `levels.*` selalu numeric.
- Jika level tidak valid (mis. `stop_price >= entry_ref` atau `tp1_price <= entry_ref`), item tidak boleh berada di `TOP_PICKS`; minimal `WATCH_ONLY`/`AVOID` dan wajib ada reason yang menjelaskan.
- `reasons[]` wajib deterministic:
  - urutan reasons harus stabil (canonical order) dan konsisten dengan kontrak hash.
  - `payload` hanya memuat field yang dibutuhkan untuk audit (tidak random, tidak berubah-ubah).

### 3.3 CONFIRM rules
- CONFIRM tidak boleh mengubah PLAN:
  - Tidak boleh mengubah `plan_hash`.
  - Tidak boleh mengubah `items[].rank`, `group_semantic`, `score_total`, `levels.*`, `reasons[]` pada PLAN.
- `label` ditentukan oleh severity tertinggi di reasons:
  - ada `BLOCK` → `DELAY`
  - else ada `WARN` → `CAUTION`
  - else hanya `INFO` → `NEUTRAL`
  - else (tidak ada reasons) → `CONFIRMED`
- Jika snapshot stale melebihi `confirm_overlay.snapshot_max_age_sec`, label harus `DELAY`.

### 3.4 Summary rules
- `summary.*_count` harus konsisten dengan agregasi `items[]` pada output masing-masing.
- Jika `summary.no_trade=true`, maka:
  - `summary.top_picks_count = 0`
  - `summary.secondary_count = 0`
  - `summary.no_trade_reason` wajib terisi.

---

## 4) Backward/Forward compatibility (LOCKED)
- Jika schema berubah, harus bump **policy schema version** dan dokumentasi harus menyatakan breaking change.
- Tidak boleh “diam-diam” menambah field yang mengubah interpretasi UI/audit.

