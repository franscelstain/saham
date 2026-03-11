# Config Change Protocol (LOCKED)

Any output-affecting config change must be treated as a contract change.

## Locked rules
- change must be recorded in the configuration registry with effective date
- reruns must use the registry version effective for the requested trade date or explicitly documented override
- if semantics change output, indicator_set_version and/or replay expectations must be updated
- ad-hoc undocumented config tweaks are forbidden for production output
