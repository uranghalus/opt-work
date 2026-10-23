---
created: 2026-10-22
tags: [research, design, audit, ui-ux, departments, master-data, accessibility, responsive]
status: draft
---

# Audit UI — Halaman Department (index + show)

> **Scope:** audit tampilan halaman `/{tenant}/departments` (index) dan
> `/{tenant}/departments/{id}` (show). Penilaian **bebas** — tidak memakai
> `DESIGN.md`/`DESIGN_BRIEF.md` sebagai standar; yang dipakai adalah prinsip
> UI/UX umum + WCAG 2.2 + skill `ui-ux-pro-max`.
>
> **Metode:** baca kode (`resources/js/pages/departments/*`,
> `app/Http/Controllers/MasterData/DepartmentController.php`), render nyata di
> Chrome headless dengan fixture org realistis (9 department, 126 karyawan,
> HOD sebagian terisi), ukur DOM, bandingkan rasio kontras.
>
> **Bukti:** `.impeccable/review/dept-index-light.png`, `dept-index-dark.png`,
> `dept-index-mobile.png`, `dept-show-light.png`, `dept-index-focus.png`,
> plus `capture-departments.mjs` + `measure-departments.mjs`.
>
> **Tidak ada kode aplikasi yang diubah.**

---

## Ringkasan

Halaman index **sebagian besar bekerja** — shell heading band, breadcrumb,
token dark mode, dan struktur tabelnya benar. Yang rusak ada di **isi dan
jalur**: satu department tidak bisa diakses, tiga kolom hilang di ponsel, ada
hydration mismatch di setiap load, dan halaman detail kehilangan heading,
breadcrumb, serta tombol kembali.

**Skor per dimensi** (5 = baik, 1 = buruk):

| Dimensi | Skor | Catatan |
| --- | --- | --- |
| Kebacaan visual | 3.5/5 | Tipografi & token bagus; ritme baris & bobot kolom tidak konsisten |
| Navigasi | 2/5 | Index baik; detail kehilangan breadcrumb + tombol kembali + h1 |
| Responsive | 1.5/5 | 3 dari 6 kolom off-screen di 390px |
| Aksesibilitas | 2.5/5 | 3 kegagalan terukur (h1, kontras, focus ring) + 1 bug runtime |
| Efisiensi kerja | 2.5/5 | Angka penting tidak bisa diklik; tidak ada search/sort/paginasi |
| Konsistensi | 3/5 | Index konsisten dengan shell; halaman detail menyimpang dari semua halaman lain |

## Status perbaikan — slice #1–#5 (2026-10-22, DONE)

User memilih slice #1–#5. Yang dikerjakan:

| Item | Status | Verifikasi |
| --- | --- | --- |
| **A1** — format tanggal di server, hapus `toLocaleString` | ✅ FIXED | `last_synced_label` = "22 Okt 2026, 02:53" (format server, timezone app); `last_synced_at` ISO untuk `<time datetime>`. Label jadi "Sinkron terakhir:". **Hydration mismatch hilang** — capture browser: `no browser errors` (sebelumnya ada `[pageerror] Hydration failed...`). |
| **C1** — paginasi | ✅ FIXED | `per_page` 50 + footer paginasi ("Menampilkan 1–9 dari 9 department", Halaman N dari M, Sebelum/Sesudah). 51 department → 2 halaman, `DEP-051` terjangkau. |
| **M1** — mobile | ✅ FIXED | `<768px` ganti ke **card list** (kartu: kode, nama, HOD, karyawan, divisi). Terukur: `tableVisible: false`, `columnsOffscreen: []`, `documentScrollWidth == viewportWidth`. Sel HOD juga diberi `truncate` + `max-w-[20rem]` sehingga email panjang tidak lagi mendorong lebar kolom. |
| **A2 + A3** — h1 + breadcrumb di detail | ✅ FIXED | `h1Count: 1`, `h1Text: "Accounting & Finance"`, `breadcrumbText: "Data MasterDepartmentAccounting & Finance"` + link balik ke daftar. |
| **A5** — focus ring link nama | ✅ FIXED | `boxShadow: rgb(12,107,88) 0 0 0 2px` + offset 2px (desktop & mobile). |

**Tambahan (di luar slice, ditemukan saat audit):**

