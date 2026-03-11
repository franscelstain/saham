# Effective Trade Date Contract (LOCKED)

## Rule (LOCKED)
For requested date T:
- if run SUCCESS => effective = T
- if HELD/FAILED => effective = last_good_trade_date (latest prior SUCCESS)

Consumer invariant:
- do not build outputs from requested date if not SUCCESS