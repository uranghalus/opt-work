<?php

namespace App;

enum WorkOrderStatus: string
{
    case Draft = 'draft';

    /**
     * Gerbang approval lintas-department (R1): WO ke department lain
     * menunggu persetujuan department PENGAJU sebelum dirilis. Dept tujuan
     * tidak melihat WO pada status ini (R8).
     */
    case PendingDeptApproval = 'pending_dept_approval';

    /**
     * Ditolak approver department pengaju (alasan wajib, R4) — requester
     * bisa merevisi lalu mengajukan ulang, atau membatalkan.
     */
    case RejectedByDept = 'rejected_by_dept';

    case WaitingHod = 'waiting_hod';

    case HodApproved = 'hod_approved';

    case Scheduled = 'scheduled';

    case Assigned = 'assigned';

    case InProgress = 'in_progress';

    case PendingVerify = 'pending_verify';

    case Revision = 'revision';

    case Closed = 'closed';

    /**
     * Terminal (R4/R12): dibatalkan requester/approver department pengaju
     * sebelum rilis, atau reviewer department tujuan sebagai duplikat
     * (kasus WO yang sama diterima dari dua department berbeda).
     */
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
            self::Draft => 'Draf',
            self::WaitingHod => 'Menunggu HOD',
            self::PendingDeptApproval => 'Menunggu Persetujuan Dept Pengaju',
            self::RejectedByDept => 'Ditolak Dept Pengaju',
            self::Cancelled => 'Dibatalkan',
            self::HodApproved => 'Disetujui HOD',
            self::Scheduled => 'Terjadwal',
            self::Assigned => 'Ditugaskan',
            self::InProgress => 'Dikerjakan',
            self::PendingVerify => 'Menunggu Verifikasi',
            self::Revision => 'Revisi',
            self::Closed => 'Selesai',
        };
    }
}
