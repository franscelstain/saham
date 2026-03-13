# Logging Operasional

Dokumen ini mengatur logging untuk kebutuhan observabilitas, debugging, dan investigasi operasional.

## Tujuan logging
- Mengetahui start, progress, warning, error, dan akhir proses.
- Menyediakan context yang cukup untuk investigasi.
- Membantu audit teknis tanpa mencampur logging dengan domain logic.

## Aturan logging
- Logging operasional boleh dilakukan di transport boundary, application service, job, scheduler, dan komponen operasional lain yang relevan.
- Logging di domain compute dilarang.
- Repository hanya boleh menulis log pada kondisi kegagalan query atau exception yang memang perlu dicatat.
- File log bukan pengganti storage terstruktur.

## Context minimum yang wajib ada pada log penting
- nama proses atau use case
- identitas run bila ada
- scope tanggal atau range bila relevan
- identitas entitas yang diproses bila relevan
- informasi chunk atau batch bila relevan
- exception class dan message untuk error

## Severity minimum
- info untuk start, progress, summary
- warning untuk anomali yang tidak menghentikan proses
- error untuk kegagalan unit kerja
- critical untuk kondisi yang memaksa proses berhenti
