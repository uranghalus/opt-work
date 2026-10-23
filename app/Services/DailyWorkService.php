<?php

namespace App\Services;

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * FR-5.x Daily Work — materialize-on-view: item template aktif yang cocok
 * dengan hari pada tanggal D di-upsert idempoten menjadi baris DailyWork
 * saat daftar harian dibuka (design .scratch/daily-work/design.md, opsi A).
 *
 * Kontrak: kolom progres (status/location/done_at) TIDAK PERNAH ditimpa —
 * firstOrCreate hanya mengisi kolom turunan template saat baris belum ada.
 * Unique (template_id, work_date) mencegah dobel saat dua request bersamaan.
 */
class DailyWorkService
{
    /**
     * Materialize instance harian untuk satu karyawan pada satu tanggal.
     * Idempoten: dipanggil berulang untuk tanggal yang sama tidak
     * menduplikasi maupun mengubah baris yang sudah ada.
     */
    public function materializeFor(Employee $employee, CarbonImmutable $date): void
    {
        $templates = DailyWorkTemplate::query()
            ->where('employee_id', $employee->getKey())
            ->where('is_active', true)
            ->get();

        if ($templates->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($templates, $employee, $date): void {
            foreach ($templates as $template) {
                // Carbon dayOfWeek: 0=Minggu .. 6=Sabtu (sama dengan isi weekdays).
                if (! in_array($date->dayOfWeek, $template->weekdays, true)) {
                    continue;
                }

                // Match pakai instance Carbon (bukan string Y-m-d): sqlite
                // menyimpan kolom date sebagai 'Y-m-d H:i:s' — binding Carbon
                // cocok di sqlite DAN di MySQL (DATE truncates time part).
                DailyWork::query()->firstOrCreate(
                    [
                        'template_id' => $template->getKey(),
                        'work_date' => $date->startOfDay(),
                    ],
                    [
                        'employee_id' => $employee->getKey(),
                        'title' => $template->title,
                        'description' => $template->description,
                        'source' => 'template',
                    ],
                );
            }
        });
    }
}
