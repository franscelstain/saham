# Audit Layer Classification Rules

## Layer A — System Docs
Paket dianggap Layer A bila isi dominannya adalah:
- system docs
- domain contracts
- owner docs
- DB/schema support docs
- invariants, decision tables, publication/correction contracts

## Layer B — Implementation Guidance
Paket dianggap Layer B bila isi dominannya adalah:
- module mapping
- runtime artifact flow translation
- API guidance
- persistence guidance
- test guidance
- runbooks
- delivery checklist
- implementation audit guidance

## Layer C — Real Implementation / App Runtime
Paket dianggap Layer C hanya bila ada bukti nyata yang bisa ditelusuri, misalnya:
- real application code
- real service/controller/use-case code
- runtime payload nyata
- runtime schema yang dipakai app
- actual logs / event summaries
- executed test result nyata
- archived execution evidence nyata

## Allowed package classifications
- A only
- B only
- C only
- A+B
- A+B+C
- mixed misleading package

## Dominant-layer rule
Audit harus selalu menyatakan layer dominan. Contoh:
- paket dominan kontrak dan schema = dominan A
- paket dominan runbook dan mapping = dominan B
- paket dominan code dan runtime outputs = dominan C

## Downgrade rule
Paket tidak boleh diklaim Layer C bila evidence yang ada hanya berupa:
- template
- illustrative examples
- example payloads
- admission criteria tanpa executed result
- archived path structure tanpa actual runtime contents

## Important classification notes
- Folder `examples/` tidak otomatis membuat paket menjadi Layer C.
- Folder `evidence/` hanya mendukung Layer C bila isinya benar-benar archived actual execution evidence yang traceable.
- Archived executed evidence dapat mengaktifkan Layer C walaupun paket tidak berisi code aplikasi nyata.
- Namun audit wajib tetap membedakan antara:
  - `Layer C via archived runtime evidence`, dan
  - `Layer C via real implementation/app runtime surface`.
- Perbedaan dua bentuk Layer C ini adalah **batas klasifikasi**, bukan otomatis kelemahan. Selama paket jujur terhadap jenis buktinya, kondisi ini tidak boleh ditulis sebagai defect.
- Package bisa dominan A walaupun mengandung sebagian B dan sebagian C.
