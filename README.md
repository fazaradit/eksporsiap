# EksporSiap

**AI Copilot Compliance Ekspor untuk UMKM Indonesia**

EksporSiap membantu pelaku UMKM Indonesia menentukan kandidat klasifikasi HS Code dan syarat dokumen ekspor untuk produk pangan olahan mereka, berdasarkan deskripsi produk yang dituliskan sendiri oleh pengguna.

> Dibuat untuk AI Innovation Challenge (AIC) COMPFEST 18 — subtema Smart Commerce.

---

## Fitur Utama

- **Retrieval HS Code berbasis embedding (RAG)** — mencocokkan deskripsi produk pengguna dengan database referensi HS Code menggunakan kemiripan semantik (cosine similarity), bukan pencocokan kata kunci biasa.
- **Guardrail relevansi produk** — jika skor kemiripan tertinggi di bawah ambang batas, sistem menolak memproses lebih lanjut dan memberi tahu pengguna bahwa produknya di luar cakupan MVP, alih-alih memaksakan jawaban yang tidak meyakinkan.
- **Checklist dokumen ekspor (rule-based)** — daftar dokumen wajib/opsional untuk negara tujuan (Malaysia dan Jepang pada MVP ini), diambil dari basis data statis yang telah dikurasi, sehingga hasilnya deterministik dan dapat diverifikasi.
- **Ringkasan naratif berbasis LLM** — merangkai hasil klasifikasi dan checklist menjadi penjelasan singkat berbahasa natural, dengan mekanisme *fallback* otomatis ke ringkasan non-AI jika API LLM gagal dipanggil.
- **Field input opsional** (komposisi, jenis kemasan, berat) — memperkaya konteks deskripsi produk untuk meningkatkan akurasi klasifikasi tanpa mewajibkan pengguna mengisi detail teknis.

## Cakupan MVP (Batasan)

- Kategori produk: pangan olahan Bab 19–21 HS (produk roti/serealia, olahan buah/sayur, dan berbagai sediaan makanan).
- Negara tujuan: Malaysia dan Jepang.
- Model AI dioptimalkan melalui arsitektur RAG (retrieval embedding + generation), bukan fine-tuning penuh, sesuai klarifikasi resmi panitia AIC COMPFEST 18.
- Seluruh proses berjalan sinkron dalam satu request, tanpa background job atau antrian.

## Tech Stack

| Komponen | Teknologi |
| --- | --- |
| Backend | PHP native (MVC sederhana) |
| Database | PostgreSQL 15 |
| Embedding & LLM | Google Gemini API (`gemini-embedding-001` dan model generatif sesuai konfigurasi) |
| Frontend | HTML/CSS/JavaScript vanilla (tanpa framework) |
| Kontainerisasi | Docker & Docker Compose |

## Prasyarat

