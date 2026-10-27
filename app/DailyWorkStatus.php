<?php

namespace App;

enum DailyWorkStatus: string
{
    case Pending = 'pending';

    case InProgress = 'in_progress';

    /**
     * Bukti kerja (catatan + foto) sudah diserahkan karyawan — menunggu
     * verifikasi HOD/TL (plan .scratch/daily-work-verification/plan.md).
     */
    case PendingVerify = 'pending_verify';

    /**
     * Terverifikasi HOD/TL ("Tandai telah selesai"). Baris legacy pra-fitur
     * memakai nilai ini tanpa verified_by — sah, tanpa backfill.
     */
    case Done = 'done';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum',
            self::InProgress => 'Proses',
            self::PendingVerify => 'Perlu verifikasi HOD',
            self::Done => 'Telah selesai',
        };
    }
}