| Item | Status | Verifikasi |
| --- | --- | --- |
| **`divisions/show.tsx` crash** | ✅ FIXED | Pola `layout: { title: (division) => division.nama_division }` **rusak**: Inertia v3 menyalin nilai layout apa adanya ke props layout, sehingga `title` jadi function dan React melempar *"Functions are not valid as a React child"* — `<h1>` kosong di setiap load. Diganti ke `layout: (props) => ({...})`. Terukur: `h1Text: "Accounting"`, `errors: []`. |

> **Pola yang benar** (dipakai di `departments/show.tsx` dan `divisions/show.tsx`):
> `layout` **harus berupa function** kalau butuh props halaman:
> `ShowDepartment.layout = (props) => ({ title: ..., breadcrumbs: [...] })`.
> Nilai function di dalam object `layout` **tidak** dipanggil Inertia.

**Belum dikerjakan (sisa slice):** #6 A4 (kontras "Belum di-set" 3.10:1 + badge),
#7 I1 (angka karyawan jadi link + hapus tombol "Lihat"), #8 I2–I6 (badge jumlah
department tanpa HOD, layout detail dua kolom, bobot kolom, density baris,
search/sort/filter).


---

## C1 — FIXED — Department ke-11 tidak bisa diakses

`DepartmentController@index` → `->paginate(10)`, tapi `departments/index.tsx`
merender **hanya** `departments.data`. Tidak ada komponen paginasi di file
itu maupun di mana pun pada halaman.

Terukur: `paginationControls: 0`.

Dampaknya bukan hipotetis — cabang **DMB punya 11 department**:

```
bm: 1 departments
dmb: 11 departments  <-- MELEBIHI per_page=10
dmp: 1 departments
```

`DMB-TENANCY` (urutan terakhir, alphabetis) **tidak pernah tampil dan tidak
ada cara mencapainya**. `divisions/index.tsx` dan `positions/index.tsx` punya
lubang yang sama. Bandingkan `employees/index.tsx`, yang punya search debounce,
filter status, pemilih `per_page`, dan blok paginasi lengkap — jadi ini
ketidakkonsistenan di dalam repo sendiri, bukan keputusan desain.

**Perbaikan:** render `pagination` (pola yang sudah ada di `employees/index.tsx`)
atau naikkan `per_page` ke 50 untuk master data dan hapus paginasi. Untuk data
master, biasanya lebih baik "tampilkan semua + search" daripada paginasi.

---

## M1 — FIXED — Di ponsel, 3 dari 6 kolom off-screen

Terukur di viewport 390px:

```
tableContainerClientWidth:  356
tableContainerScrollWidth:   719      (2.0x lebar viewport)
columnsOffscreen: ["HOD", "Karyawan", "Aksi"]
```

Kolom yang hilang persis yang paling penting: **jumlah karyawan** (41, 18, 12…)
dan **aksi "Lihat"**. Yang tersisa di layar hanya Kode, Nama, dan angka Divisi
saja — dan email HOD terpotong di tengah kata.

Penyebab: sel HOD **tidak punya `truncate`**, jadi alamat email yang panjang
mengembang lebar kolom tanpa batas. (Email di fixture memang sengaja
dipanjangkan, tapi email Optigate asli juga panjang — ini bukan artefak
fixture.)

Region-nya memang bisa digulir horizontal (`overflow-x-auto` dari primitive
`Table`), tapi:
- tidak ada affordance apa pun yang menyuruh pengguna menggulir,
- kolom paling penting berada **paling kanan**, jadi harus digulir sampai
  ujung,
- baris paling bawah tertutup floating bottom nav.

`ui-ux-pro-max` memberi rating **High** untuk ini:
*"Horizontal Scroll — Avoid horizontal scrolling. Do: Ensure content fits
viewport width. Don't: Content wider than viewport."*

**Perbaikan (pilih satu):**
1. **Pindah ke card list di <768px** (pola yang dipakai halaman Timeline dan
   pola skill: *"Table Handling — Do: Use horizontal scroll **or card layout**"*).
   Kartu: Kode + Nama di atas, baris meta berisi HOD · Divisi · Karyawan.
2. Minimal: `truncate` pada nama/email HOD + sembunyikan kolom "Divisi" dan
   "Aksi" di layar kecil (`hidden md:table-cell`), karena nama department
   sudah jadi link.

---

