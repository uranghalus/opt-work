<?php

namespace App;

enum WorkOrderJobStatus: string
{
    case Assigned = 'assigned';

    case InProgress = 'in_progress';

    case PendingVerify = 'pending_verify';

    case Revision = 'revision';

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
            self::Assigned => 'Ditugaskan',
            self::InProgress => 'Dikerjakan',
            self::PendingVerify => 'Menunggu Verifikasi',
            self::Revision => 'Revisi',
        };
    }
}
