# System Runtime Flow

## High-level flow
1. source acquisition starts
2. raw or mapped source data is validated
3. canonical rows and derived outputs are produced
4. persistence and quality gates are applied
5. publication artifacts are prepared and sealed
6. current publication pointer may be switched
7. run evidence is archived
8. correction / replay can occur later when needed

## Operator touchpoints
Operator dapat terlibat pada:
- run initiation
- incident triage
- correction execution
- replay supervision
- publication verification

## Failure handling
Failure handling detail tetap ada di `ops/`, tetapi secara sistemik kegagalan harus:
- terlihat
- terklasifikasi
- tidak diam-diam mengubah published state
