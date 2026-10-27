<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\DailyWork;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke akun karyawan ketika HOD/TL menandai bukti Daily Work
 * telah selesai (plan .scratch/daily-work-verification/plan.md Task 4).
 */
class DailyWorkVerified extends Notification
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
            'type' => 'daily_work.verified',
            'daily_work_id' => $this->dailyWork->getKey(),
            'work_date' => $this->dailyWork->work_date->toDateString(),
            'title' => $this->dailyWork->title,
            'verified_by_name' => $this->dailyWork->verifier?->name,
            'message' => sprintf(
                'Daily Work "%s" untuk %s ditandai telah selesai oleh %s.',
                $this->dailyWork->title,
                $this->dailyWork->work_date->translatedFormat('d M Y'),
                $this->dailyWork->verifier?->name ?? 'HOD',
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (pola ADR-0009; no-op tanpa WAHA_URL).
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '✅ *DAILY WORK TERVERIFIKASI*',
            '',
            '*Pekerjaan:* '.$this->dailyWork->title,
            '*Tanggal:* '.$this->dailyWork->work_date->translatedFormat('l, d M Y'),
            '*Diverifikasi:* '.$this->dailyWork->verifier?->name,
        ];

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
        return 'daily_work.verified';
    }
}
