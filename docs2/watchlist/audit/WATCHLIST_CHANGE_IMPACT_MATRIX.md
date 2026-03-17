# Watchlist Change Impact Matrix

Gunakan matriks ini untuk menentukan file yang wajib dicek ulang setiap kali ada perubahan.

## Jika berubah di recommendation

Wajib cek ulang:
- `22`
- `23`
- `24`
- `25`
- `02`
- `03`
- `10`
- `13`
- `21`
- `_refs / examples / fixtures / db` yang terkait recommendation

## Jika berubah di confirm

Wajib cek ulang:
- `10`
- `02`
- `03`
- `11`
- `13`
- `21`
- examples / fixtures confirm
- recommendation docs untuk memastikan boundary tidak bocor

## Jika berubah di plan / group semantics

Wajib cek ulang:
- `08`
- `09`
- `02`
- `03`
- `13`
- `22–24` untuk memastikan recommendation tetap sinkron

## Jika berubah di scope atau owner orientation

Wajib cek ulang:
- `system/README.md`
- `01`
- `02`
- `21`
- seluruh audit docs

## Jika berubah di support docs

Wajib cek ulang:
- owner file yang dirujuk support docs tersebut
- `_refs / examples / fixtures / db` lain yang serupa
- `13` bila contoh/fixture menyentuh acceptance

## Rule

Perubahan tidak boleh dianggap selesai jika owner docs yang terdampak belum ikut diperiksa ulang.
