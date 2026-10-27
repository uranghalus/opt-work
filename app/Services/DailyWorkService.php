<?php

namespace App\Services;

use App\DailyWorkStatus;
use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\DailyWorkRejected;
use App\Notifications\DailyWorkSubmitted;
use App\Notifications\DailyWorkVerified;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /**
     * Terima bukti kerja karyawan (plan .scratch/daily-work-verification/
     * plan.md Task 2, design opsi S1): status naik ke pending_verify —
     * HOD/TL selanjutnya memverifikasi. Item yang baru saja dikembalikan
     * (rejected_*) dibersihkan di sini agar submit ulang bersih. 409 bila
     * item tidak dalam state yang boleh submit (already pending_verify /
     * done).
     */
    public function submit(DailyWork $work, string $notes, array $attachments): DailyWork
    {
        return DB::transaction(function () use ($work, $notes, $attachments): DailyWork {
            abort_unless(
                in_array($work->status, [DailyWorkStatus::Pending->value, DailyWorkStatus::InProgress->value], true),
                409,
            );

            $work->update([
                'status' => DailyWorkStatus::PendingVerify->value,
                'proof_notes' => $notes,
                'proof_attachments' => $attachments === [] ? null : $attachments,
                'submitted_at' => now(),
                'done_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ]);

            // Task 4 — seluruh scope verifikasi (HOD + manager department,
            // TL department sama) diberi tahu (pola WO submit line ~329).
            app(DailyWorkVerifierScope::class)
                ->verifiersFor($work)
                ->each(fn (User $recipient) => $recipient->notify(new DailyWorkSubmitted($work)));

            return $work;
        });
    }

    /**
     * Task 3 — HOD/TL "Tandai telah selesai": scope (canVerify → 403)
     * + state pending_verify saja (→ 409). done_at TIDAK disentuh —
     * tetap momen submit bukti (design S1).
     */
    public function verify(User $actor, DailyWork $work): DailyWork
    {
        return DB::transaction(function () use ($actor, $work): DailyWork {
            abort_unless(app(DailyWorkVerifierScope::class)->canVerify($actor, $work), 403);
            abort_unless($work->status === DailyWorkStatus::PendingVerify->value, 409);

            $work->update([
                'status' => DailyWorkStatus::Done->value,
                'verified_by' => $actor->getKey(),
                'verified_at' => now(),
            ]);

            // Task 4 — akun karyawan (skip + log bila tidak ter-link,
            // pola adhocStore line ~264).
            $this->notifyEmployee($work, new DailyWorkVerified($work));

            return $work;
        });
    }

    /**
     * Task 3 — kembalikan bukti ke karyawan (rejection_note wajib dari
     * controller): status turun ke in_progress, kolom reject terisi,
     * bukti TIDAK dihapus (jejak untuk karyawan melihat ulang).
     */
    public function reject(User $actor, DailyWork $work, string $note): DailyWork
    {
        return DB::transaction(function () use ($actor, $work, $note): DailyWork {
            abort_unless(app(DailyWorkVerifierScope::class)->canVerify($actor, $work), 403);
            abort_unless($work->status === DailyWorkStatus::PendingVerify->value, 409);

            $work->update([
                'status' => DailyWorkStatus::InProgress->value,
                'rejected_by' => $actor->getKey(),
                'rejected_at' => now(),
                'rejection_note' => $note,
            ]);

            $this->notifyEmployee($work, new DailyWorkRejected($work));

            return $work;
        });
    }

    /**
     * Notifikasi ke akun karyawan pemilik item — tanpa akun ter-link,
     * hanya log warning (item tetap sah; karyawan melihat status di
     * halaman harian saat login berikutnya).
     */
    private function notifyEmployee(DailyWork $work, object $notification): void
    {
        $recipient = $work->employee?->user;

        if ($recipient === null) {
            Log::warning('Daily Work verification outcome reached an employee without a linked akun; no in-app notification possible.', [
                'daily_work_id' => $work->getKey(),
                'employee_id' => $work->employee_id,
            ]);

            return;
        }

        $recipient->notify($notification);
    }
}
