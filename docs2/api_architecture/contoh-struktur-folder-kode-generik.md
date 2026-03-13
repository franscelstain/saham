# Contoh Struktur Folder Kode Generik

## Struktur tingkat atas

```text
src/
  transport/
  application/
  domain/
  persistence/
  response/
  contracts/
  integration/
  support/
```

## Penjelasan singkat
- `transport/` untuk controller, handler, webhook receiver, queue consumer, CLI boundary
- `application/` untuk application service dan orchestration use case
- `domain/` untuk policy, rule, calculator, evaluator, classifier
- `persistence/` untuk repository, DAO, query object, read model, persistence adapter
- `response/` untuk response mapper dan presenter
- `contracts/` untuk DTO dan kontrak data
- `integration/` untuk adapter provider eksternal
- `support/` untuk utilitas lintas concern yang netral
