# PRD: Work Management System (WMS)

**Versi:** 1.0 Draft **Stack:** Laravel + Inertia React + MySQL, RBAC via Spatie Laravel Permission, multi-cabang via package `tenancyforlaravel` **Status:** Draft untuk review — beberapa item ditandai sebagai Open Question dan BELUM boleh dianggap final

---

## 1. Problem Statement

Saat ini pengelolaan pekerjaan internal (perbaikan, pemeliharaan, permintaan bantuan antar-department) berjalan tanpa sistem terpusat. Akibatnya:

- **Requester** (karyawan yang butuh bantuan department lain) tidak punya cara terstruktur untuk mengajukan pekerjaan dan melacak statusnya — permintaan kemungkinan besar berjalan via chat/lisan sehingga mudah hilang atau terlupa.
- **HOD (Head of Department)** tidak punya visibilitas terpusat atas beban kerja timnya, sehingga assignment pekerjaan dan keputusan jadwal vs eksekusi langsung dilakukan tanpa data yang jelas.
- **Karyawan lapangan** menerima pekerjaan tanpa notifikasi sistematis dan tanpa cara standar untuk submit hasil pekerjaan/bukti penyelesaian.
- **Manajemen (DGM/GM)** tidak punya mekanisme otomatis untuk tahu pekerjaan mana yang terlambat sampai eskalasi manual terjadi — risiko keterlambatan pekerjaan kritikal tidak terdeteksi dini.
- Tidak ada audit trail: siapa assign siapa, kapan revisi diminta, kapan deadline di-extend dan oleh siapa disetujui.

Yang dirugikan: seluruh rantai kerja lintas department (Requester → HOD → karyawan lapangan → manajemen), karena tidak ada single source of truth untuk status pekerjaan dan tidak ada eskalasi otomatis saat SLA terlanggar.

---

## 2. Target User — 2 Persona

**Persona 1: Budi — Head of Department (HOD)**

- Menerima Work Order masuk dari department lain maupun dari timnya sendiri.
- Harus memutuskan: eksekusi langsung atau dijadwalkan (khusus WO normal), lalu assign ke karyawan.
- Perlu memantau WO yang mendekati/melewati deadline timnya, approve/reject extend, verifikasi hasil pekerjaan sebelum WO ditutup.
- Pain point saat ini: tidak tahu beban kerja real-time tim, approval extend tidak tercatat.

**Persona 2: Sari — Karyawan Lapangan (Field Worker)**

- Menerima notifikasi saat di-assign ke WO/Daily Work.
- Mengerjakan pekerjaan, submit hasil (foto sebelum/sesudah, catatan) untuk diverifikasi HOD.
- Punya Daily Work rutin (template harian) di luar WO yang di-assign.
- Pain point saat ini: tidak ada daftar tugas harian terpusat, tidak jelas prioritas antara WO baru vs Daily Work rutin.

_(Role lain yang ikut terdampak sistem: Requester (siapapun pengaju WO), Team Leader (penerima notifikasi telat tahap 1), DGM/GM (eskalasi tahap akhir), **Super Admin** (pengelola konfigurasi sistem/RBAC), **Direksi** (kemungkinan visibility read-only di level tertinggi) — belum dikonfirmasi apakah ini persona terpisah dengan kebutuhan UI berbeda atau cukup di-cover oleh 2 persona di atas, dan belum dikonfirmasi hak akses spesifik Super Admin vs Direksi. **Open Question.**)_

---

## 3. Goals & Non-Goals

**Goals (MVP):**

- Satu sistem terpusat untuk membuat, assign, menjadwalkan, mengerjakan, dan menutup Work Order — termasuk lintas department.
- Eskalasi keterlambatan otomatis dan berjenjang (Team Leader → HOD → DGM/GM) tanpa intervensi manual.
- Setiap karyawan lapangan punya visibilitas Daily Work (template rutin + tugas tambahan) terpisah dari WO.
- Notifikasi realtime untuk setiap perubahan status yang relevan ke role terkait.
- Kontrol akses berbasis role/permission (Spatie) sehingga setiap role hanya melihat/melakukan aksi yang relevan dengan wewenangnya.
- Data pekerjaan (Work Data Management) tercatat rapi sebagai riwayat/histori tiap WO.

