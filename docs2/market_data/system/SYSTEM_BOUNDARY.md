# System Boundary

## Upstream boundary
Upstream adalah provider dan source feeds yang memasok data mentah atau semi-processed ke platform.

## Internal boundary
Di dalam boundary market-data, area utama adalah:
- source acquisition
- mapping / identity handling
- validation and anomaly classification
- canonicalization
- persistence
- publication
- correction / reseal / replay
- observability and audit evidence

## Downstream boundary
Downstream consumer, termasuk watchlist PLAN/CONFIRM, hanya dipandang sebagai consumer. Market-data boleh menentukan readability dan readiness guarantee, tetapi tidak boleh mengambil alih strategy logic consumer.

## Out of scope
- security selection
- signal confirmation logic sebagai owner behavior consumer
- recommendation ranking
- order execution and broker routing
- capital allocation logic

## Boundary rule
Bila dokumen market-data mulai menentukan apa yang harus dibeli atau dijual, dokumen tersebut sudah keluar domain.