## A1 — FIXED — Hydration mismatch di setiap load

`index.tsx:145`:

```tsx
new Date(last_synced_at).toLocaleString(undefined, {
    dateStyle: 'medium', timeStyle: 'short',
})
```

`undefined` berarti "locale browser". Server render (locale `id-ID`):
`22 Okt 2026, 10.20`. Browser (locale `en-US`):
`Oct 22, 2026, 10:20 AM`. React membuang seluruh HTML server dan render ulang
seluruh tree — **flash terlihat + error console di setiap kunjungan**.

Terukur di console browser:

```
[pageerror] Hydration failed because the server rendered text didn't match
the client. ...
+   Oct 22, 2026, 10:20 AM
-   22 Okt 2026, 10.20
```

React sendiri menyebut "Date formatting in a user's locale which doesn't match
the server" sebagai salah satu penyebab hydration mismatch.

Sekalian: labelnya **bahasa Inggris** — `"Last synced:"` — di aplikasi yang
seluruh copy-nya Bahasa Indonesia. Seharusnya "Sinkron terakhir:".

**Perbaikan:** format tanggal di server (locale tetap, timezone tetap) lalu
kirim string jadi ke view, persis seperti yang sudah dilakukan di halaman
Timeline (`weekLabel` dihitung di controller). Hilangkan pemanggilan
`toLocaleString` dari komponen.

---

## A2 — FIXED — Halaman detail tidak punya `<h1>`

`show.tsx` memakai komponen lokal `Heading`, yang merender **`<h2>`**:

```tsx
<h2 className="text-xl font-semibold tracking-tight">{title}</h2>
```

Terukur: `h1Count: 0`, `h2Texts: ["Accounting & Finance"]`.

Halaman ini juga **tidak** memakai `.layout` metadata, jadi `PageHeader` shell
tidak merender apa-apa (judul, deskripsi, dan slot aksi kosong).

Efeknya: pengguna screen reader masuk ke halaman tanpa heading level
tertinggi, dan hierarki heading melompat. `ui-ux-pro-max`:
*"Heading Hierarchy — Do: Use sequential heading levels h1-h6. Don't: Skip
heading levels."*

**Perbaikan:** samakan dengan halaman lain — set `DepartmentShow.layout`
dengan `breadcrumbs`, `title`, `description`, lalu hapus `Heading` lokal.

---

## A3 — FIXED — Detail tanpa breadcrumb dan tanpa cara kembali

Terukur di halaman detail:

```
breadcrumbText: null
backLinkCount: 0     (tidak ada link yang mengandung "department")
```

Slot breadcrumb di top bar kosong — ada panel toggle lalu langsung kosong.
Tidak ada tombol "← Kembali ke Department". Satu-satunya jalan keluar adalah
tombol back browser atau klik ulang sidebar.

Kedalaman halaman ini tepat 3 level: *Data Master › Department › Accounting &
Finance*. `ui-ux-pro-max`: *"Breadcrumbs — Do: Use for sites with 3+ levels
of depth."*

**Perbaikan:** `breadcrumbs: [{title:'Data Master'}, {title:'Department', href: index}, {title: nama}]`.
Item "Department" jadi link balik — cukup, tanpa tombol tambahan.

---

## A4 — BELUM — "Belum di-set" gagal WCAG AA

`text-ink-subtle` = `#8494A7` di atas kartu putih:

```
ink-subtle  #8494A7 on #FFFFFF =  3.10:1   FAIL (butuh 4.5:1 untuk teks 14px)
ink-muted   #5B6B7C on #FFFFFF =  5.47:1   PASS
```

Dan ini pesan yang **paling sering muncul di halaman**: pada fixture, 4 dari 9
baris tidak punya HOD. Di cabang DMB yang nyata, semua department memang
punya `hod_employee_id` tapi `hod_user_id` kosong — jadi banyaknya "Belum
di-set" bisa jauh lebih besar.

Ironisnya: **pesan paling sering = pesan paling tak terbaca.** "Belum di-set"
justru informasi paling penting di halaman ini (siapa penanggung jawab
seksi), tapi dirender sebagai teks paling redup.

**Perbaikan:** ganti ke `text-ink-muted` (5.47:1) minimal, dan beri
perlakuan berbeda dari teks biasa — misal badge dengan label, supaya
kekosongan data terbaca sebagai kondisi, bukan sekadar catatan kaki.