**Non-Goals (MVP):**

- Modul Inventory, Kelompok Barang, dan Surat Masuk/Keluar — eksplisit **tidak** masuk produk ini.
- Management Tenant (CRUD penyewa tempat) — ditunda ke v2.
- Integrasi eksternal (WhatsApp/email gateway, sistem absensi, dsb) — tidak ada di scope MVP karena belum ada batasan teknis yang dikonfirmasi (lihat Open Question §10).
- Analytics/reporting dashboard lanjutan (selain kebutuhan minimal untuk memantau SLA) — tidak dibahas eksplisit oleh user, jadi tidak diasumsikan masuk MVP.

---

## 4. User Stories

**Work Order — Requester**

- Sebagai Requester, saya ingin membuat Work Order dengan memilih kategori (Normal / Urgent by Accident / Urgent Request by Owner), supaya jenis penanganan dan jalur approval yang sesuai otomatis berlaku.
- Sebagai Requester, saya ingin melihat status WO yang saya buat secara realtime, supaya saya tahu progresnya tanpa harus bertanya manual.

**Work Order — HOD**

- Sebagai HOD, saya ingin menerima notifikasi saat WO baru masuk ke department saya, supaya saya bisa segera memutuskan eksekusi langsung atau dijadwalkan.
- Sebagai HOD, saya ingin assign karyawan ke WO, supaya pekerjaan jelas penanggung jawabnya.
- Sebagai HOD, saya ingin memverifikasi hasil pekerjaan yang disubmit karyawan dan meminta revisi bila perlu, supaya kualitas pekerjaan terjaga sebelum WO ditutup.
- Sebagai HOD, saya ingin mengajukan extend deadline WO yang telat (maks 3 hari tambahan), supaya keterlambatan wajar bisa ditangani sesuai proses approval berjenjang.

**Work Order — Karyawan Lapangan**

- Sebagai karyawan lapangan, saya ingin menerima notifikasi saat di-assign ke WO baru, supaya saya segera tahu ada pekerjaan.
- Sebagai karyawan lapangan, saya ingin submit hasil pekerjaan (foto, catatan) langsung dari sistem, supaya HOD bisa memverifikasi tanpa proses manual.

**Work Order Terjadwal**

- Sebagai HOD, saya ingin menjadwalkan WO normal (bukan urgent by accident) ke tanggal tertentu, supaya beban kerja tim bisa diatur.
- Sebagai HOD, saya ingin mengajukan extend Work Schedule dengan approval DGM/GM, supaya perubahan jadwal tetap terkontrol di level manajemen.

**Lintas Department**

- Sebagai Requester, saya ingin memilih department tujuan saat membuat WO, supaya permintaan bantuan lintas department langsung sampai ke HOD yang tepat.

**Daily Work Management**

- Sebagai karyawan, saya ingin melihat daftar tugas harian rutin saya (template) beserta tugas tambahan yang di-assign HOD, supaya saya tahu prioritas kerja hari itu.
- Sebagai HOD, saya ingin menambahkan tugas harian di luar template rutin ke karyawan tertentu, supaya kebutuhan mendadak tetap tercatat sebagai bagian dari beban kerja harian.

**Eskalasi & Notifikasi**

- Sebagai Team Leader, saya ingin menerima notifikasi saat WO tim saya telat 3 hari kerja, supaya saya bisa menindaklanjuti sebelum eskalasi naik ke HOD.
- Sebagai HOD, saya ingin menerima notifikasi saat WO telat 5 hari kerja, supaya saya bisa mengambil tindakan sebelum eskalasi ke DGM/GM.
- Sebagai DGM/GM, saya ingin menerima notifikasi saat WO telat 6 hari kerja, supaya saya bisa melakukan intervensi di level tertinggi.

**RBAC**

- Sebagai Admin sistem, saya ingin mengatur role dan permission per user, supaya setiap role hanya bisa melakukan aksi sesuai wewenangnya.

---

## 5. Daftar Fitur — MVP / v2 / Nanti

