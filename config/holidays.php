<?php

/**
 * Hari libur untuk perhitungan deadline hari kerja (FR-2.1 — urgent 3 /
 * normal 6 hari kerja dari tanggal assign).
 *
 * Dua bentuk entri diterima:
 *  - 'Y-m-d'  → libur pada tanggal pasti tahun itu saja ('2026-12-31').
 *  - 'MM-DD'  → libur berulang setiap tahun ('12-25' = 25 Desember).
 *
 * Weekend (Sabtu+Minggu) otomatis dilewati oleh kalkulator; tambahkan
 * tanggal cuti bersama & libur nasional di sini tanpa deploy perubahan kode.
 */
return [
    '01-01', // Tahun Baru
    '05-01', // Hari Buruh
    '06-01', // Hari Lahir Pancasila
    '08-17', // Kemerdekaan RI
    '12-25', // Natal
];
