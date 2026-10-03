<?php

namespace App;

enum WorkOrderStatus: string
{
    case Draft = 'draft';

    case WaitingHod = 'waiting_hod';

    case Scheduled = 'scheduled';

    case Assigned = 'assigned';

    case InProgress = 'in_progress';

    case PendingVerify = 'pending_verify';

    case Revision = 'revision';

    case Closed = 'closed';

    /**
     * @return array<string, string>
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
            self::Draft => 'Draf',
            self::WaitingHod => 'Menunggu HOD',
            self::Scheduled => 'Terjadwal',
            self::Assigned => 'Ditugaskan',
            self::InProgress => 'Dikerjakan',
            self::PendingVerify => 'Menunggu Verifikasi',
            self::Revision => 'Revisi',
            self::Closed => 'Selesai',
        };
    }
}
