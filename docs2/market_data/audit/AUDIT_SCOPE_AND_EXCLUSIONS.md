# Audit Scope and Exclusions

## Scope
Audit market-data menilai kualitas dan konsistensi domain producer-side data platform, termasuk:
- contract clarity
- schema support alignment
- publication behavior
- correction behavior
- implementation guidance readiness
- evidence strength
- consumer readability guarantees

## Exclusions
Audit ini tidak menilai dan tidak boleh dialihkan ke:
- strategy alpha generation
- watchlist grouping / scoring
- recommendation ranking
- intraday trading signal finalization
- broker execution behavior
- portfolio action and allocation

## Handling out-of-scope contamination
Bila satu file atau satu area mulai membahas domain consumer decision atau execution:
- tandai sebagai domain drift
- sebutkan file dan bagian yang terdampak
- nilai minimal PARTIAL
- naikkan menjadi FAIL bila drift mengubah definisi sistem

## Non-goals of this audit
Audit ini bukan:
- review profitabilitas trading
- review kualitas strategi watchlist
- review UX consumer downstream