| Fitur                                                     | Kategori                                            |
| --------------------------------------------------------- | --------------------------------------------------- |
| Work Order (Create/Update/Review/Assign)                  | **MVP**                                             |
| Work Order Terjadwal (planning + eksekusi)                | **MVP**                                             |
| Work Data Management (histori/riwayat pekerjaan per WO)   | **MVP**                                             |
| Lintas Department Workorder                               | **MVP**                                             |
| Daily Work Management (template rutin + tugas tambahan)   | **MVP**                                             |
| RBAC (Spatie)                                             | **MVP**                                             |
| Realtime Notification                                     | **MVP**                                             |
| Eskalasi deadline berjenjang (Team Leader → HOD → DGM/GM) | **MVP** (bagian dari Work Order)                    |
| Extend deadline WO telat (approval berjenjang)            | **MVP**                                             |
| Extend Work Schedule (approval DGM/GM)                    | **MVP**                                             |
| Management Tenant (CRUD penyewa tempat)                   | **v2**                                              |
| Integrasi WA/email notification                           | **Nanti** (belum dikonfirmasi, lihat Open Question) |
| Reporting/analytics dashboard lanjutan                    | **Nanti**                                           |
| Inventory, Kelompok Barang, Surat Masuk/Keluar            | **Tidak masuk produk ini**                          |

---

## 6. Functional Requirements — Detail per Fitur MVP

### 6.1 Work Order (Create/Update/Review/Assign)

- FR-1.1: Requester membuat WO dengan field minimal: department tujuan, kategori (Normal / Urgent by Accident / Urgent Request by Owner), jenis pekerjaan/kerusakan, prioritas, jumlah personel dibutuhkan, deskripsi, lampiran gambar.
- FR-1.2: Jika kategori = **Urgent by Accident** → sistem **tidak menampilkan opsi jadwal**, WO wajib masuk status "harus dieksekusi langsung".
- FR-1.3: Jika kategori = **Urgent Request by Owner** → sistem mengizinkan HOD memilih eksekusi langsung ATAU dijadwalkan.
- FR-1.4: Jika kategori = **Normal** → HOD memilih eksekusi langsung atau dijadwalkan.
- FR-1.5: Setelah WO tersimpan, sistem mengirim notifikasi realtime ke HOD department tujuan.
- FR-1.6: HOD melakukan assign 1 atau lebih karyawan ke WO; sistem mengirim notifikasi realtime ke karyawan yang di-assign.
- FR-1.7: Karyawan submit hasil pekerjaan (field: catatan hasil, foto/lampiran) → status WO berubah ke "menunggu verifikasi HOD".
- FR-1.8: HOD dapat approve (WO selesai/closed) atau reject dengan catatan revisi (status kembali ke karyawan, tidak reset deadline).
- FR-1.9: Setiap perubahan status WO tercatat di histori (siapa, kapan, aksi apa) — mendukung §6.3 Work Data Management.

### 6.2 Work Order Terjadwal + Eskalasi Deadline

- FR-2.1: Deadline otomatis dihitung dari **tanggal assign**, bukan tanggal submit WO. Minimal 3 hari kerja, maksimal 6 hari kerja (nilai pasti per jenis WO — lihat Open Question §10).
- FR-2.2: Jika pekerjaan berstatus "on progress" dan telat melewati deadline:
    - Hari telat ke-3 → notifikasi ke **Team Leader** (jika ada tim leader di department tsb).
    - Hari telat ke-5 → notifikasi ke **HOD**.
    - Hari telat ke-6 → notifikasi ke **DGM/GM**.
- FR-2.3: WO yang telat dapat di-extend maksimal 3 hari, dengan approval berjenjang: Team Leader (jika ada) → HOD; jika department tidak punya Team Leader, langsung ke HOD.
- FR-2.4: Work Schedule (jadwal WO normal) dapat di-extend oleh HOD, tetapi wajib approval DGM/GM — **alur ini terpisah** dari FR-2.3 (extend WO telat).
- FR-2.5: Sistem mencatat siapa yang approve/reject setiap pengajuan extend beserta timestamp dan alasan.

### 6.3 Work Data Management

