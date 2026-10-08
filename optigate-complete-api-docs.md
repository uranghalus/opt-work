# 📖 Dokumentasi Lengkap API Portal DM (OptiGate)

> **PT Tata Optima Property** • Portal Ekosistem & Single Sign-On Terpusat  
> **Versi Dokumen:** `1.0-Enterprise` | **Terakhir Diperbarui:** `Oktober 2026`  
> **Base URL Production:** `https://gate.appdutamall.com`  
> **Base URL Development / Staging:** `http://localhost:8000`

---

Selamat datang di portal dokumentasi resmi Application Programming Interface (API) Portal DM (OptiGate).

### 📑 Panduan Khusus per Modul / Tab:
Untuk kenyamanan integrasi yang terfokus, Anda dapat membaca dan mengunduh panduan spesifik untuk masing-masing modul:

| Modul / Tab | File Markdown | Deskripsi |
| :--- | :--- | :--- |
| **SAML 2.0 Single Sign-On** | [`docs/SAML_2.0_SSO.md`](file:///root/portal-dm/docs/SAML_2.0_SSO.md) | Panduan IdP, XML metadata, claim atribut, registrasi SP, dan tutorial Nextcloud, Google, Laravel, Node.js, AWS. |
| **Notification Hub** | [`docs/NOTIFICATION_API.md`](file:///root/portal-dm/docs/NOTIFICATION_API.md) | Panduan pengiriman notifikasi realtime, mode *Actionable* (terkunci dari hapus), resolver UUID target, dan *auto-resolve*. |
| **OptiGuard Security SDK** | [`docs/OPTIGUARD_SECURITY.md`](file:///root/portal-dm/docs/OPTIGUARD_SECURITY.md) | Proteksi frontend (Anti-DevTools, Tab-Switch Privacy Blur, Inactivity Lock) dan backend Anti-Session Hijacking. |
| **REST API Reference** | [`docs/REST_API.md`](file:///root/portal-dm/docs/REST_API.md) | Katalog 52 endpoint master data organisasi, hierarki, profil staf, klien, dan log audit. |

---

## 📋 Daftar Isi Dokumen Master

1. [Ringkasan Skema Autentikasi & Keamanan](#1-ringkasan-skema-autentikasi--keamanan)
   - [1.1 Perbandingan Metode Autentikasi](#11-perbandingan-metode-autentikasi)
   - [1.2 Bearer Token (Laravel Passport)](#12-bearer-token-laravel-passport)
   - [1.3 Kredensial Klien (Notification Ingestion Hub)](#13-kredensial-klien-notification-ingestion-hub)
   - [1.4 Federasi Identitas SAML 2.0](#14-federasi-identitas-saml-20)
   - [1.5 Session Cookie (Cascading Internal Helpers)](#15-session-cookie-cascading-internal-helpers)
2. [Konvensi Format Request & Respon](#2-konvensi-format-request--respon)
   - [2.1 Format Respon Sukses](#21-format-respon-sukses)
   - [2.2 Format Respon Kesalahan (Error)](#22-format-respon-kesalahan-error)
   - [2.3 Daftar Kode Status HTTP](#23-daftar-kode-status-http)
3. [Katalog Endpoint API](#3-katalog-endpoint-api)
   - [Grup 1: 1. Profil Pengguna Sendiri (Me & Profile)](#grup-1-profil-pengguna-sendiri-me-profile) (4 endpoint)
     - [GET `/api/user`: Lihat Profil Sendiri (Me)](#ep-1-1-get-api-user)
     - [POST `/api/user/profile`: Update Profil & Avatar](#ep-1-2-post-api-user-profile)
     - [PUT `/api/user/password`: Ganti Kata Sandi (Update Password)](#ep-1-3-put-api-user-password)
     - [DELETE `/api/user/photo`: Hapus Foto Avatar Profil](#ep-1-4-delete-api-user-photo)
   - [Grup 2: 2. Perusahaan (Companies Resource)](#grup-2-perusahaan-companies-resource) (8 endpoint)
     - [GET `/api/companies`: List Semua Perusahaan](#ep-2-1-get-api-companies)
     - [GET `/api/companies/{company}`: Detail Perusahaan Tunggal](#ep-2-2-get-api-companies-company)
     - [GET `/api/companies/{company}/departments`: List Departemen di Perusahaan](#ep-2-3-get-api-companies-company-departments)
     - [GET `/api/companies/{company}/divisions`: List Divisi di Perusahaan](#ep-2-4-get-api-companies-company-divisions)
     - [GET `/api/companies/{company}/positions`: List Semua Jabatan di Perusahaan](#ep-2-5-get-api-companies-company-positions)
     - [GET `/api/companies/{company}/users`: List Semua Personel di Perusahaan](#ep-2-6-get-api-companies-company-users)
     - [GET `/api/companies/{company}/executives`: Jajaran Pimpinan Eksekutif & Dewan Direksi](#ep-2-7-get-api-companies-company-executives)
     - [GET `/api/companies/{company}/tree`: Pohon Struktur Organisasi Lengkap (Full Tree JSON)](#ep-2-8-get-api-companies-company-tree)
   - [Grup 3: 3. Departemen (Departments Resource)](#grup-3-departemen-departments-resource) (6 endpoint)
     - [GET `/api/departments`: List Semua Departemen](#ep-3-1-get-api-departments)
     - [GET `/api/departments/{department}`: Detail Departemen Tunggal](#ep-3-2-get-api-departments-department)
     - [GET `/api/departments/{department}/divisions`: List Divisi di Departemen](#ep-3-3-get-api-departments-department-divisions)
     - [GET `/api/departments/{department}/positions`: List Jabatan di Departemen](#ep-3-4-get-api-departments-department-positions)
     - [GET `/api/departments/{department}/users`: List Personel di Departemen](#ep-3-5-get-api-departments-department-users)
     - [GET `/api/departments/{department}/tree`: Pohon Struktur Departemen (Department Tree JSON)](#ep-3-6-get-api-departments-department-tree)
   - [Grup 4: 4. Divisi (Divisions Resource)](#grup-4-divisi-divisions-resource) (4 endpoint)
     - [GET `/api/divisions`: List Semua Divisi](#ep-4-1-get-api-divisions)
     - [GET `/api/divisions/{division}`: Detail Divisi Tunggal](#ep-4-2-get-api-divisions-division)
     - [GET `/api/divisions/{division}/positions`: List Jabatan di Divisi](#ep-4-3-get-api-divisions-division-positions)
     - [GET `/api/divisions/{division}/users`: List Personel di Divisi](#ep-4-4-get-api-divisions-division-users)
   - [Grup 5: 5. Jabatan / Posisi (Positions Resource)](#grup-5-jabatan-posisi-positions-resource) (5 endpoint)
     - [GET `/api/positions`: List Semua Jabatan dengan Filter Multi-Dimensi](#ep-5-1-get-api-positions)
     - [GET `/api/positions/{position}`: Detail Jabatan Tunggal](#ep-5-2-get-api-positions-position)
     - [GET `/api/positions/{position}/child-positions`: List Sub-Jabatan / Bawahan Langsung](#ep-5-3-get-api-positions-position-child-positions)
     - [GET `/api/positions/{position}/clients`: List Klien / Aplikasi Terdelegasi ke Jabatan](#ep-5-4-get-api-positions-position-clients)
     - [GET `/api/positions/{position}/users`: List Personel yang Menduduki Jabatan](#ep-5-5-get-api-positions-position-users)
   - [Grup 6: 6. Personel & Pengguna Global (Users Resource)](#grup-6-personel-pengguna-global-users-resource) (2 endpoint)
     - [GET `/api/users`: List Semua Pengguna dengan Filter Multi-Dimensi](#ep-6-1-get-api-users)
     - [GET `/api/users/{user}`: Detail Profil Pengguna Tunggal](#ep-6-2-get-api-users-user)
   - [Grup 7: 7. Hierarchical Chaining (Penelusuran Berjenjang Terstruktur)](#grup-7-hierarchical-chaining-penelusuran-berjenjang-terstruktur) (5 endpoint)
     - [GET `/api/companies/{company}/departments/{department}`: Departemen di Bawah Perusahaan Tertentu](#ep-7-1-get-api-companies-company-departments-department)
     - [GET `/api/companies/{company}/departments/{department}/divisions`: Divisi di Departemen Perusahaan Tertentu](#ep-7-2-get-api-companies-company-departments-department-divisions)
     - [GET `/api/companies/{company}/departments/{department}/divisions/{division}/positions`: Jabatan di Divisi Departemen Perusahaan Tertentu](#ep-7-3-get-api-companies-company-departments-department-divisions-division-positions)
     - [GET `/api/companies/{company}/departments/{department}/divisions/{division}/positions/{position}/users`: Personel di Jalur Penuh (Company -> Dept -> Div -> Pos -> Users)](#ep-7-4-get-api-companies-company-departments-department-divisions-division-positions-position-users)
     - [GET `/api/companies/{company}/departments/{department}/positions`: Jabatan Langsung di Departemen (Non-Divisi)](#ep-7-5-get-api-companies-company-departments-department-positions)
   - [Grup 8: 8. Public & Telemetri & Layanan Realtime](#grup-8-public-telemetri-layanan-realtime) (3 endpoint)
     - [POST `/api/network-status`: Pengecekan Jaringan Kantor vs Luar Kantor (Office Network Status)](#ep-8-1-post-api-network-status)
     - [GET `/api/air-quality`: Data Kualitas Udara Real-Time (Air Quality Index / ISPU)](#ep-8-2-get-api-air-quality)
     - [POST `/api/optiguard/incident`: Pelaporan Insiden Keamanan (OptiGuard Telemetry)](#ep-8-3-post-api-optiguard-incident)
   - [Grup 9: 9. Cascading Dropdowns (Internal Web & Organisasi Helper)](#grup-9-cascading-dropdowns-internal-web-organisasi-helper) (8 endpoint)
     - [GET `/api/cascading/available-users`: Daftar Personel Tanpa Jabatan (Available Users)](#ep-9-1-get-api-cascading-available-users)
     - [GET `/api/cascading/companies/{company}/departments`: Daftar Departemen Perusahaan (Cascading)](#ep-9-2-get-api-cascading-companies-company-departments)
     - [GET `/api/cascading/companies/{company}/direct-divisions`: Daftar Divisi Mandiri Perusahaan (Cascading)](#ep-9-3-get-api-cascading-companies-company-direct-divisions)
     - [GET `/api/cascading/departments/{department}/divisions`: Daftar Divisi Departemen (Cascading)](#ep-9-4-get-api-cascading-departments-department-divisions)
     - [GET `/api/cascading/departments/{department}/positions`: Daftar Jabatan Departemen (Cascading)](#ep-9-5-get-api-cascading-departments-department-positions)
     - [GET `/api/cascading/positions/{position}/details`: Detail Lengkap Jabatan & Hak Akses Klien yang Diwarisi](#ep-9-6-get-api-cascading-positions-position-details)
     - [POST `/api/cascading/positions/{position}/assign-user`: Penugasan Personel ke Jabatan (Assign User)](#ep-9-7-post-api-cascading-positions-position-assign-user)
     - [POST `/api/cascading/users/{user}/remove-position`: Pelepasan Personel dari Jabatan (Remove Position)](#ep-9-8-post-api-cascading-users-user-remove-position)
   - [Grup 10: 10. SAML 2.0 Identity Provider (OptiGate)](#grup-10-saml-2-0-identity-provider-optigate) (4 endpoint)
     - [GET `/saml/metadata`: IdP Metadata XML](#ep-10-1-get-saml-metadata)
     - [GET `/saml/certificate`: Unduh Sertifikat Publik X.509 IdP (Download .crt)](#ep-10-2-get-saml-certificate)
     - [GET / POST `/saml/sso`: Single Sign-On (SSO) Service](#ep-10-3-get-post-saml-sso)
     - [GET / POST `/saml/slo`: Single Logout (SLO) Service](#ep-10-4-get-post-saml-slo)
   - [Grup 11: 11. Notifikasi Terpusat (Centralized Notification Hub)](#grup-11-notifikasi-terpusat-centralized-notification-hub) (3 endpoint)
     - [POST `/api/v1/notifications/send`: Kirim Notifikasi Real-Time (Ingestion)](#ep-11-1-post-api-v1-notifications-send)
     - [POST `/api/v1/notifications/resolve`: Selesaikan / Hilangkan Notifikasi Approval (Auto-Resolve)](#ep-11-2-post-api-v1-notifications-resolve)
     - [DELETE `/api/v1/notifications/by-reference`: Hapus Notifikasi Berdasarkan Reference ID](#ep-11-3-delete-api-v1-notifications-by-reference)
4. [Contoh Kode Integrasi Multi-Bahasa](#4-contoh-kode-integrasi-multi-bahasa)
   - [4.1 PHP (Guzzle HTTP & cURL)](#41-php-guzzle-http--curl)
   - [4.2 JavaScript / TypeScript (Fetch & Axios)](#42-javascript--typescript-fetch--axios)
   - [4.3 Python (Requests)](#43-python-requests)
   - [4.4 Go (net/http)](#44-go-nethttp)
5. [Penanganan Masalah & FAQ (Troubleshooting)](#5-penanganan-masalah--faq-troubleshooting)

---

## 1. Ringkasan Skema Autentikasi & Keamanan

Portal DM menerapkan sistem keamanan berlapis disesuaikan dengan jenis konsumen API dan tingkat sensitivitas data:

### 1.1 Perbandingan Metode Autentikasi

| Kategori API | Metode Autentikasi | Header Wajib | Keterangan & Target Penggunaan |
| :--- | :--- | :--- | :--- |
| **REST API (Master Data & Profil)** | **OAuth2 Bearer Token** (Laravel Passport) | `Authorization: Bearer <token>` | Digunakan untuk sinkronisasi data master organisasi, hierarki, dan manajemen profil staf oleh sistem eksternal / klien. |
| **Notifikasi Hub Ingestion** | **Kredensial Klien API** (`X-Client-*`) atau **Bearer** | `X-Client-ID: <id>`<br>`X-Client-Secret: <secret>` | Digunakan oleh microservices / aplikasi internal (Cuti, HRIS, Ticketing) untuk mengirim atau me-resolve notifikasi real-time. |
| **SAML 2.0 Identity Provider** | **Sertifikat Digital X.509** (RSA-SHA256) | SAML Request XML via HTTP-Redirect / HTTP-POST | Digunakan untuk Single Sign-On (SSO) browser ke aplikasi pihak ketiga (Nextcloud, Google Workspace, AWS, Laravel SP). |
| **Cascading Dropdowns** | **Session Cookie Web** (Inertia Session) | `Cookie: ...`<br>`X-CSRF-TOKEN: <token>` | Digunakan khusus oleh antarmuka web Portal DM untuk mengisi dropdown formulir secara dinamis. |
| **Public & Telemetri** | **Tanpa Autentikasi (Terbuka)** | Header HTTP standar | Pengecekan IP jaringan kantor, pemantauan sensor udara, dan broadcast Web Push VAPID key. |

### 1.2 Bearer Token (Laravel Passport)

Untuk mengakses endpoint REST API (`/api/user`, `/api/companies`, `/api/departments`, dll.), klien wajib menyertakan token autentikasi yang valid:

```http
GET /api/user HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

> 💡 **Tips Pengujian:** Token Personal Access Token (PAT) atau OAuth2 Client Credentials dapat dibuat melalui menu **Master Data > Klien OAuth / SAML** di dashboard Admin Portal DM.

### 1.3 Kredensial Klien (Notification Ingestion Hub)

Endpoint Notifikasi Terpusat (`/api/v1/notifications/*`) mendukung autentikasi ringkas menggunakan pasangan Client ID dan Client Secret yang didaftarkan pada Portal DM:

```http
POST /api/v1/notifications/send HTTP/1.1
Host: gate.appdutamall.com
Content-Type: application/json
Accept: application/json
X-Client-ID: cuti-online-app
X-Client-Secret: 4f8b9a1e-8765-4321-abcd-ef0123456789
```

### 1.4 Federasi Identitas SAML 2.0

OptiGate bertindak sebagai SAML 2.0 Identity Provider (IdP). URL endpoint penting:
- **IdP Entity ID / Issuer**: `https://gate.appdutamall.com/saml/metadata`
- **Single Sign-On (SSO) URL**: `https://gate.appdutamall.com/saml/sso`
- **Single Logout (SLO) URL**: `https://gate.appdutamall.com/saml/slo`
- **Sertifikat Publik X.509**: `https://gate.appdutamall.com/saml/certificate`

### 1.5 Session Cookie (Cascading Internal Helpers)

Endpoint cascading (`/api/cascading/*`) dilindungi oleh middleware `['web', 'auth']` dan memerlukan sesi login aktif serta token CSRF saat melakukan mutasi (POST).

---

## 2. Konvensi Format Request & Respon

### 2.1 Format Respon Sukses

Secara umum, respon API mengembalikan format JSON berstruktur standar:

```json
{
  "success": true,
  "message": "Deskripsi keberhasilan (opsional)",
  "data": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 45
  }
}
```

### 2.2 Format Respon Kesalahan (Error)

Jika terjadi kegagalan validasi atau otorisasi, format respon standar adalah:

```json
{
  "success": false,
  "message": "Data yang dikirimkan tidak valid.",
  "errors": {
    "email": [
      "Alamat email sudah terdaftar."
    ]
  }
}
```

### 2.3 Daftar Kode Status HTTP

| Kode HTTP | Arti | Skenario Penggunaan |
| :--- | :--- | :--- |
| `200 OK` | Berhasil | Permintaan GET, PUT, DELETE, atau POST berhasil diproses. |
| `201 Created` | Sumber Dibuat | Permintaan pembuatan data baru atau notifikasi baru berhasil disimpan. |
| `400 Bad Request` | Permintaan Salah | Parameter tidak sesuai ketentuan atau sintaks payload rusak. |
| `401 Unauthorized` | Belum Login | Token Bearer hilang, kadaluarsa, atau kredensial `X-Client-*` tidak cocok. |
| `403 Forbidden` | Akses Ditolak | Akun tidak memiliki peran / izin (Spatie permission) untuk mengakses resource. |
| `404 Not Found` | Data Tidak Ditemukan | Entitas ID yang diminta tidak ada di database. |
| `422 Unprocessable Entity` | Validasi Gagal | Payload JSON tidak lolos aturan validasi form Laravel. |
| `500 Server Error` | Kesalahan Internal | Terjadi exception di sisi server backend. |

---

## 3. Katalog Endpoint API

### 1. Profil Pengguna Sendiri (Me & Profile)
<a id="grup-1-profil-pengguna-sendiri-me-profile"></a>

> ℹ️ **Deskripsi Grup:** Endpoint untuk mengelola profil, kata sandi, dan foto avatar pengguna yang sedang terautentikasi (pemilik token).

#### 1. Lihat Profil Sendiri (Me)
<a id="ep-1-1-get-api-user"></a>

- **Metode & URL:** `GET` `/api/user`
- **Deskripsi:** Mengambil detail profil akun Anda sendiri beserta hak akses jabatan, divisi, departemen, perusahaan, role Spatie, permission, dan perusahaan yang dikelola.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:** *Tidak membutuhkan payload body khusus.*

**Contoh Request:**
```http
GET /api/user HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "data": {
    "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
    "name": "Muhammad Ridha Fatahillah",
    "email": "ridho.fatahillah@tataoptima.com",
    "whatsapp_number": "081234567890",
    "nik": "6371012304950001",
    "photo_url": "https://s3.tataoptima.com/storage/profile-photos/avatar.jpg",
    "is_active": true,
    "onboarded": true,
    "company_id": 1,
    "department_id": 2,
    "position_id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
    "permissions": [
      "manage-users",
      "manage-companies",
      "manage-departments",
      "manage-positions",
      "view-logs"
    ],
    "managed_companies": [
      {
        "id": 1,
        "code": "TOP",
        "name": "PT Tata Optima Property"
      }
    ]
  }
}
```

---

#### 2. Update Profil & Avatar
<a id="ep-1-2-post-api-user-profile"></a>

- **Metode & URL:** `POST` `/api/user/profile`
- **Deskripsi:** Memperbarui informasi biodata profil dan mengunggah foto avatar (mendukung form multipart).
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `name` | `string` | **Ya** | Nama lengkap pengguna. |
| `email` | `string` | **Ya** | Alamat email aktif (unik). |
| `nik` | `string` | Tidak | Nomor Induk Karyawan / NIK. |
| `whatsapp_number` | `string` | Tidak | Nomor WhatsApp aktif. |
| `photo` | `file` | Tidak | File foto profil baru (format jpg/jpeg/png, maks 2MB). |

**Contoh Request:**
```http
POST /api/user/profile HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Content-Type: multipart/form-data

name=Muhammad Ridha Fatahillah
email=ridho.fatahillah@tataoptima.com
whatsapp_number=081234567890
nik=6371012304950001
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
    "name": "Muhammad Ridha Fatahillah",
    "email": "ridho.fatahillah@tataoptima.com",
    "whatsapp_number": "081234567890",
    "nik": "6371012304950001",
    "photo_url": "https://s3.tataoptima.com/storage/profile-photos/avatar.jpg",
    "is_active": true
  }
}
```

---

#### 3. Ganti Kata Sandi (Update Password)
<a id="ep-1-3-put-api-user-password"></a>

- **Metode & URL:** `PUT` `/api/user/password`
- **Deskripsi:** Memperbarui kata sandi akun pengguna yang sedang login.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `current_password` | `string` | **Ya** | Kata sandi akun saat ini. |
| `password` | `string` | **Ya** | Kata sandi baru (minimal 8 karakter, kombinasi simbol/angka). |
| `password_confirmation` | `string` | **Ya** | Konfirmasi kata sandi baru (harus sama persis). |

**Contoh Request:**
```http
PUT /api/user/password HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Content-Type: application/json

{
  "current_password": "KataSandiLama123!",
  "password": "KataSandiBaru456!",
  "password_confirmation": "KataSandiBaru456!"
}
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "message": "Password updated successfully",
  "data": null
}
```

---

#### 4. Hapus Foto Avatar Profil
<a id="ep-1-4-delete-api-user-photo"></a>

- **Metode & URL:** `DELETE` `/api/user/photo`
- **Deskripsi:** Menghapus foto profil avatar pengguna dan mengembalikan ke inisial default.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:** *Tidak membutuhkan payload body khusus.*

**Contoh Request:**
```http
DELETE /api/user/photo HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "message": "Profile photo removed successfully",
  "data": {
    "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
    "name": "Muhammad Ridha Fatahillah",
    "photo_url": null
  }
}
```

---

### 2. Perusahaan (Companies Resource)
<a id="grup-2-perusahaan-companies-resource"></a>

> ℹ️ **Deskripsi Grup:** Endpoint untuk mengelola data entitas perusahaan, dewan direksi / jajaran eksekutif, dan sub-hierarki organisasi.

#### 1. List Semua Perusahaan
<a id="ep-2-1-get-api-companies"></a>

- **Metode & URL:** `GET` `/api/companies`
- **Deskripsi:** Menampilkan daftar seluruh perusahaan beserta pimpinan eksekutif dan statistik anggota.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `q` | `string` | Tidak | Pencarian nama atau kode perusahaan. |
| `client_id` | `string` | Tidak | Filter perusahaan yang memiliki akses ke ID aplikasi tertentu. |
| `has_departments` | `boolean` | Tidak | Filter perusahaan yang memiliki departemen. |
| `has_direct_divisions` | `boolean` | Tidak | Filter perusahaan yang memiliki divisi mandiri (langsung di bawah GM). |
| `has_executives` | `boolean` | Tidak | Filter perusahaan yang sudah memiliki jajaran pimpinan. |
| `with` | `string` | Tidak | Eager load relasi: departments, director, directors, directors.user, gm, dgm, secretary, clients, directDivisions. |
| `with_count` | `string` | Tidak | Hitungan relasi: departments, directDivisions, users, positions. |
| `sort` | `string` | Tidak | Urutkan berdasarkan: name, code, created_at (awali dengan tanda minus untuk descending, misal: -created_at). |
| `paginate` | `boolean` | Tidak | Set true untuk mengaktifkan paginasi. |
| `per_page` | `integer` | Tidak | Jumlah data per halaman saat paginasi (default: 15). |

**Contoh Request:**
```http
GET /api/companies?with_count=departments,users&with=directors.user HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "TOP",
      "name": "PT Tata Optima Property",
      "president_director": {
        "id": "019fdfb7-0ee3-70ae-9a6a-8d3fa11b98ac",
        "name": "Direktur Utama",
        "email": "direktur@tataoptima.com"
      },
      "directors": [
        {
          "id": 1,
          "title": "Direktur Operasional",
          "order": 1,
          "user": {
            "id": "019fe123-4567-7890-abcd-ef1234567890",
            "name": "Bpk. Direktur",
            "email": "direktur.ops@tataoptima.com"
          }
        }
      ],
      "general_manager": null,
      "deputy_general_manager": null,
      "secretary": null,
      "departments_count": 8,
      "users_count": 120
    }
  ]
}
```

---

#### 2. Detail Perusahaan Tunggal
<a id="ep-2-2-get-api-companies-company"></a>

- **Metode & URL:** `GET` `/api/companies/{company}`
- **Deskripsi:** Mengambil rincian data satu perusahaan berdasarkan ID perusahaan.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan (Path parameter). |
| `with` | `string` | Tidak | Eager load relasi. |
| `with_count` | `string` | Tidak | Hitungan relasi. |

**Contoh Request:**
```http
GET /api/companies/1?with=departments,clients HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "TOP",
    "name": "PT Tata Optima Property",
    "president_director": {
      "id": "019fdfb7-0ee3-70ae-9a6a-8d3fa11b98ac",
      "name": "Direktur Utama",
      "email": "direktur@tataoptima.com"
    },
    "directors": [],
    "general_manager": null,
    "deputy_general_manager": null,
    "secretary": null,
    "departments": [
      {
        "id": 2,
        "code": "IT",
        "name": "Information Technology"
      }
    ],
    "clients": [
      {
        "id": "9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
        "name": "Nextcloud Cloud Drive"
      }
    ]
  }
}
```

---

#### 3. List Departemen di Perusahaan
<a id="ep-2-3-get-api-companies-company-departments"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/departments`
- **Deskripsi:** Mengambil seluruh departemen yang berada di bawah naungan perusahaan tertentu.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan (Path parameter). |
| `q` | `string` | Tidak | Pencarian nama atau kode departemen. |
| `with` | `string` | Tidak | Relasi: divisions, positions, hod, manager, users, clients. |
| `with_count` | `string` | Tidak | Hitungan: users, positions, divisions. |

**Contoh Request:**
```http
GET /api/companies/1/departments?with_count=users,positions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "HRD",
      "name": "Human Resources Department",
      "company_id": 1,
      "leadership_title": "Supervisor HRD",
      "users_count": 5,
      "positions_count": 3
    },
    {
      "id": 2,
      "code": "IT",
      "name": "Information Technology",
      "company_id": 1,
      "leadership_title": "Head of IT",
      "users_count": 12,
      "positions_count": 7
    }
  ]
}
```

---

#### 4. List Divisi di Perusahaan
<a id="ep-2-4-get-api-companies-company-divisions"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/divisions`
- **Deskripsi:** Mengambil seluruh divisi (termasuk divisi mandiri langsung di bawah GM) pada perusahaan tertentu.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan (Path parameter). |
| `direct_only` | `boolean` | Tidak | Set true untuk hanya mengambil divisi langsung di bawah GM (tanpa departemen). |

**Contoh Request:**
```http
GET /api/companies/1/divisions?direct_only=true HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "SEC-AST",
      "name": "Sekretariat & Asset Management",
      "department_id": null,
      "company_id": 1,
      "is_direct": true
    }
  ]
}
```

---

#### 5. List Semua Jabatan di Perusahaan
<a id="ep-2-5-get-api-companies-company-positions"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/positions`
- **Deskripsi:** Mengambil seluruh jabatan/posisi yang terdaftar di seluruh departemen & divisi pada perusahaan tersebut.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan (Path parameter). |
| `level` | `integer (1-7)` | Tidak | Filter level jabatan spesifik (1: Direksi s/d 7: Staff/Pelaksana). |
| `vacant_only` | `boolean` | Tidak | Hanya tampilkan posisi yang belum memiliki personel. |

**Contoh Request:**
```http
GET /api/companies/1/positions?level=7 HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
      "name": "Staff Programmer",
      "level": 7,
      "level_label": "Staff / Pelaksana",
      "department_id": 2,
      "is_direct": false
    }
  ]
}
```

---

#### 6. List Semua Personel di Perusahaan
<a id="ep-2-6-get-api-companies-company-users"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/users`
- **Deskripsi:** Mengambil seluruh anggota/personel yang terdaftar di perusahaan (melalui departemen, divisi, atau jajaran eksekutif).
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan (Path parameter). |
| `q` | `string` | Tidak | Pencarian nama, email, NIK, atau nama jabatan. |
| `is_active` | `boolean` | Tidak | Filter status akun aktif / nonaktif. |

**Contoh Request:**
```http
GET /api/companies/1/users?is_active=true HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com",
      "whatsapp_number": "081234567890",
      "is_active": true
    }
  ]
}
```

---

#### 7. Jajaran Pimpinan Eksekutif & Dewan Direksi
<a id="ep-2-7-get-api-companies-company-executives"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/executives`
- **Deskripsi:** Mengambil struktur pimpinan puncak perusahaan: Direktur Utama, Dewan Direksi (Board of Directors), General Manager (GM), Deputy GM, dan Sekretaris Perusahaan.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan (Path parameter). |

**Contoh Request:**
```http
GET /api/companies/1/executives HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "president_director": {
      "id": "019fdfb7-0ee3-70ae-9a6a-8d3fa11b98ac",
      "name": "Direktur Utama",
      "email": "director@tataoptima.com"
    },
    "directors": [
      {
        "id": 1,
        "title": "Direktur Operasional",
        "order": 1,
        "user": {
          "id": "019fe123-4567-7890-abcd-ef1234567890",
          "name": "Bpk. Direktur",
          "email": "direktur.ops@tataoptima.com"
        }
      }
    ],
    "general_manager": null,
    "deputy_general_manager": null,
    "secretary": null
  }
}
```

---

#### 8. Pohon Struktur Organisasi Lengkap (Full Tree JSON)
<a id="ep-2-8-get-api-companies-company-tree"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/tree`
- **Deskripsi:** Mengambil seluruh hierarki organisasi perusahaan lengkap (Dewan Direksi -> GM -> Departemen -> Divisi -> Jabatan -> Personel) dalam 1 struktur pohon JSON nested.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan (Path parameter). |

**Contoh Request:**
```http
GET /api/companies/1/tree HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "TOP",
    "name": "PT Tata Optima Property",
    "president_director": { "name": "..." },
    "directors": [],
    "departments": [
      {
        "id": 2,
        "code": "IT",
        "name": "Information Technology",
        "hod": { "name": "Head of IT" },
        "divisions": [
          {
            "id": 1,
            "code": "IT-DEV",
            "name": "Software Engineering",
            "positions": [
              {
                "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
                "name": "Staff Programmer",
                "users": [
                  { "name": "Muhammad Ridha Fatahillah" }
                ]
              }
            ]
          }
        ]
      }
    ]
  }
}
```

---

### 3. Departemen (Departments Resource)
<a id="grup-3-departemen-departments-resource"></a>

> ℹ️ **Deskripsi Grup:** Endpoint untuk mengelola data departemen, pimpinan HOD / Manager, struktur divisi di dalamnya, dan organigram pohon departemen.

#### 1. List Semua Departemen
<a id="ep-3-1-get-api-departments"></a>

- **Metode & URL:** `GET` `/api/departments`
- **Deskripsi:** Menampilkan daftar seluruh departemen dengan filter company_id, company_code, nama pimpinan, pencarian q, dan hitungan statistik.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company_id` | `integer` | Tidak | Filter berdasarkan ID perusahaan. |
| `company_code` | `string` | Tidak | Filter berdasarkan kode perusahaan (contoh: TOP). |
| `q` | `string` | Tidak | Pencarian nama atau kode departemen. |
| `leadership_role` | `string` | Tidak | Filter tingkat pimpinan (contoh: HOD, SPV, Manager). |
| `has_divisions` | `boolean` | Tidak | Filter departemen yang memiliki divisi. |
| `has_positions` | `boolean` | Tidak | Filter departemen yang memiliki posisi jabatan. |
| `has_leader` | `boolean` | Tidak | Filter departemen yang sudah memiliki pimpinan HOD/Manager. |
| `with_count` | `string` | Tidak | Hitungan relasi: users, positions, divisions. |
| `with` | `string` | Tidak | Relasi: company, divisions, positions, users, hod, manager, clients. |
| `sort` | `string` | Tidak | Urutkan berdasarkan: name, code, created_at. |
| `paginate` | `boolean` | Tidak | Set true untuk mengaktifkan paginasi. |

**Contoh Request:**
```http
GET /api/departments?company_id=1&with_count=users,positions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "code": "IT",
      "name": "Information Technology",
      "company_id": 1,
      "leadership_title": "Head of IT",
      "users_count": 12,
      "positions_count": 7,
      "divisions_count": 2
    }
  ]
}
```

---

#### 2. Detail Departemen Tunggal
<a id="ep-3-2-get-api-departments-department"></a>

- **Metode & URL:** `GET` `/api/departments/{department}`
- **Deskripsi:** Mengambil detail rincian satu departemen beserta relasi pimpinan HOD/Manager dan hierarki terkait.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department` | `integer` | **Ya** | ID Departemen (Path parameter). |
| `with` | `string` | Tidak | Relasi yang ingin dimuat: company, divisions, positions, users, hod, manager, clients. |
| `with_count` | `string` | Tidak | Hitungan relasi. |

**Contoh Request:**
```http
GET /api/departments/2?with=company,hod,divisions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "code": "IT",
    "name": "Information Technology",
    "company_id": 1,
    "leadership_title": "Head of IT",
    "company": {
      "id": 1,
      "code": "TOP",
      "name": "PT Tata Optima Property"
    },
    "hod": {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com"
    },
    "divisions": [
      {
        "id": 1,
        "code": "IT-DEV",
        "name": "Software Engineering"
      }
    ]
  }
}
```

---

#### 3. List Divisi di Departemen
<a id="ep-3-3-get-api-departments-department-divisions"></a>

- **Metode & URL:** `GET` `/api/departments/{department}/divisions`
- **Deskripsi:** Mengambil seluruh divisi yang berada di bawah departemen tertentu.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department` | `integer` | **Ya** | ID Departemen (Path parameter). |

**Contoh Request:**
```http
GET /api/departments/2/divisions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "IT-DEV",
      "name": "Software Engineering",
      "department_id": 2,
      "company_id": 1,
      "is_direct": false
    }
  ]
}
```

---

#### 4. List Jabatan di Departemen
<a id="ep-3-4-get-api-departments-department-positions"></a>

- **Metode & URL:** `GET` `/api/departments/{department}/positions`
- **Deskripsi:** Mengambil daftar seluruh posisi/jabatan di bawah departemen tertentu.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department` | `integer` | **Ya** | ID Departemen (Path parameter). |
| `level` | `integer (1-7)` | Tidak | Filter level jabatan. |
| `vacant_only` | `boolean` | Tidak | Hanya posisi yang belum ada personel. |

**Contoh Request:**
```http
GET /api/departments/2/positions?level=7 HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
      "name": "Staff Programmer",
      "level": 7,
      "level_label": "Staff / Pelaksana",
      "department_id": 2
    }
  ]
}
```

---

#### 5. List Personel di Departemen
<a id="ep-3-5-get-api-departments-department-users"></a>

- **Metode & URL:** `GET` `/api/departments/{department}/users`
- **Deskripsi:** Mengambil seluruh anggota/personel yang bertugas di departemen tersebut.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department` | `integer` | **Ya** | ID Departemen (Path parameter). |
| `q` | `string` | Tidak | Pencarian nama atau email. |

**Contoh Request:**
```http
GET /api/departments/2/users HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com",
      "is_active": true
    }
  ]
}
```

---

#### 6. Pohon Struktur Departemen (Department Tree JSON)
<a id="ep-3-6-get-api-departments-department-tree"></a>

- **Metode & URL:** `GET` `/api/departments/{department}/tree`
- **Deskripsi:** Mengambil hierarki pohon 1 departemen (Pimpinan Dept -> Divisi -> Jabatan -> Personel) lengkap.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department` | `integer` | **Ya** | ID Departemen (Path parameter). |

**Contoh Request:**
```http
GET /api/departments/2/tree HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "code": "IT",
    "name": "Information Technology",
    "hod": { "name": "Head of IT" },
    "divisions": [
      {
        "id": 1,
        "name": "Software Engineering",
        "positions": []
      }
    ]
  }
}
```

---

### 4. Divisi (Divisions Resource)
<a id="grup-4-divisi-divisions-resource"></a>

> ℹ️ **Deskripsi Grup:** Endpoint untuk mengelola data divisi (baik divisi di bawah departemen maupun divisi mandiri langsung di bawah GM).

#### 1. List Semua Divisi
<a id="ep-4-1-get-api-divisions"></a>

- **Metode & URL:** `GET` `/api/divisions`
- **Deskripsi:** Menampilkan daftar seluruh divisi dengan filter department_id, company_id, direct_only, has_leader, has_positions, pencarian q, relasi, dan paginasi.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department_id` | `integer` | Tidak | Filter berdasarkan ID departemen. |
| `company_id` | `integer` | Tidak | Filter berdasarkan ID perusahaan. |
| `direct_only` | `boolean` | Tidak | Set true untuk hanya menampilkan divisi mandiri langsung di bawah GM. |
| `has_leader` | `boolean` | Tidak | Filter divisi yang sudah memiliki koordinator / leader. |
| `has_positions` | `boolean` | Tidak | Filter divisi yang memiliki posisi jabatan. |
| `q` | `string` | Tidak | Pencarian nama atau kode divisi. |
| `with` | `string` | Tidak | Relasi: department, department.company, company, positions, leader. |
| `with_count` | `string` | Tidak | Hitungan relasi: positions. |
| `sort` | `string` | Tidak | Urutkan berdasarkan: name, code, created_at. |
| `paginate` | `boolean` | Tidak | Set true untuk mengaktifkan paginasi. |

**Contoh Request:**
```http
GET /api/divisions?company_id=1&with=department,leader HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "IT-DEV",
      "name": "Software Engineering",
      "department_id": 2,
      "company_id": 1,
      "is_direct": false,
      "leader": {
        "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
        "name": "Muhammad Ridha Fatahillah",
        "email": "ridho.fatahillah@tataoptima.com"
      },
      "department": {
        "id": 2,
        "code": "IT",
        "name": "Information Technology"
      }
    }
  ]
}
```

---

#### 2. Detail Divisi Tunggal
<a id="ep-4-2-get-api-divisions-division"></a>

- **Metode & URL:** `GET` `/api/divisions/{division}`
- **Deskripsi:** Mengambil detail rincian satu divisi beserta departemen induk dan jajaran jabatan.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `division` | `integer` | **Ya** | ID Divisi (Path parameter). |
| `with` | `string` | Tidak | Relasi: department, company, positions, leader. |
| `with_count` | `string` | Tidak | Hitungan relasi. |

**Contoh Request:**
```http
GET /api/divisions/1?with=positions,leader HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "IT-DEV",
    "name": "Software Engineering",
    "department_id": 2,
    "company_id": 1,
    "is_direct": false,
    "leader": {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com"
    },
    "positions": [
      {
        "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
        "name": "Staff Programmer",
        "level": 7,
        "level_label": "Staff / Pelaksana"
      }
    ]
  }
}
```

---

#### 3. List Jabatan di Divisi
<a id="ep-4-3-get-api-divisions-division-positions"></a>

- **Metode & URL:** `GET` `/api/divisions/{division}/positions`
- **Deskripsi:** Mengambil seluruh posisi/jabatan yang ditempatkan di bawah divisi tertentu.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `division` | `integer` | **Ya** | ID Divisi (Path parameter). |

**Contoh Request:**
```http
GET /api/divisions/1/positions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
      "name": "Staff Programmer",
      "level": 7,
      "level_label": "Staff / Pelaksana",
      "division_id": 1
    }
  ]
}
```

---

#### 4. List Personel di Divisi
<a id="ep-4-4-get-api-divisions-division-users"></a>

- **Metode & URL:** `GET` `/api/divisions/{division}/users`
- **Deskripsi:** Mengambil seluruh anggota/personel yang bertugas di bawah divisi tersebut.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `division` | `integer` | **Ya** | ID Divisi (Path parameter). |

**Contoh Request:**
```http
GET /api/divisions/1/users HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com",
      "is_active": true
    }
  ]
}
```

---

### 5. Jabatan / Posisi (Positions Resource)
<a id="grup-5-jabatan-posisi-positions-resource"></a>

> ℹ️ **Deskripsi Grup:** Endpoint untuk mengelola jabatan, level eselon (Level 1 s/d 7), atasan/bawahan hierarki, dan hak akses aplikasi (clients).

#### 1. List Semua Jabatan dengan Filter Multi-Dimensi
<a id="ep-5-1-get-api-positions"></a>

- **Metode & URL:** `GET` `/api/positions`
- **Deskripsi:** Menampilkan daftar seluruh jabatan dengan filter departemen, divisi, level, rentang level (level_min & level_max), status kosong (vacant_only/occupied_only), akses klien aplikasi, pencarian q, dan relasi.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department_id` | `integer` | Tidak | Filter berdasarkan ID departemen. |
| `division_id` | `integer` | Tidak | Filter berdasarkan ID divisi. |
| `company_id` | `integer` | Tidak | Filter berdasarkan ID perusahaan. |
| `direct_only` | `boolean` | Tidak | Filter jabatan mandiri di bawah perusahaan (tanpa departemen). |
| `parent_position_id` | `string (UUID)` | Tidak | Filter bawahan langsung dari ID jabatan atasan tertentu. |
| `is_root` | `boolean` | Tidak | true: jabatan puncak (tanpa atasan). |
| `is_leaf` | `boolean` | Tidak | true: jabatan ujung (tanpa bawahan). |
| `level` | `integer (1-7)` | Tidak | Filter level eselon jabatan spesifik. |
| `level_min / level_max` | `integer (1-7)` | Tidak | Filter rentang level eselon. |
| `vacant_only` | `boolean` | Tidak | Hanya tampilkan jabatan yang belum diduduki siapapun. |
| `occupied_only` | `boolean` | Tidak | Hanya tampilkan jabatan yang sudah ada personelnya. |
| `client_id` | `string` | Tidak | Filter jabatan yang memiliki hak akses ke aplikasi klien OAuth/SAML tertentu. |
| `q` | `string` | Tidak | Pencarian nama jabatan. |
| `with` | `string` | Tidak | Relasi: department, division, parentPosition, childPositions, users, clients. |
| `with_count` | `string` | Tidak | Hitungan relasi: users, childPositions. |
| `sort` | `string` | Tidak | Urutkan berdasarkan: level, name, order, created_at. |
| `paginate` | `boolean` | Tidak | Set true untuk mengaktifkan paginasi. |

**Contoh Request:**
```http
GET /api/positions?department_id=2&with=users,clients HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
      "name": "IT Manager",
      "level": 4,
      "level_label": "Manager",
      "department_id": 2,
      "division_id": null,
      "parent_position_id": null,
      "is_direct": false,
      "is_vacant": false,
      "users": [
        {
          "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
          "name": "Muhammad Ridha Fatahillah",
          "email": "ridho.fatahillah@tataoptima.com"
        }
      ],
      "clients": [
        {
          "id": "9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
          "name": "Nextcloud Cloud Drive"
        }
      ]
    }
  ]
}
```

---

#### 2. Detail Jabatan Tunggal
<a id="ep-5-2-get-api-positions-position"></a>

- **Metode & URL:** `GET` `/api/positions/{position}`
- **Deskripsi:** Mengambil rincian informasi satu jabatan beserta atasan, bawahan, personel, dan aplikasi yang dapat diakses.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `position` | `string (UUID)` | **Ya** | ID / UUID Jabatan (Path parameter). |
| `with` | `string` | Tidak | Relasi: department, division, parentPosition, childPositions, users, clients. |

**Contoh Request:**
```http
GET /api/positions/019fe005-a4b0-706d-90f3-9ecdbb3cfc99?with=department,division,users,clients HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
    "name": "IT Manager",
    "level": 4,
    "level_label": "Manager",
    "department_id": 2,
    "division_id": null,
    "parent_position_id": null,
    "is_direct": false,
    "is_vacant": false,
    "department": {
      "id": 2,
      "code": "IT",
      "name": "Information Technology"
    },
    "users": [
      {
        "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
        "name": "Muhammad Ridha Fatahillah",
        "email": "ridho.fatahillah@tataoptima.com"
      }
    ],
    "clients": []
  }
}
```

---

#### 3. List Sub-Jabatan / Bawahan Langsung
<a id="ep-5-3-get-api-positions-position-child-positions"></a>

- **Metode & URL:** `GET` `/api/positions/{position}/child-positions`
- **Deskripsi:** Mengambil daftar jabatan yang berkedudukan sebagai bawahan langsung dari posisi jabatan ini.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `position` | `string (UUID)` | **Ya** | ID / UUID Jabatan Atasan (Path parameter). |

**Contoh Request:**
```http
GET /api/positions/019fe005-a4b0-706d-90f3-9ecdbb3cfc99/child-positions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fe006-b5c1-717e-91a4-afedcc4ded00",
      "name": "Software Engineer Lead",
      "level": 5,
      "level_label": "Supervisor / Lead"
    }
  ]
}
```

---

#### 4. List Klien / Aplikasi Terdelegasi ke Jabatan
<a id="ep-5-4-get-api-positions-position-clients"></a>

- **Metode & URL:** `GET` `/api/positions/{position}/clients`
- **Deskripsi:** Mengambil daftar aplikasi pihak ketiga (OAuth/SAML) yang hak aksesnya diberikan secara eksplisit ke jabatan ini.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `position` | `string (UUID)` | **Ya** | ID / UUID Jabatan (Path parameter). |

**Contoh Request:**
```http
GET /api/positions/019fe005-a4b0-706d-90f3-9ecdbb3cfc99/clients HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
      "name": "Nextcloud Cloud Drive",
      "redirect": "https://cloud.tataoptima.com/apps/user_saml/saml/acs"
    }
  ]
}
```

---

#### 5. List Personel yang Menduduki Jabatan
<a id="ep-5-5-get-api-positions-position-users"></a>

- **Metode & URL:** `GET` `/api/positions/{position}/users`
- **Deskripsi:** Mengambil daftar seluruh pengguna/karyawan yang saat ini memegang posisi jabatan ini.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `position` | `string (UUID)` | **Ya** | ID / UUID Jabatan (Path parameter). |

**Contoh Request:**
```http
GET /api/positions/019fe005-a4b0-706d-90f3-9ecdbb3cfc99/users HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com",
      "is_active": true
    }
  ]
}
```

---

### 6. Personel & Pengguna Global (Users Resource)
<a id="grup-6-personel-pengguna-global-users-resource"></a>

> ℹ️ **Deskripsi Grup:** Endpoint pencarian, pemfilteran komprehensif, dan rincian data seluruh personel karyawan di portal.

#### 1. List Semua Pengguna dengan Filter Multi-Dimensi
<a id="ep-6-1-get-api-users"></a>

- **Metode & URL:** `GET` `/api/users`
- **Deskripsi:** Mengambil dan mencari pengguna dengan filter kombinasi lengkap: perusahaan, departemen, divisi, jabatan, level eselon, role Spatie, permission, status aktif, onboarded, foto, pencarian teks pintar lintas nama/email/NIK/WA, sorting, dan paginasi.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `q` | `string` | Tidak | Pencarian pintar lintas nama, email, NIK, WhatsApp, nama jabatan, nama departemen, atau kode departemen. |
| `company_id` | `integer` | Tidak | Filter pengguna di perusahaan tertentu (melalui jabatan/departemen/divisi/manajemen). |
| `department_id` | `integer` | Tidak | Filter pengguna di departemen tertentu. |
| `division_id` | `integer` | Tidak | Filter pengguna di divisi tertentu. |
| `position_id` | `string (UUID)` | Tidak | Filter pengguna di jabatan tertentu. |
| `has_position` | `boolean` | Tidak | true: sudah punya jabatan, false: belum ditugaskan (unassigned). |
| `level` | `integer (1-7)` | Tidak | Filter level eselon jabatan. |
| `level_min / level_max` | `integer (1-7)` | Tidak | Filter rentang level jabatan (contoh: level_min=5&amp;level_max=7). |
| `role` | `string` | Tidak | Filter role Spatie (contoh: super-admin, admin, user, employee). |
| `permission` | `string` | Tidak | Filter pengguna yang memiliki permission tertentu. |
| `is_active` | `boolean` | Tidak | Filter status akun aktif (true) atau nonaktif (false). |
| `onboarded` | `boolean` | Tidak | Filter status kelengkapan onboarding akun. |
| `has_photo` | `boolean` | Tidak | Filter pengguna yang memiliki foto profil avatar. |
| `sort` | `string` | Tidak | Urutkan: name, email, nik, created_at, last_seen_at, is_active (gunakan tanda minus untuk descending, misal: -last_seen_at). |
| `with` | `string` | Tidak | Eager load relasi: position, position.department, position.department.company, position.division, roles, permissions, managedCompanies. |
| `paginate` | `boolean` | Tidak | Set true untuk mengaktifkan paginasi. |
| `per_page` | `integer` | Tidak | Jumlah data per halaman (default: 15). |

**Contoh Request:**
```http
GET /api/users?company_id=1&department_id=2&is_active=true&with=position.department.company,roles&sort=name HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com",
      "whatsapp_number": "081234567890",
      "nik": "6371012304950001",
      "photo_url": "https://s3.tataoptima.com/storage/profile-photos/avatar.jpg",
      "is_active": true,
      "onboarded": true,
      "company_id": 1,
      "department_id": 2,
      "position_id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99"
    }
  ]
}
```

---

#### 2. Detail Profil Pengguna Tunggal
<a id="ep-6-2-get-api-users-user"></a>

- **Metode & URL:** `GET` `/api/users/{user}`
- **Deskripsi:** Mengambil data profil lengkap, jabatan, departemen, dan role Spatie dari satu akun pengguna berdasarkan UUID / ID.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `user` | `string (UUID)` | **Ya** | UUID / ID Akun Pengguna (Path parameter). |
| `with` | `string` | Tidak | Eager load: position, position.department, position.department.company, position.division, roles, permissions, managedCompanies. |

**Contoh Request:**
```http
GET /api/users/019fdfb7-4959-7b3b-9a99-bcfd4e963fa1?with=position.department.company,roles,permissions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
    "name": "Muhammad Ridha Fatahillah",
    "email": "ridho.fatahillah@tataoptima.com",
    "whatsapp_number": "081234567890",
    "nik": "6371012304950001",
    "photo_url": "https://s3.tataoptima.com/storage/profile-photos/avatar.jpg",
    "is_active": true,
    "onboarded": true,
    "company_id": 1,
    "department_id": 2,
    "position_id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
    "permissions": [
      "manage-users",
      "manage-companies",
      "manage-positions"
    ]
  }
}
```

---

### 7. Hierarchical Chaining (Penelusuran Berjenjang Terstruktur)
<a id="grup-7-hierarchical-chaining-penelusuran-berjenjang-terstruktur"></a>

> ℹ️ **Deskripsi Grup:** Endpoint REST bersarang untuk memastikan integritas relasi jalur organisasi (Perusahaan -> Departemen -> Divisi -> Jabatan -> Personel).

#### 1. Departemen di Bawah Perusahaan Tertentu
<a id="ep-7-1-get-api-companies-company-departments-department"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/departments/{department}`
- **Deskripsi:** Mengambil data departemen yang dipastikan terdaftar langsung di bawah perusahaan yang bersangkutan.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan. |
| `department` | `integer` | **Ya** | ID Departemen. |

**Contoh Request:**
```http
GET /api/companies/1/departments/2 HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "code": "IT",
    "name": "Information Technology",
    "company_id": 1
  }
}
```

---

#### 2. Divisi di Departemen Perusahaan Tertentu
<a id="ep-7-2-get-api-companies-company-departments-department-divisions"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/departments/{department}/divisions`
- **Deskripsi:** Mengambil seluruh divisi yang berada di bawah departemen suatu perusahaan.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan. |
| `department` | `integer` | **Ya** | ID Departemen. |

**Contoh Request:**
```http
GET /api/companies/1/departments/2/divisions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "IT-DEV",
      "name": "Software Engineering",
      "department_id": 2
    }
  ]
}
```

---

#### 3. Jabatan di Divisi Departemen Perusahaan Tertentu
<a id="ep-7-3-get-api-companies-company-departments-department-divisions-division-positions"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/departments/{department}/divisions/{division}/positions`
- **Deskripsi:** Mengambil seluruh jabatan pada divisi di dalam departemen perusahaan tertentu.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan. |
| `department` | `integer` | **Ya** | ID Departemen. |
| `division` | `integer` | **Ya** | ID Divisi. |

**Contoh Request:**
```http
GET /api/companies/1/departments/2/divisions/1/positions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
      "name": "Staff Programmer",
      "level": 7,
      "level_label": "Staff / Pelaksana"
    }
  ]
}
```

---

#### 4. Personel di Jalur Penuh (Company -> Dept -> Div -> Pos -> Users)
<a id="ep-7-4-get-api-companies-company-departments-department-divisions-division-positions-position-users"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/departments/{department}/divisions/{division}/positions/{position}/users`
- **Deskripsi:** Mengambil daftar personel yang menduduki jabatan pada hierarki rantai organisasi lengkap.
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan. |
| `department` | `integer` | **Ya** | ID Departemen. |
| `division` | `integer` | **Ya** | ID Divisi. |
| `position` | `string (UUID)` | **Ya** | ID / UUID Jabatan. |

**Contoh Request:**
```http
GET /api/companies/1/departments/2/divisions/1/positions/019fe005-a4b0-706d-90f3-9ecdbb3cfc99/users HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1",
      "name": "Muhammad Ridha Fatahillah",
      "email": "ridho.fatahillah@tataoptima.com",
      "is_active": true
    }
  ]
}
```

---

#### 5. Jabatan Langsung di Departemen (Non-Divisi)
<a id="ep-7-5-get-api-companies-company-departments-department-positions"></a>

- **Metode & URL:** `GET` `/api/companies/{company}/departments/{department}/positions`
- **Deskripsi:** Mengambil jabatan yang berada di departemen perusahaan (termasuk yang tidak memiliki divisi khusus).
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan. |
| `department` | `integer` | **Ya** | ID Departemen. |

**Contoh Request:**
```http
GET /api/companies/1/departments/2/positions HTTP/1.1
Host: gate.appdutamall.com
Authorization: Bearer 1|a8f93bc091d74e8726e8419b4892cfa76e938bf84210d7bc19a8471e9823f6b2
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
      "name": "IT Manager",
      "level": 4,
      "level_label": "Manager"
    }
  ]
}
```

---

### 8. Public & Telemetri & Layanan Realtime
<a id="grup-8-public-telemetri-layanan-realtime"></a>

> ℹ️ **Deskripsi Grup:** Endpoint publik, deteksi jaringan intranet/ekstranet kantor, pemantauan kualitas udara, dan penerima telemetri keamanan OptiGuard.

#### 1. Pengecekan Jaringan Kantor vs Luar Kantor (Office Network Status)
<a id="ep-8-1-post-api-network-status"></a>

- **Metode & URL:** `POST` `/api/network-status`
- **Deskripsi:** Memeriksa apakah IP pengakses berasal dari blok IP jaringan lokal/kantor resmi PT Tata Optima Property atau jaringan internet publik.
- **Autentikasi:** Terbuka (Public Endpoint)

**Parameter Request:** *Tidak membutuhkan payload body khusus.*

**Contoh Request:**
```http
POST /api/network-status HTTP/1.1
Host: gate.appdutamall.com
Content-Type: application/json
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "is_office_network": true,
  "client_ip": "180.252.12.34"
}
```

---

#### 2. Data Kualitas Udara Real-Time (Air Quality Index / ISPU)
<a id="ep-8-2-get-api-air-quality"></a>

- **Metode & URL:** `GET` `/api/air-quality`
- **Deskripsi:** Mengambil data indeks standar pencemar udara (ISPU), konsentrasi PM2.5, suhu, kelembaban, dan status kesehatan udara per kota/lokasi perusahaan.
- **Autentikasi:** Terbuka (Public Endpoint)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company_id` | `integer` | Tidak | ID Perusahaan untuk menentukan kota otomatis. |
| `location` | `string` | Tidak | Nama kota spesifik (contoh: banjarmasin, banjarbaru, jakarta). |
| `refresh` | `boolean` | Tidak | Set true untuk memaksa pembaruan data langsung dari sensor / BMKG dan broadcast via WebSocket Reverb. |

**Contoh Request:**
```http
GET /api/air-quality?location=banjarmasin&refresh=false HTTP/1.1
Host: gate.appdutamall.com
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "data": {
    "aqi": 42,
    "status": "Baik",
    "pm25": 10.2,
    "temperature": 29,
    "humidity": 78,
    "city": "Banjarmasin",
    "updated_at": "2026-09-20T19:45:00+08:00"
  },
  "available_locations": [
    "banjarmasin",
    "banjarbaru",
    "jakarta"
  ]
}
```

---

#### 3. Pelaporan Insiden Keamanan (OptiGuard Telemetry)
<a id="ep-8-3-post-api-optiguard-incident"></a>

- **Metode & URL:** `POST` `/api/optiguard/incident`
- **Deskripsi:** Menerima laporan telemetri pelanggaran keamanan otomatis dari SDK browser pengguna (DevTools terbuka, percobaan kloning sesi, shortcut terlarang, dll.).
- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `type` | `string` | **Ya** | Kategori insiden (contoh: devtools_detected, cookie_hijack_attempt, tab_blur_trigger). |
| `timestamp` | `string (ISO8601)` | Tidak | Waktu kejadian insiden di sisi klien. |
| `payload` | `object` | Tidak | Metadata kontekstual tambahan kejadian. |

**Contoh Request:**
```http
POST /api/optiguard/incident HTTP/1.1
Host: gate.appdutamall.com
Content-Type: application/json
Accept: application/json

{
  "type": "devtools_detected",
  "timestamp": "2026-09-20T19:40:00.000Z",
  "payload": {
    "url": "https://gate.appdutamall.com/admin/financial-report",
    "action": "lockscreen",
    "threat_level": "high"
  }
}
```

**Contoh Respon (JSON):**
```json
{
  "status": "received",
  "incident_id": "7b8d4e9f1a2c3d5e"
}
```

---

### 9. Cascading Dropdowns (Internal Web & Organisasi Helper)
<a id="grup-9-cascading-dropdowns-internal-web-organisasi-helper"></a>

> ℹ️ **Deskripsi Grup:** Endpoint dinamis untuk dropdown bertingkat antarmuka web portal dengan filter hak kelola hierarki organisasi.

#### 1. Daftar Personel Tanpa Jabatan (Available Users)
<a id="ep-9-1-get-api-cascading-available-users"></a>

- **Metode & URL:** `GET` `/api/cascading/available-users`
- **Deskripsi:** Mengambil daftar seluruh personel yang berstatus unassigned (belum ditugaskan ke jabatan manapun).
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:** *Tidak membutuhkan payload body khusus.*

**Contoh Request:**
```http
GET /api/cascading/available-users HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
Accept: application/json
```

**Contoh Respon (JSON):**
```json
[
  {
    "id": "019fe123-4567-7890-abcd-ef1234567890",
    "name": "Siti Rahma",
    "email": "siti.rahma@tataoptima.com"
  }
]
```

---

#### 2. Daftar Departemen Perusahaan (Cascading)
<a id="ep-9-2-get-api-cascading-companies-company-departments"></a>

- **Metode & URL:** `GET` `/api/cascading/companies/{company}/departments`
- **Deskripsi:** Mengambil departemen di bawah naungan perusahaan sesuai batas hak kelola user yang sedang login.
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan. |

**Contoh Request:**
```http
GET /api/cascading/companies/1/departments HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
Accept: application/json
```

**Contoh Respon (JSON):**
```json
[
  {
    "id": 2,
    "company_id": 1,
    "code": "IT",
    "name": "Information Technology"
  }
]
```

---

#### 3. Daftar Divisi Mandiri Perusahaan (Cascading)
<a id="ep-9-3-get-api-cascading-companies-company-direct-divisions"></a>

- **Metode & URL:** `GET` `/api/cascading/companies/{company}/direct-divisions`
- **Deskripsi:** Mengambil divisi yang berkedudukan langsung di bawah perusahaan/GM.
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `company` | `integer` | **Ya** | ID Perusahaan. |

**Contoh Request:**
```http
GET /api/cascading/companies/1/direct-divisions HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
Accept: application/json
```

**Contoh Respon (JSON):**
```json
[
  {
    "id": 1,
    "company_id": 1,
    "code": "SEC-AST",
    "name": "Sekretariat & Asset Management",
    "positions": []
  }
]
```

---

#### 4. Daftar Divisi Departemen (Cascading)
<a id="ep-9-4-get-api-cascading-departments-department-divisions"></a>

- **Metode & URL:** `GET` `/api/cascading/departments/{department}/divisions`
- **Deskripsi:** Mengambil divisi di bawah departemen tertentu untuk dropdown dinamis.
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department` | `integer` | **Ya** | ID Departemen. |

**Contoh Request:**
```http
GET /api/cascading/departments/2/divisions HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
Accept: application/json
```

**Contoh Respon (JSON):**
```json
[
  {
    "id": 1,
    "department_id": 2,
    "code": "IT-DEV",
    "name": "Software Engineering"
  }
]
```

---

#### 5. Daftar Jabatan Departemen (Cascading)
<a id="ep-9-5-get-api-cascading-departments-department-positions"></a>

- **Metode & URL:** `GET` `/api/cascading/departments/{department}/positions`
- **Deskripsi:** Mengambil jabatan di bawah departemen dengan opsi filter division_id.
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `department` | `integer` | **Ya** | ID Departemen. |
| `division_id` | `string` | Tidak | Filter ID Divisi (isi &quot;none&quot; untuk hanya mengambil jabatan tanpa divisi). |

**Contoh Request:**
```http
GET /api/cascading/departments/2/positions?division_id=none HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
Accept: application/json
```

**Contoh Respon (JSON):**
```json
[
  {
    "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
    "department_id": 2,
    "division_id": null,
    "name": "IT Manager",
    "level": 4
  }
]
```

---

#### 6. Detail Lengkap Jabatan & Hak Akses Klien yang Diwarisi
<a id="ep-9-6-get-api-cascading-positions-position-details"></a>

- **Metode & URL:** `GET` `/api/cascading/positions/{position}/details`
- **Deskripsi:** Mengambil detail jabatan, personel aktif, serta daftar klien aplikasi yang diwarisi dari level jabatan, departemen, dan perusahaan.
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `position` | `string (UUID)` | **Ya** | ID / UUID Jabatan. |

**Contoh Request:**
```http
GET /api/cascading/positions/019fe005-a4b0-706d-90f3-9ecdbb3cfc99/details HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
Accept: application/json
```

**Contoh Respon (JSON):**
```json
{
  "position": {
    "id": "019fe005-a4b0-706d-90f3-9ecdbb3cfc99",
    "name": "IT Manager",
    "users": [],
    "clients": []
  },
  "department_clients": [],
  "company_clients": []
}
```

---

#### 7. Penugasan Personel ke Jabatan (Assign User)
<a id="ep-9-7-post-api-cascading-positions-position-assign-user"></a>

- **Metode & URL:** `POST` `/api/cascading/positions/{position}/assign-user`
- **Deskripsi:** Menugaskan personel tertentu ke suatu posisi jabatan.
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `position` | `string (UUID)` | **Ya** | ID / UUID Jabatan. |
| `user_id` | `string (UUID)` | **Ya** | UUID Pengguna yang akan ditugaskan. |

**Contoh Request:**
```http
POST /api/cascading/positions/019fe005-a4b0-706d-90f3-9ecdbb3cfc99/assign-user HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
Content-Type: application/json

{
  "user_id": "019fdfb7-4959-7b3b-9a99-bcfd4e963fa1"
}
```

**Contoh Respon (JSON):**
```json
HTTP/1.1 302 Found
Location: /positions

(Redirect dengan session flash success)
```

---

#### 8. Pelepasan Personel dari Jabatan (Remove Position)
<a id="ep-9-8-post-api-cascading-users-user-remove-position"></a>

- **Metode & URL:** `POST` `/api/cascading/users/{user}/remove-position`
- **Deskripsi:** Mengeluarkan personel dari jabatan saat ini sehingga berstatus unassigned.
- **Autentikasi:** Sesi Web Internal (`Cookie: ...` + `X-CSRF-TOKEN`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `user` | `string (UUID)` | **Ya** | UUID Pengguna. |

**Contoh Request:**
```http
POST /api/cascading/users/019fdfb7-4959-7b3b-9a99-bcfd4e963fa1/remove-position HTTP/1.1
Host: gate.appdutamall.com
Cookie: portal_session=...
```

**Contoh Respon (JSON):**
```json
HTTP/1.1 302 Found
Location: /positions

(Redirect dengan session flash success)
```

---

### 10. SAML 2.0 Identity Provider (OptiGate)
<a id="grup-10-saml-2-0-identity-provider-optigate"></a>

> ℹ️ **Deskripsi Grup:** Endpoint protokol standar federasi identitas SAML 2.0 untuk mengintegrasikan aplikasi pihak ketiga atau klien internal (PHP Native, Laravel, Nextcloud, Google Workspace, AWS, dll.) sebagai Service Provider (SP) ke OptiGate IdP (gate.appdutamall.com - PT Tata Optima Property).

#### 1. IdP Metadata XML
<a id="ep-10-1-get-saml-metadata"></a>

- **Metode & URL:** `GET` `/saml/metadata`
- **Deskripsi:** Mengembalikan dokumen SAML 2.0 Metadata XML resmi OptiGate yang memuat Entity ID, KeyDescriptor (sertifikat publik X.509), SingleSignOnService, dan SingleLogoutService endpoints.
- **Autentikasi:** Publik / Protokol SAML 2.0 Assertion

**Parameter Request:** *Tidak membutuhkan payload body khusus.*

**Contoh Request:**
```http
GET /saml/metadata HTTP/1.1
Host: gate.appdutamall.com
Accept: application/samlmetadata+xml, application/xml
```

**Contoh Respon (JSON):**
```json
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="https://gate.appdutamall.com/saml/metadata">
    <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:KeyDescriptor use="signing">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate>MIIBxTCCAW6gAwIBAgIU...</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://gate.appdutamall.com/saml/sso"/>
        <md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://gate.appdutamall.com/saml/slo"/>
        <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
    </md:IDPSSODescriptor>
</md:EntityDescriptor>
```

---

#### 2. Unduh Sertifikat Publik X.509 IdP (Download .crt)
<a id="ep-10-2-get-saml-certificate"></a>

- **Metode & URL:** `GET` `/saml/certificate`
- **Deskripsi:** Mengunduh sertifikat publik X.509 (format PEM .crt) yang digunakan aplikasi klien (SP) untuk memverifikasi tanda tangan digital SAML assertion dari OptiGate.
- **Autentikasi:** Publik / Protokol SAML 2.0 Assertion

**Parameter Request:** *Tidak membutuhkan payload body khusus.*

**Contoh Request:**
```http
GET /saml/certificate HTTP/1.1
Host: gate.appdutamall.com
```

**Contoh Respon (JSON):**
```json
-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIU...
... (sertifikat publik X.509 OptiGate) ...
-----END CERTIFICATE-----
```

---

#### 3. Single Sign-On (SSO) Service
<a id="ep-10-3-get-post-saml-sso"></a>

- **Metode & URL:** `GET / POST` `/saml/sso`
- **Deskripsi:** Endpoint SP-Initiated SSO. Menerima query parameter SAMLRequest (deflated base64), memverifikasi autentikasi user (misal: Muhammad Ridha Fatahillah - PT Tata Optima Property), memeriksa izin akses aplikasi, dan mengembalikan form auto-POST berisi signed SAMLResponse ke ACS URL klien.
- **Autentikasi:** Publik / Protokol SAML 2.0 Assertion

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `SAMLRequest` | `string (base64)` | **Ya** | AuthnRequest XML dari Service Provider (SP). |
| `RelayState` | `string` | Tidak | State/URL tujuan redirect di sisi SP setelah login berhasil. |

**Contoh Request:**
```http
GET /saml/sso?SAMLRequest=fZFfa8IwFMXf%2FRZl73...&RelayState=https%3A%2F%2Fcloud.tataoptima.com%2Fdashboard HTTP/1.1
Host: gate.appdutamall.com
```

**Contoh Respon (JSON):**
```json
<!-- Auto-submitting HTML form targeting SP ACS URL -->
<form method="post" action="https://cloud.tataoptima.com/saml/acs">
    <input type="hidden" name="SAMLResponse" value="PHNhbWxwOlJlc3BvbnNlIHhtbG5z..." />
    <input type="hidden" name="RelayState" value="https://cloud.tataoptima.com/dashboard" />
</form>
```

---

#### 4. Single Logout (SLO) Service
<a id="ep-10-4-get-post-saml-slo"></a>

- **Metode & URL:** `GET / POST` `/saml/slo`
- **Deskripsi:** Endpoint Single Logout dua arah. Menghancurkan sesi pengguna di OptiGate saat SP mengirim LogoutRequest dan mengirimkan konfirmasi LogoutResponse.
- **Autentikasi:** Publik / Protokol SAML 2.0 Assertion

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `SAMLRequest` | `string (base64)` | Tidak | LogoutRequest XML jika logout diinisiasi oleh SP. |
| `SAMLResponse` | `string (base64)` | Tidak | LogoutResponse XML jika menanggapi logout dari IdP. |
| `RelayState` | `string` | Tidak | State/URL redirect setelah logout selesai. |

**Contoh Request:**
```http
GET /saml/slo?SAMLRequest=fZJbb4IwFMXf%2BRZl... HTTP/1.1
Host: gate.appdutamall.com
```

**Contoh Respon (JSON):**
```json
<!-- Form auto-POST LogoutResponse ke SLO URL klien -->
<form method="post" action="https://cloud.tataoptima.com/saml/sls">
    <input type="hidden" name="SAMLResponse" value="PHNhbWxwOkxvZ291dFJlc3BvbnNl..." />
</form>
```

---

### 11. Notifikasi Terpusat (Centralized Notification Hub)
<a id="grup-11-notifikasi-terpusat-centralized-notification-hub"></a>

> ℹ️ **Deskripsi Grup:** Endpoint bagi seluruh aplikasi ekosistem internal untuk mengirimkan notifikasi instan secara real-time ke pengguna Portal DM, dengan multi-targeting cerdas dan sinkronisasi approval (resolve).

#### 1. Kirim Notifikasi Real-Time (Ingestion)
<a id="ep-11-1-post-api-v1-notifications-send"></a>

- **Metode & URL:** `POST` `/api/v1/notifications/send`
- **Deskripsi:** Mengirim notifikasi ke satu pengguna spesifik atau grup (Jabatan Level 1-7, Departemen, Divisi, Perusahaan/PT, Role, atau Seluruh Pengguna). Notifikasi langsung muncul via WebSockets dan Web Push.
- **Autentikasi:** Wajib (`X-Client-ID` + `X-Client-Secret` ATAU `Authorization: Bearer <token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `target_type` | `string` | **Ya** | Target: user, position, department, division, company, role, all. |
| `target_id` | `string` | Tidak | Email / NIK / UUID / Level (1-7) / Kode Dept / PT (Wajib kecuali target_type=all). |
| `title` | `string` | **Ya** | Judul notifikasi (maksimal 255 karakter). |
| `body` | `string` | **Ya** | Isi pesan / rincian notifikasi. |
| `type` | `string` | Tidak | Kategori: info (default), approval, warning, success, error. |
| `priority` | `string` | Tidak | Prioritas: normal (default), low, high, urgent. |
| `action_url` | `string` | Tidak | URL aksi tujuan saat notifikasi diklik. |
| `reference_id` | `string` | Tidak | ID referensi unik dokumen aplikasi asal (misal LEAVE-2026-089) untuk auto-resolve. |
| `data` | `object` | Tidak | Data JSON tambahan opsional. |

**Contoh Request:**
```http
POST /api/v1/notifications/send HTTP/1.1
Host: gate.appdutamall.com
Content-Type: application/json
X-Client-ID: 019246bf-d7e1-70e2-881a-c75c86c1dd5e
X-Client-Secret: secret_klien_anda_disini

{
  "target_type": "user",
  "target_id": "budi.santoso@appdutamall.com",
  "title": "Pengajuan Cuti Menunggu Approval",
  "body": "Siti Rahma mengajukan cuti tahunan selama 3 hari.",
  "type": "approval",
  "priority": "high",
  "reference_id": "LEAVE-2026-104",
  "action_url": "https://leave.appdutamall.com/approvals/104"
}
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "message": "Notifikasi berhasil dikirim.",
  "delivered_count": 1,
  "target_type": "user",
  "notification_ids": [
    "019246cd-0001-7000-8000-000000000001"
  ]
}
```

---

#### 2. Selesaikan / Hilangkan Notifikasi Approval (Auto-Resolve)
<a id="ep-11-2-post-api-v1-notifications-resolve"></a>

- **Metode & URL:** `POST` `/api/v1/notifications/resolve`
- **Deskripsi:** Menandai notifikasi telah selesai diproses di aplikasi asal. Notifikasi pada Portal DM akan otomatis ditandai resolved dan hilang dari daftar pending pengguna secara instan tanpa perlu refresh.
- **Autentikasi:** Wajib (`X-Client-ID` + `X-Client-Secret` ATAU `Authorization: Bearer <token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `reference_id` | `string` | **Ya** | ID referensi dokumen asal yang sama saat /send dipanggil. |
| `user_id` | `string` | Tidak | Opsional: Selesaikan hanya untuk user tertentu. |

**Contoh Request:**
```http
POST /api/v1/notifications/resolve HTTP/1.1
Host: gate.appdutamall.com
Content-Type: application/json
X-Client-ID: 019246bf-d7e1-70e2-881a-c75c86c1dd5e
X-Client-Secret: secret_klien_anda_disini

{
  "reference_id": "LEAVE-2026-104"
}
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "message": "Notifikasi berhasil diselesaikan / disinkronkan.",
  "resolved_count": 1
}
```

---

#### 3. Hapus Notifikasi Berdasarkan Reference ID
<a id="ep-11-3-delete-api-v1-notifications-by-reference"></a>

- **Metode & URL:** `DELETE` `/api/v1/notifications/by-reference`
- **Deskripsi:** Membatalkan atau menghapus notifikasi yang telah terkirim dari database dan antarmuka pengguna.
- **Autentikasi:** Wajib (`X-Client-ID` + `X-Client-Secret` ATAU `Authorization: Bearer <token>`)

**Parameter Request:**

| Parameter | Tipe Data | Wajib? | Keterangan |
| :--- | :--- | :---: | :--- |
| `reference_id` | `string` | **Ya** | ID referensi notifikasi yang akan dihapus. |

**Contoh Request:**
```http
DELETE /api/v1/notifications/by-reference?reference_id=LEAVE-2026-104 HTTP/1.1
Host: gate.appdutamall.com
X-Client-ID: 019246bf-d7e1-70e2-881a-c75c86c1dd5e
X-Client-Secret: secret_klien_anda_disini
```

**Contoh Respon (JSON):**
```json
{
  "success": true,
  "message": "Notifikasi berhasil dihapus.",
  "deleted_count": 1
}
```

---

## 4. Contoh Kode Integrasi Multi-Bahasa

Berikut contoh penerapan pemanggilan API Portal DM pada berbagai bahasa pemrograman populer:

### 4.1 PHP (Guzzle HTTP & cURL)

#### Contoh A: Mengirim Notifikasi Real-Time (Notification Ingestion)
```php
<?php
use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'https://gate.appdutamall.com']);

$response = $client->post('/api/v1/notifications/send', [
    'headers' => [
        'X-Client-ID'     => 'cuti-online-app',
        'X-Client-Secret' => '4f8b9a1e-8765-4321-abcd-ef0123456789',
        'Accept'          => 'application/json',
        'Content-Type'    => 'application/json',
    ],
    'json' => [
        'target_type'      => 'user',
        'target_id'        => '019fc128-dd08-73e7-af77-38a9409d5bb0', // UUID Pengguna
        'title'            => 'Pengajuan Cuti Tahunan',
        'body'             => 'Staf IT mengajukan cuti 3 hari menunggu persetujuan Anda.',
        'type'             => 'approval',
        'priority'         => 'high',
        'action_url'       => 'https://cuti.tataoptima.com/approvals/9021',
        'reference_id'     => 'LEAVE-2026-9021',
        'resolve_previous' => true,
    ]
]);

$body = json_decode($response->getBody(), true);
echo 'Status Pengiriman: ' . $body['message'];
```

#### Contoh B: Mengambil Data Hierarki Perusahaan (REST API Bearer Token)
```php
<?php
use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'https://gate.appdutamall.com']);

$response = $client->get('/api/companies/1/tree', [
    'headers' => [
        'Authorization' => 'Bearer 1|your_personal_access_token_here',
        'Accept'        => 'application/json',
    ],
]);

$tree = json_decode($response->getBody(), true);
print_r($tree['data']);
```

### 4.2 JavaScript / TypeScript (Fetch & Axios)

#### Menggunakan Axios (Node.js atau Frontend SPA)
```typescript
import axios from 'axios';

const portalApi = axios.create({
  baseURL: 'https://gate.appdutamall.com',
  headers: {
    'Authorization': 'Bearer 1|your_personal_access_token_here',
    'Accept': 'application/json',
  }
});

// Ambil profil pengguna login
async function getMyProfile() {
  try {
    const response = await portalApi.get('/api/user');
    console.log('User Profile:', response.data.data);
  } catch (error) {
    console.error('Gagal mengambil profil:', error.response?.data || error.message);
  }
}

// Auto-resolve notifikasi approval
async function resolveNotification(referenceId: string) {
  await axios.post('https://gate.appdutamall.com/api/v1/notifications/resolve', {
    reference_id: referenceId
  }, {
    headers: {
      'X-Client-ID': 'cuti-online-app',
      'X-Client-Secret': '4f8b9a1e-8765-4321-abcd-ef0123456789',
    }
  });
}
```

### 4.3 Python (Requests)

```python
import requests

BASE_URL = 'https://gate.appdutamall.com'
TOKEN = '1|your_personal_access_token_here'

headers = {
    'Authorization': f'Bearer {TOKEN}',
    'Accept': 'application/json'
}

# Ambil daftar semua departemen dengan jumlah personel
params = {
    'with_count': 'users,positions',
    'company_id': 1
}

res = requests.get(f'{BASE_URL}/api/departments', headers=headers, params=params)
if res.status_code == 200:
    departments = res.json().get('data', [])
    for dept in departments:
        print(f"[{dept['code']}] {dept['name']} - {dept.get('users_count', 0)} personel")
else:
    print("Error:", res.status_code, res.text)
```

### 4.4 Go (net/http)

```go
package main

import (
    "bytes"
    "encoding/json"
    "fmt"
    "net/http"
)

func main() {
    payload := map[string]interface{}{
        "target_type": "all",
        "title":       "Pengumuman Pemeliharaan Server",
        "body":        "Sistem akan offline sementara malam ini pkl 23.00 WIB.",
        "type":        "warning",
    }
    jsonBytes, _ := json.Marshal(payload)

    req, _ := http.NewRequest("POST", "https://gate.appdutamall.com/api/v1/notifications/send", bytes.NewBuffer(jsonBytes))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("X-Client-ID", "infra-alert-bot")
    req.Header.Set("X-Client-Secret", "secret-value-token")

    client := &http.Client{}
    resp, err := client.Do(req)
    if err != nil {
        panic(err)
    }
    defer resp.Body.Close()

    fmt.Println("Response Status:", resp.Status)
}
```

## 5. Penanganan Masalah & FAQ (Troubleshooting)

### Q1: Mengapa request saya menerima respon `401 Unauthorized`?
- **Penyebab:** Header `Authorization: Bearer <token>` tidak disertakan, token kadaluarsa, atau format token salah.
- **Solusi:** Pastikan token diawali kata `Bearer ` dengan spasi tunggal. Periksa apakah Personal Access Token belum dicabut dari tabel `oauth_access_tokens`.

### Q2: Mengapa Notifikasi Hub menolak request dengan status `401 / 403`?
- **Penyebab:** Pasangan `X-Client-ID` dan `X-Client-Secret` tidak cocok dengan klien yang terdaftar di database `oauth_clients`.
- **Solusi:** Buka menu **Master Data > Klien OAuth / SAML** di dashboard Portal DM, verifikasi Client ID dan salin Client Secret terbaru.

### Q3: Bagaimana cara melakukan filtering multi-kolom di endpoint REST?
- Gunakan query parameters standar seperti `company_id=1&level=7&vacant_only=true`.
- Untuk menyertakan data relasi terhubung sekaligus, gunakan parameter `with=department,users,positions` atau hitungan relasi dengan `with_count=users,departments`.

### Q4: Apakah format ID di seluruh entitas organisasi menggunakan Integer atau UUID?
- **Perusahaan, Departemen, Divisi:** Menggunakan format integer ID auto-increment (misal `1`, `2`, `3`).
- **Jabatan (Position) & Personel (User):** Menggunakan format **UUID v7** (misal `019fdfb7-4959-7b3b-9a99-bcfd4e963fa1`). Sangat disarankan untuk selalu mereferensikan UUID pada endpoint penugasan jabatan maupun target notifikasi.

---

**OptiGate Enterprise Platform** • *PT Tata Optima Property (Duta Mall Group)*  
Untuk pertanyaan integrasi lebih lanjut, hubungi Departemen IT di `it@tataoptima.com`.
