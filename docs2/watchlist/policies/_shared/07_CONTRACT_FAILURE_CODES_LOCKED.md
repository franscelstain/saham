# 07 — Contract Failure Codes (LOCKED)

## Purpose
`CF_*` adalah kode kegagalan deterministik untuk layer contract-test, validator, dan anti-drift. Kode ini bukan reason code trading (`WS_*`) dan bukan fail code runtime (`PLAN_ABORT_*` / `CONFIRM_ABORT_*`).

## Scope
Dokumen ini berlaku lintas strategy untuk kelas kegagalan kontraktual yang boleh dipakai oleh validator, contract tests, dan guard audit.

Pemetaan `CF_*` ke test case strategy-specific tetap dimiliki oleh checklist contract test strategy yang relevan. Dokumen shared ini tidak menjadi owner penomoran test case lokal.

## Rules (LOCKED)
- `CF_*` hanya dipakai di test layer / validator layer.
- Pesan error bebas dilarang; FAIL harus mengeluarkan `CF_*` yang tepat.
- Satu failure menghasilkan satu `CF_*` utama, dengan context tambahan bila perlu.
- Strategy-specific checklists boleh memetakan satu `CF_*` ke beberapa test case lokal, tetapi tidak boleh mengubah semantik code-nya.

## Code Dictionary (LOCKED)

- `CF_PARAMSET_MISSING_KEY`
  - Makna: key wajib berdasarkan contract owner / registry tidak ada pada payload.

- `CF_PARAMSET_UNKNOWN_KEY`
  - Makna: payload mengandung key yang tidak terdaftar pada kontrak aktif.

- `CF_PARAMSET_TYPE_DRIFT`
  - Makna: tipe nilai tidak sesuai kontrak aktif.

- `CF_PARAMSET_AUDIT_SCHEMA_INVALID`
  - Makna: node audit leaf tidak memiliki field wajib `{ value, origin, status, bt_target, rationale, change_triggers }` atau formatnya salah.

- `CF_PARAMSET_ENUM_INVALID`
  - Makna: nilai enum tidak termasuk allowed set yang aktif.

- `CF_HASH_CONTRACT_VIOLATION`
  - Makna: aturan hash contract yang dikunci dilanggar.

- `CF_EVAL_GATE_INVALID`
  - Makna: aturan gating evaluasi dilanggar (tipe / limit / range / constraint).

- `CF_SCHEMA_DRIFT`
  - Makna: payload atau shape runtime menampilkan field / struktur non-kontraktual yang harus ditolak sebagai drift.

- `CF_UNIVERSE_EQUIVALENCE_MISMATCH`
  - Makna: outcome equivalence lintas sistem tidak match pada sample yang disepakati.

- `CF_PLAN_UNIVERSE_SNAPSHOT_SCHEMA_INVALID`
  - Makna: export snapshot universe PLAN tidak sesuai kontrak snapshot yang resmi.

- `CF_EVAL_METRICS_INSUFFICIENT`
  - Makna: metrik minimum untuk memilih paramset terbaik / aktif tidak terpenuhi.

- `CF_OOS_PROOF_FAILED`
  - Makna: bukti OOS gagal memenuhi acceptance minimum.

- `CF_ARTIFACT_REFERENCE_VIOLATION`
  - Makna: dokumen atau proof menyebut artefak di luar manifest / allowlist resmi.