Sebelum memulai, pastikan sudah terpasang:
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (termasuk Docker Compose)
- Git
- API key Gemini dari [Google AI Studio](https://aistudio.google.com/api-keys) (gratis, tidak perlu kartu kredit untuk tingkat penggunaan MVP ini)

## Cara Menjalankan

### 1. Clone repository

```bash
git clone https://github.com/fazaradit/eksporsiap.git
cd eksporsiap
```

### 2. Siapkan file environment

```bash
cp .env.example .env
```

Buka `.env` dan isi variabel berikut dengan value asli:

```ini
DB_HOST=db
DB_PORT=5432
DB_DATABASE=eksporsiap
DB_USERNAME=postgres
DB_PASSWORD=postgres
EMBEDDING_API_KEY=isi_dengan_api_key_gemini_anda
EMBEDDING_API_URL=https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent
EMBEDDING_MODEL=models/gemini-embedding-001
LLM_MODEL=gemini-3.6-flash
```

> **Catatan:** `EMBEDDING_API_URL` di atas sudah berupa value final, tidak perlu diubah kecuali Anda ingin mengganti model embedding. Hanya `EMBEDDING_API_KEY` yang wajib diisi dengan key milik Anda sendiri.

### 3. Bangun dan jalankan container

```bash
docker-compose build
docker-compose up -d
```

Verifikasi kedua container berjalan:

```bash
docker-compose ps
```

Harus muncul `eksporsiap_app` dan `eksporsiap_db` dengan status `Up`.

> **Info:** Saat `docker-compose up` dijalankan pertama kali, PostgreSQL akan otomatis mengeksekusi semua file migration `*.sql` di dalam folder `database/` secara berurutan (dari `000_schema.sql` sampai `005`). Jadi Anda tidak perlu menjalankan setup tabel secara manual.

### 4. Import data referensi (HS Code + checklist compliance)

```bash
docker exec -it eksporsiap_app php scripts/import_data.php
```

Proses ini akan memanggil API embedding untuk setiap baris HS Code di `database/seed_hs_code.csv`, jadi membutuhkan koneksi internet dan API key yang valid. Script ini aman dijalankan berulang kali (idempotent) — data yang sudah ada akan diperbarui, bukan diduplikasi.

Verifikasi data berhasil masuk:

```bash
docker exec -it eksporsiap_db psql -U postgres -d eksporsiap -c "SELECT COUNT(*) FROM hs_code_reference;"
```
Harus menunjukkan 14 baris.

### 5. Akses aplikasi

Buka browser ke:

```
http://localhost:8000
```

## Menguji Fungsionalitas Inti (Opsional)

Untuk menguji service secara langsung tanpa lewat antarmuka web:

```bash
docker exec -it eksporsiap_app php scripts/test_services.php
```

Contoh pemanggilan API langsung:

```bash
curl -X POST http://localhost:8000/api/classify \
  -H "Content-Type: application/json" \
  -d '{"deskripsi_produk": "kerupuk udang kemasan plastik 250gr", "negara_tujuan": "Malaysia"}'
```

## Struktur Proyek

```text
eksporsiap/
├── docker-compose.yml
├── docker/php/Dockerfile
├── public/
│   ├── index.php              # Entry point, routing, dan tampilan frontend
│   └── .htaccess
├── src/
│   ├── Controllers/
│   │   └── ClassifyController.php
│   ├── Services/
│   │   ├── HsCodeRetrieval.php         # Core AI — retrieval embedding
│   │   └── ComplianceChecklistService.php  # Rule-based
│   └── Config/database.php
├── database/
│   ├── 000_schema.sql
│   ├── 001_add_embedding_and_compliance.sql
│   ├── 002_add_kata_kunci_produk.sql
│   ├── 003_add_compliance_unique_constraint.sql
│   ├── 004_add_hscode_unique_constraint.sql
│   ├── 005_rename_deskripsi_column.sql
│   ├── seed_hs_code.csv
│   └── seed_compliance_checklist.csv
└── scripts/
    ├── import_data.php
    └── test_services.php
```

## Arsitektur Singkat

Sistem mengikuti pola **RAG (Retrieval-Augmented Generation)**:

1. **Retrieval** — deskripsi produk pengguna diubah menjadi vector embedding (Gemini `gemini-embedding-001`), dibandingkan dengan embedding seluruh entri HS Code di database menggunakan cosine similarity.
2. **Guardrail** — jika skor kemiripan tertinggi di bawah 0.60, proses dihentikan dan pengguna diberi tahu bahwa produknya di luar cakupan sistem, tanpa memanggil LLM sama sekali.
3. **Augmentation & Generation** — jika lolos guardrail, top-3 kandidat HS Code dan checklist dokumen compliance disusun sebagai konteks, lalu dikirim ke Gemini (`generateContent`) untuk menghasilkan ringkasan naratif berbahasa natural.
4. **Fallback** — jika pemanggilan LLM gagal (galat jaringan, model sedang sibuk, dsb.), sistem otomatis menghasilkan ringkasan sederhana dari data yang ada, sehingga endpoint tetap merespons tanpa error.

Checklist dokumen compliance sengaja **tidak** menggunakan AI/embedding — ini keputusan desain sadar agar hasilnya deterministik dan mudah diverifikasi, bukan keterbatasan teknis.

## Disclaimer

Hasil klasifikasi HS Code dan checklist dokumen dari sistem ini adalah **rekomendasi awal**, bukan pengganti konsultasi resmi dengan Bea Cukai atau customs broker berlisensi.