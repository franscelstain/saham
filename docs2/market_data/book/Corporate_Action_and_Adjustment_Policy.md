# Corporate Action and Adjustment Policy Reference Note

## Role of this file
This file is a compact reference note only.
It summarizes the option space and points readers to the authoritative selected-default contract.
It must not be treated as a parallel policy owner.

## Authoritative owner
The locked default behavior is defined by:
- `Corporate_Action_and_Adjustment_Policy_Selected_Defaults_LOCKED.md`
- `../indicators/EOD_Indicators_Formula_Spec.md` for formula-level price-basis application
- `EOD_Indicators_Contract.md` for artifact-level indicator semantics

## Option space summary
Possible implementation families discussed in this domain are:
- provider-adjusted close available and stored as `adj_close`
- no adjusted series available
- internal adjustment pipeline (only if separately specified and versioned)

## Locked default summary
The current locked default is:
- `PRICE_BASIS_DEFAULT = ADJ_CLOSE`, with per-date fallback to `CLOSE` when `adj_close` is `NULL`
- ATR/TR uses real OHLC inputs
- changing the selected default requires an `indicator_set_version` bump and recomputation

## Anti-ambiguity rule
If this file and the locked selected-default contract ever appear to disagree, the locked selected-default contract wins.
