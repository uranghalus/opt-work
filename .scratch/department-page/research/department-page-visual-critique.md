---
created: 2026-10-22
tags: [design, critique, ui, ux, departments, master-data, visual-hierarchy, tokens]
status: active
---

# Critique Desain — Halaman Department (Lead Designer review)

[[Index]]

> **Peran:** Lead Designer, bukan auditor checklist. Penilaian **tidak** mengikat
> diri ke `DESIGN.md`/`DESIGN_BRIEF.md`.
> **Bukti:** `.impeccable/review/d2-*.png` (fold, heading band, table closeup,
> detail, mobile, dark) + `capture-dept-design.mjs`. Semua angka di bawah diukur
> dari DOM, bukan taksiran.
> **Tidak ada kode yang diubah pada ronde ini.**

---

## Verdict

Halaman ini tidak "flat". **Halaman ini belum didesain.** Yang ada adalah tabel
HTML yang diberi border, di dalam shell yang rapi. Shell-nya (sidebar, heading
band, breadcrumb) punya karakter; isinya tidak. Begitu masuk ke area konten,
semua keputusan desain berhenti.

Dan ini bukan soal selera. Ini terukur:

| Sinyal | Angka | Ambang sehat |
| --- | --- | --- |
| Teks berukuran 14px | **61 dari 71 node (86%)** | < 55% |
| Elevasi (box-shadow) di area konten | **0** | ≥ 2 tingkat |
| Kontras canvas ↔ card | **1.06:1** | ≥ 1.2:1 terasa, 3:1 untuk batas |
| Kontras border ↔ card | **1.38:1** | 3:1 (WCAG 1.4.11) |
| Porsi lebar tabel yang berisi glyph | **45%** (508px dari 1118px) | ≥ 70% |
| Void horizontal terbesar | **221px** | < 60px |
| Isi kolom "Divisi" | **7px digit di kolom 87px (8% fill)** | — |
| Isi kolom "Karyawan" | **14px di kolom 127px (11% fill)** | — |
| Detail: lebar konten vs main | **49%** (576/1184) | ≥ 65% |

**Skor:** 2.5/5 sebagai UI. 3.5/5 sebagai UX fungsional (setelah perbaikan
aksesibilitas ronde sebelumnya).

---

## Diagnosis: lima akar penyebab

Bukan gejala. Kalau hanya gejalanya yang diperbaiki, hasilnya akan tetap terasa
generik.

### 1. Type scale ada di token, tidak dipakai di halaman

`app.css` mendefinisikan skala 11→32px. Halaman ini memakai **satu ukuran**:
14px untuk header kolom, kode, nama, nama HOD, angka, dan "Lihat". 13px untuk
email. 24px untuk h1. Selesai.

Akibatnya hierarki hanya bisa dibawa oleh **weight** (400/500/600) dan **warna**.
Itu penyebab langsung kenapa semuanya terasa rata: tidak ada perbedaan ukuran
untuk dibaca mata. Nama department seharusnya 2× ukuran email HOD, bukan
1.08×.

### 2. Grid diserahkan ke browser

Tabel `w-full` tanpa lebar kolom eksplisit → browser membagi rata. Hasil ukur:

| Kolom | Lebar sel | Lebar glyph | Fill |
| --- | --- | --- | --- |
| Kode | 237px | 50px | 21% |
| Nama | 261px | 97px | 37% |
| Divisi | 87px | **7px** | **8%** |
| HOD | 310px | 286px | 92% |
| Karyawan | 127px | 14px | 11% |
| Aksi | 96px | 54px | 56% |

Lima dari enam kolom sebagian besar kosong. Void 221px antara "Building Service"
dan "2". Itulah kenapa tabel terbaca sebagai **pulau-pulau angka**, bukan satu
baris data.

### 3. Tidak ada sistem permukaan

- Canvas `#f7f9fb` vs card `#ffffff` = **1.06:1**. Praktis tidak terlihat. Di
  monitor kelas menengah, card-nya menyatu dengan latar.
- `border` `#d5dce5` vs card = **1.38:1** — hairline yang menghilang.
- `shadow-card` = `0 1px 2px rgba(20,33,43,0.05)`. 5% opacity, blur 1px. Bahkan
  kalau dipakai, tidak akan terbaca. Dan tabel ini **tidak memakainya sama
  sekali** — satu-satunya shadow di halaman adalah focus ring.