- FR-3.1: Setiap WO yang closed menghasilkan satu record Work Data berisi: no kerja, jam pengerjaan, department, status akhir, gambar sebelum/sesudah, prediksi penyebab, hasil kesimpulan, saran solusi, tindakan yang diambil.
- FR-3.2: Work Data dapat dicari/difilter berdasarkan department, rentang tanggal, status.
- FR-3.3: Data ini menjadi sumber histori/audit trail — tidak bisa diedit setelah WO closed, hanya bisa dilihat (append-only setelah closing; detail alur revisi post-closing — **Open Question**).

### 6.4 Lintas Department Workorder

- FR-4.1: Saat membuat WO, Requester wajib memilih department tujuan dari daftar department aktif.
- FR-4.2: WO lintas department tunduk pada FR-1.x s.d. FR-2.x yang sama — tidak ada alur berbeda selain routing ke HOD department tujuan.

### 6.5 Daily Work Management

- FR-5.1: Setiap karyawan punya template tugas harian rutin (recurring), didefinisikan per karyawan atau per jabatan/role (mekanisme pasti template — **Open Question**).
- FR-5.2: HOD dapat menambahkan tugas tambahan di luar template untuk karyawan tertentu pada tanggal tertentu.
- FR-5.3: Karyawan melihat gabungan (template rutin + tugas tambahan) sebagai satu daftar Daily Work per hari.
- FR-5.4: Setiap item Daily Work punya status pekerjaan (belum/proses/selesai) dan lokasi pekerjaan.

### 6.6 RBAC (Spatie)

- FR-6.1: Role minimal yang harus didukung: Requester, Karyawan Lapangan, Team Leader, HOD, DGM/GM, **Super Admin**, **Direksi**. (Requester bisa jadi bukan role eksklusif — setiap user berpotensi jadi Requester — **Open Question**. Hak akses persis Super Admin — apakah setara konfigurasi RBAC/master data sistem — vs Direksi — apakah hanya read-only monitoring lintas department — juga **Open Question**, lihat §10.)
- FR-6.2: Setiap permission (create WO, assign, verify, approve extend, dst.) di-assign ke role via Spatie, dapat dikonfigurasi Admin tanpa deploy ulang.
- FR-6.3: Middleware memastikan user hanya bisa akses route/aksi sesuai permission-nya; percobaan akses tanpa izin menghasilkan 403 dan tercatat di log.

### 6.7 Realtime Notification

- FR-7.1: Event yang memicu notifikasi realtime: WO baru masuk, WO di-assign, hasil kerja disubmit, hasil kerja direvisi, WO telat (3/5/6 hari), pengajuan extend, approval/reject extend, Daily Work baru ditambahkan.
- FR-7.2: Notifikasi tampil in-app (bell icon / toast) minimal; channel tambahan (WA/email) — **Open Question**, lihat §3 Non-Goals.
- FR-7.3: Notifikasi tersimpan dan bisa ditandai sudah dibaca; user bisa melihat riwayat notifikasi.

---

## 7. Sketsa Data Model (Entitas + Field Kunci)

_Dari ERD terlampir, dengan tb_inventory, tb_kelompok_barang, tb_surat_masuk, tb_surat_keluar dikeluarkan sesuai instruksi. Nama field di bawah adalah pembacaan terbaik dari ERD — field yang tidak terbaca jelas ditandai (?) dan harus diverifikasi ulang oleh tim engineering sebelum development._

**tb_user**

- fld_id_user (PK), fld_id_karyawan (FK), fld_username, fld_password, fld_hak_akses, fld_status, fld_login_status, fld_tanggal_login

**tb_karyawan**

- fld_id_karyawan (PK), fld_nik, fld_nama, fld_nama_alias, fld_gender, fld_alamat, fld_no_ktp, fld_telp, fld_jabatan (FK?), fld_divisi (FK), fld_departmen (FK), fld_status_karyawan, field tanda tangan digital (?)

**tb_divisi**

- fld_id_divisi (PK), fld_nama_divisi, fld_ext_tlp

**tb_department**

- fld_id_department (PK), fld_kode_department, fld_nama_department, fld_id_hod (FK ke tb_karyawan)

**tb_tenant** _(masuk v2 — bukan multi-tenancy arsitektur, ini entitas penyewa tempat)_

