# Volume and Turnover Normalization (LOCKED)

- `volume` in `eod_bars` stores provider-reported traded share units after canonical normalization
- `turnover_idr = close * volume * LOT_SIZE`
- `LOT_SIZE` comes from the effective config registry for the replayed/requested date
- no downstream-specific turnover reinterpretation is allowed upstream