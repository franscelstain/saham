# Symbol Lifecycle and Mapping Contract (Global Dependency)

- ticker_id is immutable identity
- ticker_code may change

Rules:
- ticker_code changes must map to same ticker_id (post-change)
- delist => stop ingest after delist date, eligibility 0
- new listing => included when active