- fld_id_tenant (PK), fld_nama_tenant, fld_nama_perusahaan, fld_status, fld_lokasi, fld_phone, fld_area, fld_tipe_tenant, fld_unit(?), fld_kategori, fld_deskripsi, fld_logo, fld_keterangan

**tb_work_order**

- fld_id_work_order (PK), fld_no_work_order, fld_jam_work_order, fld_department (FK), fld_id_pelapor (FK ke tb_karyawan — Requester), fld_jenis_pekerjaan_kerusakan, fld_prioritas, fld_status_pekerjaan, fld_level, fld_gambar, fld_business_meeting(?), fld_risalah_meeting(?), fld_keterangan, fld_status_hapus

**tb_work_planning** _(Work Order Terjadwal)_

- fld_no_planning (PK), fld_id_work_order (FK), fld_department, fld_tanggal_planning_start, fld_budget(?), fld_rincian_pekerjaan, fld_lokasi_pekerjaan, fld_id_pic (FK ke tb_karyawan), fld_prioritas, fld_status_pekerjaan, fld_path_folder(?), fld_keterangan, fld_status_hapus

**tb_work_daily**

- fld_no_work_daily (PK), fld_id_work_order (FK, nullable jika tugas rutin murni), fld_tanggal_work_daily, fld_department, fld_rincian_pekerjaan, fld_lokasi_pekerjaan, fld_id_pic (FK), fld_level, fld_prioritas, fld_status_pekerjaan, fld_keterangan, fld_status_hapus

**tb_work_data** _(Work Data Management — histori)_

- fld_no_kerja (PK), fld_id_pekerjaan (FK ke WO/planning/daily), fld_jam_work_data, fld_department, fld_status_pekerjaan, fld_gambar_sebelum, fld_gambar_sesudah, fld_prediksi_penyebab, fld_hasil_kesimpulan, fld_saran_solusi, fld_tindakan, fld_status_hapus

**tb_work_data_pekerja** _(relasi karyawan ↔ Work Data / assignment)_

- fld_id_data_pekerja (PK), fld_no_kerja (FK), fld_id_user (FK), fld_id_karyawan (FK)

**tb_schedule_wd** _(master jadwal kerja)_

- fld_no_schedule_wd (PK), fld_department, fld_tipe_schedule_wd, fld_status_aktif

**Catatan tambahan yang perlu ditambahkan di luar ERD asli (belum ada tabelnya):**

- Entitas **Notification** (id, user_id tujuan, tipe event, referensi ke WO/Daily Work, status dibaca, timestamp) — wajib untuk FR-7.x, belum ada di ERD terlampir.
- Entitas **Extend Request** (id, referensi WO/Schedule, jenis extend \[deadline WO telat / work schedule\], jumlah hari, alasan, status approval, approver, timestamp) — wajib untuk FR-2.3/2.4, belum eksplisit sebagai tabel terpisah di ERD; kemungkinan disatukan ke tb_work_planning/tb_work_order via field status, tapi ini **Open Question** untuk tim engineering.

---

## 8. Edge Case & Failure State

- WO Urgent by Accident dibuat tapi department tujuan tidak punya HOD aktif (HOD cuti/resign) → siapa yang menerima notifikasi dan assign? **Belum didefinisikan — Open Question.**
- Karyawan yang di-assign resign/nonaktif sebelum WO selesai → butuh mekanisme re-assign, belum dibahas.
- Extend deadline diajukan lebih dari 1 kali untuk WO yang sama — apakah dibatasi maksimal 1x extend atau bisa berulang? **Open Question**, dokumen hanya menyebut "extend 3 hari" tanpa batas jumlah pengajuan.
- WO direject HOD berkali-kali (revisi berulang) tanpa batas — apakah ada SLA/eskalasi untuk kasus ini? Tidak disebutkan di dokumen sumber.
- Dua Requester dari department berbeda membuat WO ke department tujuan yang sama secara bersamaan dengan prioritas sama — urutan pengerjaan tidak didefinisikan (asumsi FIFO, perlu konfirmasi).
- Department tanpa Team Leader — notifikasi hari ke-3 di-skip, langsung tunggu hari ke-5 ke HOD (sesuai FR-2.2), tapi perlu dipastikan sistem tahu department mana yang punya/tidak punya Team Leader (role ini belum eksplisit ada di ERD tb_karyawan — **Open Question**).
- Realtime notification gagal terkirim (user offline/koneksi putus) — perlu notifikasi tetap tersimpan di database dan muncul saat user login kembali (in-app notification history, bukan hanya push).
- Approval extend Work Schedule menunggu DGM/GM yang sedang cuti/tidak aktif — tidak ada mekanisme delegasi/pengganti approver disebutkan di dokumen sumber.
- File/gambar bukti pekerjaan (sebelum/sesudah) gagal upload atau ukuran terlalu besar — perlu validasi ukuran/format, belum dispesifikasikan batasannya.

