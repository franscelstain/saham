# Corporate Action and Price Adjustment Policy (EOD)

> **Status:** Background only / Non-authoritative.  
> **Authoritative default:** lihat `Corporate_Action_and_Adjustment_Policy_Selected_Defaults_LOCKED.md`.

Options:
- Provider-adjusted (store adj_close)
- No adjusted series
- Internal adjustment (future/global dependency)

Locked rules:
- ATR/TR always real OHLC
- if ADJ_CLOSE used and missing, fallback to close