Jadi tidak ada satu pun mekanisme yang membuat konten "mengangkat" dari latar.
Itu definisi visual flat.

**Temuan sistemik:** `border-strong` `#a8b4c4` hanya **2.10:1** terhadap putih —
di bawah 3:1 yang diminta WCAG 1.4.11 untuk batas non-teks yang bermakna. Artinya
**token set ini secara struktural tidak bisa menghasilkan card yang terdefinisi
dengan baik.** Untuk mencapai 3:1, border harus sekitar `#949ca4` atau lebih
gelap. Ini perlu diputuskan di level token, bukan di level halaman — kalau tidak,
halaman ini akan jadi outlier atau tetap flat.

### 4. Semua baris setara

9 baris, tinggi identik 53px, border identik, tanpa grouping, tanpa emphasis.
"Building Service — 41 karyawan" dan "Legal — 4 karyawan" punya bobot visual
yang sama persis. Header kolom juga tidak terpisah dari body: 14px, warna
`#5B6B7C`, latar putih, border bawah 1px yang sama dengan antar-baris.

Tidak ada titik masuk mata. Tidak ada hal pertama yang harus dilihat.

### 5. Halaman tidak punya subjek

Ini akar yang paling dalam. Halaman Department seharusnya menjawab: *"bagaimana
bentuk organisasi cabang ini?"* Yang ditampilkan adalah *"berikut 9 record"*.

Konsekuensinya:
- **Headcount — muatan sebenarnya halaman ini — adalah integer mati** yang
  tidak bisa diklik dan tidak punya bobot visual.
- **HOD — orang yang bertanggung jawab — adalah avatar 28px.**
- Tidak ada ringkasan, tidak ada distribusi, tidak ada cerita.

Halaman detail lebih parah: 7 baris `<dl>` label/nilai, lalu **~850px kosong**.
Itu bukan halaman, itu form yang belum selesai.

---

## "Slop tells" — hal yang langsung terbaca sebagai hasil generate

Ini yang dimaksud "slop". Semuanya bisa dihilangkan tanpa menambah fitur.

1. **`mx-auto max-w-xl`** untuk detail page. Card 576px dipusatkan di kolom
   1184px → **~304px ruang mati tiap sisi**, dan tepi kiri card berada **~300px
   di kanan** judul h1 yang rata kiri. Dua sumbu berbeda di satu halaman.
   Ini pola layout paling sering dipakai model, dan paling mudah dikenali.
2. **`"Detail department"`** sebagai deskripsi — copy yang mengulang judul dan
   tidak menambah informasi.
3. **`"(hanya sync)"`** — detail implementasi bocor ke copy pengguna. Tidak ada
   operator yang peduli.
4. **"Lihat" × 9** — aksi teks berulang yang tujuannya **sama persis** dengan
   link pada nama department. Dua affordance per baris untuk satu tujuan.
5. **Paginasi nonaktif untuk satu halaman** — "Sebelumnya / Halaman 1 dari 1 /
   Selanjutnya" adalah elemen paling "didesain" di halaman ini, dan tidak ada
   gunanya. Chrome demi chrome.
6. **Kotak rounded bertint dengan ikon 16px di dalam 40px** — module chip
   generik. Ikonnya terlalu kecil untuk kotaknya, jadi terbaca sebagai
   placeholder.
7. **"Belum di-set" sebagai teks abu `#8494A7` (3.10:1)** — kegagalan data
   paling penting di halaman dirender sebagai teks yang paling tidak terbaca.
   Muncul 4 dari 9 baris.
8. **Kolom "Divisi" yang terlihat disabled** — satu digit, `ink-muted`, rata
   kanan, bersebelahan dengan "Karyawan" yang `ink` penuh. Dua kolom angka
   dengan bobot berbeda tanpa alasan.
9. **Kolom HOD yang ragged** — baris ber-HOD mulai dengan lingkaran avatar,
   baris tanpa HOD mulai dengan teks. Titik mulai visualnya berbeda, jadi kolom
   itu terlihat rusak, bukan kosong dengan sengaja.
10. **Avatar mint ×6 sebagai satu-satunya warna** — repetisi, bukan hierarki.
11. **`<dl>` spec-sheet sebagai halaman detail** — label 14px / nilai 14px,
    hanya dibedakan warna. Tidak ada subjek visual.
