<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\DailyWork;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke scope verifikasi (HOD + manager department karyawan, TL
 * department sama) saat karyawan menyerahkan bukti kerja Daily Work
 * (plan .scratch/daily-work-verification/plan.md Task 4).
 */
class DailyWorkSubmitted extends Notification
{
    use Queueable;

    public function __construct(public DailyWork $dailyWork) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WahaWhatsAppChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'daily_work.submitted',
            'daily_work_id' => $this->dailyWork->getKey(),
            'work_date' => $this->dailyWork->work_date->toDateString(),
            'title' => $this->dailyWork->title,
            'employee_name' => $this->dailyWork->employee?->nama_employee,
            'message' => sprintf(
                'Bukti "%s" dari %s untuk %s menunggu verifikasi HOD.',
                $this->dailyWork->title,
                $this->dailyWork->employee?->nama_employee ?? 'karyawan',
                $this->dailyWork->work_date->translatedFormat('d M Y'),
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (pola ADR-0009; no-op tanpa WAHA_URL): bukti harus
     * segera diverifikasi agar item tidak menggantung.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '🧾 *BUKTI DAILY WORK MENUNGGU VERIFIKASI*',
            '',
            '*Pekerjaan:* '.$this->dailyWork->title,
            '*Karyawan:* '.$this->dailyWork->employee?->nama_employee,
            '*Tanggal:* '.$this->dailyWork->work_date->translatedFormat('l, d M Y'),
            '*Catatan:* '.str((string) $this->dailyWork->proof_notes)->limit(120),
        ];

        $attachments = $this->dailyWork->proof_attachments ?? [];

        $lines[] = '*Lampiran:* '.count($attachments).' foto';

        $lines[] = '';
        $lines[] = 'Lihat daftar harian:';

        $lines[] = route('daily-work.index', [
            'tenant' => $this->dailyWork->tenant?->code,
            'date' => $this->dailyWork->work_date->toDateString(),
        ]);

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'daily_work.submitted';
    }
}
