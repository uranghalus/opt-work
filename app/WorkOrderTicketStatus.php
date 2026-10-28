<?php

namespace App;

enum WorkOrderTicketStatus: string
{
    case PendingDeptApproval = 'pending_dept_approval';

    case RejectedByDept = 'rejected_by_dept';

    case WaitingHod = 'waiting_hod';

    case HodApproved = 'hod_approved';

    case Scheduled = 'scheduled';

    case Closed = 'closed';

    case Cancelled = 'cancelled';

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
            self::PendingDeptApproval => 'Menunggu Persetujuan Dept Pengaju',
            self::RejectedByDept => 'Ditolak Dept Pengaju',
            self::WaitingHod => 'Menunggu HOD',
            self::HodApproved => 'Disetujui HOD',
            self::Scheduled => 'Terjadwal',
            self::Closed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
