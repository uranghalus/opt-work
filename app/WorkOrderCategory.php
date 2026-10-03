<?php

namespace App;

enum WorkOrderCategory: string
{
    case Normal = 'normal';

    case Accident = 'accident';

    case Owner = 'owner';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category) => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Accident => 'Urgent · Kecelakaan',
            self::Owner => 'Urgent · Owner',
        };
    }

    /**
     * Urgent by Accident wajib dieksekusi langsung — sistem tidak
     * menampilkan opsi jadwal untuk kategori ini.
     */
    public function allowsScheduling(): bool
    {
        return $this !== self::Accident;
    }
}