---

## A5 — FIXED — Link nama department tidak punya focus ring

Link nama hanya punya `hover:underline`:

```tsx
<Link href={show({...})} className="hover:underline">{department.nama_department}</Link>
```

Terukur saat link difokuskan:

```
focusRingOnNameLink:   "none"
focusOutlineOnNameLink: "1px"
```

Jadi hanya outline default browser 1px (yang warnanya dicetak ulang jadi teal
oleh aturan global `:focus-visible { outline-color: var(--focus-ring) }`).
Semua kontrol lain di app ini — tombol, input, badge, link Timeline — memakai
**ring 2px + offset 2px**. Link nama department adalah target utama setiap
baris, dan justru yang paling lemah fokusnya.

`ui-ux-pro-max`: *"Focus States — Do: Use a visible focus ring on every
interactive control."* (Severity: High)

**Perbaikan:** samakan dengan link Timeline:
`focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2`.

---

## I1 — BELUM — Angka yang paling penting tidak bisa diklik

Baris "Building Service" menampilkan **41** karyawan. Angka itu adalah
*payload* halaman ini — tapi hanya teks mati. Tidak ada cara melihat siapa
mereka. Yang bisa diklik cuma:
- nama department → halaman detail, dan
- tombol "Lihat" → **halaman detail yang sama**.

Jadi ada **dua link ke tujuan yang sama per baris**, sementara link yang
berguna (lihat 41 karyawan) tidak ada.

Halaman detail punya masalah serupa: "Jumlah Karyawan 12", "Jumlah Position 1",
dan "Divisi: Accounting & Finance Div 1" — semuanya plain text, tidak ada
drill-through.

**Perbaikan (murah, tanpa perubahan backend):** `EmployeeQuery` sudah
mencari nama department (`app/.../EmployeeQuery.php:50-51`), jadi angka
karyawan bisa jadi link ke `employees?search=<nama department>`. Untuk yang
rufan, tambahkan parameter `department` ke employees index.

Sekalian: hapus tombol "Lihat" per baris — link pada nama sudah cukup, dan
satu baris dengan dua affordance ke tujuan yang sama adalah noise.

---

## I2 — MEDIUM: "Belum di-set" diperlakukan sebagai kondisi normal

44% baris tanpa HOD adalah **masalah kualitas data**, bukan kondisi normal.
Tapi tampilannya: abu-abu pasif, tanpa badge, tanpa ikon, tanpa angka
ringkasan. Tidak ada yang di heading band yang berbunyi "4 department belum
punya HOD".

Halaman ini tidak bisa memperbaiki data (portal adalah sumber kebenaran),
tapi bisa **menyorotkan sinyal** — dan saat ini ia memilih tidak untuk.

**Perbaikan:** chip di heading band, mis. `4 tanpa HOD` (diklik → filter), plus
perlakuan "exception" pada baris terkait (ikon + warna warning, bukan teks
redup).

---

## I3 — MEDIUM: Halaman detail memakai 49% lebar dan tidak menambah banyak

Terukur:

```
contentWidth: 576      (max-w-xl)
mainWidth:    1184
```

`max-w-xl` (576px) dipusatkan di dalam area konten 1184px → **~300px ruang
mati di kiri dan kanan**. Worse, judul halaman **bergeser ~300px ke kanan**
saat pindah dari daftar ke detail, karena daftar memakai heading band shell
di kiri penuh sementara detail memakai kolom 576px yang terpusat. Kontinuitas
visual hilang.

Dan isinya: 7 baris label/nilai, 3 di antaranya angka tanpa drill-through,
dan hanya satu yang benar-benar baru dari index → **nama-nama divisi** — dan
itulah satu-satunya yang tidak bisa diklik.

**Perbaikan:** dua kolom (identitas | relasi) di dalam lebar penuh, list
divisi dan posisi jadi link, dan jumlah karyawan jadi link ke daftar
karyawan terfilter.

---

## I4 — MEDIUM: Dua kolom angka, dua bobot berbeda, tanpa alasan

