<?php

namespace App;

enum ExtendRequestStatus: string
{
    case PendingTeamLeader = 'pending_team_leader';

    case PendingHod = 'pending_hod';

    case Approved = 'approved';

    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingTeamLeader => 'Menunggu Team Leader',
            self::PendingHod => 'Menunggu HOD',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * Status yang masih menunggu keputusan approver — dipakai untuk blokir
     * pending duplikat per WO (perbaikan atas gap opti-work2).
     *
     * @return array<int, self>
     */
    public static function pendingStates(): array
    {
        return [self::PendingTeamLeader, self::PendingHod];
    }

    public function isPending(): bool
    {
        return in_array($this, self::pendingStates(), true);
    }
}