---

## 9. Success Metrics

\*Catatan: user menyatakan "selesai" = semua fitur berjalan sempurna tanpa celah keamanan — ini kriteria kualitatif rilis, bukan metrik pasca-launch. Metrik di bawah adalah usulan minimal untuk mengukur apakah produk mencapai tujuan di §3; **perlu dikonfirmasi/disetujui user sebagai Open Question**, karena tidak eksplisit diminta.\*\*

- % Work Order yang closed tanpa melewati eskalasi hari ke-6 (target awal: perlu ditentukan bersama stakeholder).
- Waktu rata-rata dari WO dibuat → di-assign (mengukur responsivitas HOD).
- Waktu rata-rata dari assign → closed per kategori (Normal/Urgent).
- % WO Urgent by Accident yang dieksekusi di hari yang sama.
- Jumlah insiden akses tanpa izin yang terblokir RBAC (proxy untuk "tanpa celah keamanan" — perlu didefinisikan lebih lanjut lewat security testing/pentest sebelum dianggap "selesai").
- Adopsi: % Requester/HOD/karyawan aktif menggunakan sistem vs proses lama (manual/chat) dalam periode pilot.

---

## 10. Open Questions

1. Apakah Requester adalah role terpisah, atau setiap user (apapun rolenya) otomatis bisa jadi Requester? (§6.6)
2. Nilai pasti minimal/maksimal hari kerja deadline — apakah selalu 3–6 hari untuk semua jenis WO, atau bervariasi per kategori/prioritas? (§6.2 FR-2.1)
3. Batas jumlah pengajuan extend per WO — sekali saja atau bisa berulang? (§8)
4. Mekanisme template Daily Work — didefinisikan per karyawan individual, per jabatan, atau per department? Siapa yang berwenang membuat/mengubah template? (§6.5 FR-5.1)
5. Apakah "Team Leader" adalah role/jabatan resmi di tb_karyawan, dan bagaimana sistem tahu department mana yang punya Team Leader vs tidak? (§6.2 FR-2.2, §8)
6. Apa yang terjadi jika HOD department tujuan tidak aktif (cuti/resign) saat WO Urgent masuk? (§8)
7. Channel notifikasi — cukup in-app, atau perlu WA/email gateway di MVP? User menjawab "belum ada batasan teknis", jadi ini masih terbuka. (§6.7 FR-7.2)
8. Definisi "selesai" saat ini adalah kualitatif (semua fitur jalan + tanpa celah keamanan) — perlu disepakati kriteria ukur objektif, misalnya: lulus UAT 100%, lulus pentest, atau live di 1 department pilot selama X minggu tanpa insiden kritikal.
9. Relasi Extend Request ke ERD — apakah butuh tabel baru terpisah, atau cukup field status tambahan di tb_work_order/tb_work_planning? (§7)
10. Field-field ERD yang ditandai (?) di §7 perlu diverifikasi langsung ke sumber ERD (bukan hasil baca dari foto) sebelum development dimulai, karena beberapa nama kolom tidak terbaca jelas dari gambar yang dilampirkan.
11. Hak akses **Super Admin** vs **Direksi** belum dijelaskan — apakah Super Admin setara pengelola konfigurasi RBAC/master data (department, divisi, karyawan) sementara Direksi hanya punya akses read-only untuk monitoring lintas department? Ini menentukan permission set di FR-6.2.