12. **Heading band memakan ~300px dari 844px viewport di ponsel (36%)** sebelum
    department pertama muncul. Tiga elemen teks bertumpuk + satu tombol outline
    yang mengambang.

---

## Yang sudah benar (jangan dirusak)

- **Kontrak shell**: sidebar, breadcrumb 3 level, heading band dengan satu `h1`.
  Ini bagian paling "dirancang" dari halaman, dan sudah benar.
- **Kolom kode pakai mono.** Instingnya tepat: identifier harus monospace.
  Tinggal dijadikan eyebrow (11px, tracking) alih-alih kolom sendiri.
- **Dark mode lebih baik daripada light mode.** Delta permukaan di dark
  (`#101922` card vs latar lebih gelap + border `#22303b`) menghasilkan
  kedalaman yang tidak ada di light. Ini bukti bahwa masalahnya ada di token
  light, bukan di struktur.
- **Tinggi baris 53px** — wajar untuk tabel operasional.
- **`truncate` pada email** dan **card list di mobile** — keputusan struktural
  yang benar.
- **Restraint**: tidak ada gradient, glass, atau glow. Itu titik awal yang baik —
  masalahnya bukan kelebihan ornamen, tapi **kekurangan keputusan**.

---

## Arah desain yang saya rekomendasikan: "Registry"

Bukan menambah hiasan. Mengubah halaman dari *dump database* menjadi *indeks
organisasi yang diset dengan baik*. Enam langkah, berurutan dampak.

### 1. Beri halaman satu subjek dan satu angka (dampak terbesar)

Strip ringkas di atas tabel — tiga angka, bukan tiga kalimat:

```
9                 132                4
department        karyawan           tanpa HOD
```

Angka 28–32px `tabular-nums` semibold, label 11px uppercase tracking-wide muted,
dipisah hairline vertikal. Satu baris, tinggi ~72px.

Ini melakukan empat hal sekaligus: menambah ukuran tipe kedua (memecah
monoton 14px), menciptakan focal point, menjawab pertanyaan sebenarnya halaman
ini, dan menyingkirkan "Sinkron terakhir" dari posisi melayang.

Pindahkan "Sinkron terakhir: …" ke samping tombol "Sinkronkan" — keduanya satu
fitur, sekarang terpisah 1100px.

### 2. Susun ulang baris jadi dua tingkat

```
DMB–BS                          Building Service             41 karyawan
  └ eyebrow 11px mono, tracking     └ 15–16px semibold          └ lihat #4
      (SA) Sari Andriani · HOD
```

- Kode jadi **eyebrow** (11px mono, `tracking-[0.08em]`, muted) di atas nama —
  bukan kolom 237px.
- Nama naik ke 15–16px semibold.
- HOD jadi satu baris identitas inline (avatar 24px + nama 13px medium).
- Karyawan jadi angka 16px semibold + label kecil, rata kanan.

Ini mengubah hierarki dari *weight-only* menjadi *size + weight + position*.

### 3. Perbaiki grid, buang kolom mati

Lebar eksplisit: Nama fleksibel · HOD 240px · Divisi 72px · Karyawan 96px.
**Hapus kolom "Aksi"** — nama sudah link. Target: ink fraction 45% → ≥ 70%,
void maksimum 221px → < 60px.

### 4. Jadikan headcount sebagai kuantitas visual

Bar horizontal tipis (lebar = `count / max * 100%`) di samping angka, 48px,
`brand-soft` track dengan fill `brand`. Satu-satunya grafik di halaman, dan ia
memang membawa informasi: 41 vs 4 terbaca dalam sekejap.

Kalau tidak mau bar: titik 5-level (●●●●○) dengan `aria-label`.

### 5. Exception jadi chip, bukan teks abu

`Belum di-set` → chip `warning-subtle` + teks warning yang lebih gelap dari
`#b86e00` (sekarang **3.41:1** di `warning-subtle`, gagal AA untuk teks normal —
perlu ~`#7a4a00` agar ≥ 4.5:1) + titik 6px. Kekosongan data adalah **kondisi**,
bukan catatan kaki.

Sekalian: header kolom dapat latar `surface-raised` + rule bawah `border-strong`
+ label 11px uppercase tracking. Itu perbaikan termurah untuk "tabel tanpa
header".

### 6. Detail page jadi halaman subjek, bukan spec sheet