Kolom "Divisi" pakai `text-muted-foreground` (#5B6B7C); kolom "Karyawan" pakai
ink default (#15202B). Bersebelahan, tipe data sama, tanpa penjelasan —
terbaca sebagai kekacauan, bukan hierarki. especially karena "Divisi" adalah
relasi struktural sementara "Karyawan" adalah headcount, jadi sebenarnya
justru headcount yang lebih penting; jadi tidak ada alasan kuat untuk
melemahkan Divisi.

**Perbaikan:** samakan bobot, atau jadikan keduanya label+angka dengan
hierarki yang disengaja (label muted kecil di atas, angka besar).

---

## I5 — MEDIUM: Baris terlalu tinggi untuk master data

Sel HOD menumpuk avatar + nama + email → tinggi baris ~78px. Hanya 9 baris
muat di viewport 900px. Untuk daftar master data, itu mahal: email adalah
elemen paling worthless di baris itu, tapi justru yang drove tinggi baris.

**Perbaikan:** email jadi `truncate` satu baris, atau pindah ke detail page
saja (halaman detail sudah menampilkannya). Target ~56px/baris → 13 baris
muat.

---

## I6 — MEDIUM: Tidak ada search, sort, atau filter

`employees/index.tsx` punya: search debounce server-side, filter status,
pemilih per-page, paginasi. `departments/index.tsx` tidak punya satu pun.
Untuk 11 baris masih bertahan; untuk cabang yang lebih besar ini stop total,
dan tidak ada cara menjawab "department mana yang punya X".

**Perbaikan:** minimal search by kode/nama (sudah ada polanya di
`EmployeeQuery`), plus sort per kolom.

---

## I7 — LOW: Dua perlakuan empty-state berbeda di halaman detail

```
HOD               → "Belum di-set"
Deputy / Manager  → "—"
```

Dua nilai yang sama-sama kosong, dua perlakuan berbeda. Salah satunya
(bagian HOD) memang bermakna ("belum ada yang ditunjuk"), yang lain benar-benar
tidak ada datanya ("—" wajar). Kalau dibedakan, bedakan dengan alasan;
kalau tidak, samakan.

---

## Yang sudah bagus

- **Dark mode benar-benar bekerja.** Token bertahan, tidak ada kontras yang
  patah, tabel tetap terbaca. Ini bukan "inverted light theme".
- **Index memakai heading band shell** dengan module chip, breadcrumb
  `Data Master › Department`, dan satu aksi utama. Konsisten dengan halaman lain.
- **Kode department pakai mono** dan angka pakai `tabular-nums` — benar.
- **Avatar dengan fallback inisial** untuk HOD — benar, dan tidak ada gambar rusak.
- **Empty state tabel** ("Belum ada department.") dengan `colSpan` yang benar.
- **Tombol Sinkronkan punya state loading** ("Menyinkronkan…") dan spinner.
- **Scope data benar**: hanya `sync`, tidak ada CRUD — sesuai realitas portal
  sebagai sumber kebenaran.

---

## Urutan perbaikan yang saya sarankan

| # | Item | Dampak | Biaya | Status |
| --- | --- | --- | --- | --- |
| 1 | A1 — format tanggal di server, hapus `toLocaleString` | Hentikan hydration mismatch + flash di setiap load | kecil | ✅ DONE |
| 2 | C1 — paginasi (atau `per_page` 50) | Department ke-11 bisa diakses | kecil | ✅ DONE (`per_page` 50 + footer) |
| 3 | M1 — `truncate` HOD + card list di mobile | Halaman bisa dipakai di ponsel | sedang | ✅ DONE (card list) |
| 4 | A2 + A3 — `.layout` callback di `show.tsx` (h1 + breadcrumb) | Aksesibilitas + wayfinding | kecil | ✅ DONE |
| 5 | A5 — focus ring pada link nama | Konsistensi fokus keyboard | sangat kecil | ✅ DONE |
| 6 | A4 — "Belum di-set" ke `ink-muted` + badge | Lolos AA + kekosongan data jadi terlihat | kecil | ⬜ TODO |
| 7 | I1 — angka karyawan jadi link + hapus tombol "Lihat" | Angka jadi bisa ditindaklanjuti | kecil | ⬜ TODO |
| 8 | I2/I3/I4/I5/I6 — hirarki, lebar, bobot, density, search | Kualitas tampilan | sedang–besar | ⬜ TODO |

Slice #1–#5 selesai 2026-10-22 (lihat tabel "Status perbaikan" di atas untuk
bukti ukurnya). Sisa: #6–#8.
