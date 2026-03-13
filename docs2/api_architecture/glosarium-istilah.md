# Glosarium Istilah

Dokumen ini menjelaskan istilah utama yang dipakai di paket arsitektur.

## Transport Boundary
Pintu masuk komunikasi eksternal seperti controller, handler, webhook receiver, queue consumer, atau CLI boundary.

## Application Service
Komponen yang mengorkestrasi use case aplikasi dan mengatur urutan langkah kerja.

## Repository
Rumah resmi query dan persistence.

## Persistence Layer
Area resmi untuk repository, DAO, query object, read model, dan adapter storage setara.

## Domain Compute
Rumah rule bisnis murni, scoring, klasifikasi, policy, evaluasi, dan keputusan domain.

## DTO
Data Transfer Object, yaitu kontrak data eksplisit antar layer.

## Handoff
Perpindahan payload utama dari satu layer ke layer lain.

## Response Layer
Rumah kontrak output publik dan pemetaan error publik.

## Read Model
Bentuk data baca yang dioptimalkan untuk kebutuhan query tertentu.

## Materialization
Penyimpanan hasil turunan atau snapshot untuk mengurangi beban query live.

## Idempotensi
Sifat operasi yang aman diulang dengan identitas operasi yang sama tanpa menghasilkan efek ganda yang tidak sah.

## Determinisme
Sifat proses yang menghasilkan hasil konsisten untuk input dan kondisi kontrak yang sama.

## Partial Failure
Kondisi saat sebagian proses berhasil dan sebagian gagal, atau titik kegagalan tidak sepenuhnya jelas.

## Bulk Write
Operasi tulis dalam kelompok besar yang lebih efisien daripada item-per-item.

## Chunking
Pemrosesan data dalam potongan terbatas.

## Streaming
Pembacaan atau pengaliran hasil secara bertahap tanpa memuat semua ke memory sekaligus.

## Cursor Access
Iterasi hasil bertahap dengan mekanisme cursor resmi.

## ADR
Architecture Decision Record, catatan keputusan arsitektur penting dan alasannya.
