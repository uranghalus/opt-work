<?php

namespace App;

/**
 * Aksi yang tercatat di tabel audit_logs (grill 2026-10-21, keputusan #4).
 * Cakupan awal sengaja sempit: tiga aksi akun + dua aksi ban. Event lain
 * (eskalasi, dsb.) menyusul lewat keputusan terpisah, bukan diam-diam.
 */
enum AuditAction: string
{
    case AkunLink = 'akun.link';

    case AkunUnlink = 'akun.unlink';

    case AkunRolesSync = 'akun.roles.sync';

    case EmployeeBan = 'employee.ban';

    case EmployeeUnban = 'employee.unban';

    public function label(): string
    {
        return match ($this) {
            self::AkunLink => 'Akun ditautkan',
            self::AkunUnlink => 'Tautan akun dilepas',
            self::AkunRolesSync => 'Peran akun diubah',
            self::EmployeeBan => 'Karyawan diblokir',
            self::EmployeeUnban => 'Blokir karyawan dibatalkan',
        };
    }
}
