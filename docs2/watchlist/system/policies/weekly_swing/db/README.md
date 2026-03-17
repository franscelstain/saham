# Weekly Swing DB Support

Folder `db/` berisi artefak pendukung schema, seed, dan contoh persistence untuk Weekly Swing. Folder ini mendukung system docs, tetapi tidak mengambil alih owner rule.

## Contents

- PLAN universe snapshot support
- backtest schema support
- reason code seed support
- recommendation runtime persistence support
- paramset promotion examples

## Recommendation Persistence Boundary

Jika recommendation dipersist sebagai artifact harian, persistence tersebut:
- berasal dari PLAN immutable;
- tidak membaca hasil CONFIRM sebagai input pembentukannya;
- tidak mengubah owner semantics pada recommendation contract.

Artifact CONFIRM harus tetap dipisah dari artifact RECOMMENDATION.

## Reading Rule

Jika ada perbedaan antara file DB support dan owner docs pada folder parent, maka owner docs selalu menang.