Lebar penuh (bukan `max-w-xl`), rata kiri dengan h1, dua kolom:

- **Kiri (5/12)** — identitas: kode sebagai eyebrow, nama 32px, kartu HOD
  (avatar 48px, nama, email), lalu 3 stat tile (12 karyawan / 1 position /
  1 divisi) dengan angka 24px.
- **Kanan (7/12)** — daftar divisi (link ke daftar karyawan terfilter) dan
  daftar position. Ini satu-satunya isi yang benar-benar baru dari halaman
  index, jadi tempatnya di sini dan harus bisa diklik.

Hapus `"Detail department"`. Ganti dengan ringkasan nyata: `"3 divisi · 12
karyawan · HOD belum ditunjuk"`.

### 7. Mobile: kompres band

Di <768px heading band jadi: judul + satu baris meta (`9 department · 4 tanpa
HOD`) + tombol sync **icon-only** 40px. 300px → ~150px.

---

## Token yang harus berubah (kalau tidak, perbaikan halaman akan sia-sia)

| Token | Sekarang | Masalah | Usul |
| --- | --- | --- | --- |
| canvas | `#f7f9fb` | 1.06:1 vs card | `#eef1f4` (token `--accent` sudah ada) → ~1.12:1, terasa |
| `shadow-card` | `0 1px 2px / 5%` | tidak terlihat | `0 1px 3px / 8%` + `0 1px 2px / 6%` |
| `border` | `#d5dce5` | 1.38:1, hairline hilang | pertahankan untuk divider, jangan untuk boundary card |
| `border-strong` | `#a8b4c4` | **2.10:1** — gagal 3:1 | `#949ca4` (~3:1) untuk boundary |
| `warning` di `warning-subtle` | 3.41:1 | gagal AA teks | `#7a4a00` |

Catatan penting: mengubah `canvas` dan `border-strong` menyentuh **semua**
halaman. Ini keputusan sistem, bukan keputusan halaman Department. Perlu
diputuskan eksplisit sebelum dikerjakan.

---

## Prioritas kerja

| # | Pekerjaan | Dampak | Biaya |
| --- | --- | --- | --- |
| 1 | Stat strip 3 angka + pindahkan "Sinkron terakhir" ke dekat tombol | Focal point + ukuran tipe kedua. Mengangkat halaman paling banyak per baris kode | S |
| 2 | Hapus "Lihat", hapus paginasi saat 1 halaman, hapus "(hanya sync)" & "Detail department" | Menghilangkan 4 dari 12 slop-tells tanpa kehilangan fungsi | XS |
| 3 | Lebar kolom eksplisit + kode jadi eyebrow + nama 16px | Ink fraction 45% → 70% | S |
| 4 | Exception chip + header kolom bertint | Data quality terlihat, tabel punya header | S |
| 5 | Bar headcount | 41 vs 4 terbaca instan | S |
| 6 | Detail page 2 kolom lebar penuh | Menghapus 300px dead space + 850px void | M |
| 7 | Kompres heading band mobile | +2 kartu per viewport | S |
| 8 | Token: canvas, shadow-card, border-strong, warning ink | Kedalaman sistemik untuk seluruh app | M (lintas halaman) |

#1–#5 bisa dikerjakan sebagai satu slice dan sudah mengubah halaman secara
signifikan tanpa menyentuh backend sama sekali.

---

## Satu hal yang perlu diputuskan lebih dulu

Flatness ini **bukan bug halaman Department** — ini konsekuensi dari token set
yang melarang kedalaman (canvas 1.06:1, shadow 5%, border 1.38:1). Kalau
`DESIGN.md` memang menghendaki flat, maka halaman ini sudah "benar" secara
sistem dan yang perlu diubah adalah **keputusannya**, bukan halamannya.

Yang saya sarankan: pertahankan restraint (tanpa gradient/glass/glow), tapi
izinkan **tiga hal** — satu ukuran tipe besar untuk angka kunci, satu tingkat
elevasi, dan satu warna status. Itu cukup untuk keluar dari "slop" tanpa
berubah jadi ramai.

---

## Terkait

- [[Decisions/department-page-ui-audit]] — audit UX/aksesibilitas + perbaikan
  slice #1–#5
- [[Mistakes/inertia-layout-title-fungsi-render-crash]]
- [[Index]]
