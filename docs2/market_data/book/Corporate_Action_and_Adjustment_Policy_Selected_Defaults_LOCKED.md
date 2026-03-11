# Corporate Action and Adjustment Policy — Selected Defaults (LOCKED)

Selected default:
- PRICE_BASIS_DEFAULT = ADJ_CLOSE (fallback to CLOSE if adj_close NULL)
- store provider adj_close in eod_bars.adj_close when available

ATR/TR always real OHLC.
Any change requires indicator_set_version bump + recompute.