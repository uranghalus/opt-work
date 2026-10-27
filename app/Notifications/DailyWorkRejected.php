<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\DailyWork;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke akun karyawan ketika HOD/TL mengembalikan bukti Daily Work
 * untuk diperbaiki (rejection_note wajib — plan
 * .scratch/daily-work-verification/plan.md Task 4).
 */
class DailyWorkRejected extends Notification
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
            'type' => 'daily_work.rejected',
            'daily_work_id' => $this->dailyWork->getKey(),
            'work_date' => $this->dailyWork->work_date->toDateString(),
            'title' => $this->dailyWork->title,
            'rejection_note' => $this->dailyWork->rejection_note,
            'message' => sprintf(
                'Daily Work "%s" untuk %s dikembalikan: %s',
                $this->dailyWork->title,
                $this->dailyWork->work_date->translatedFormat('d M Y'),
                str((string) $this->dailyWork->rejection_note)->limit(160),
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
            '↩️ *DAILY WORK DIKEMBALIKAN*',
            '',
            '*Pekerjaan:* '.$this->dailyWork->title,
            '*Tanggal:* '.$this->dailyWork->work_date->translatedFormat('l, d M Y'),
            '*Alasan:* '.str((string) $this->dailyWork->rejection_note)->limit(160),
        ];

        $lines[] = '';
        $lines[] = 'Perbaiki bukti di daftar harian:';

        $lines[] = route('daily-work.index', [
            'tenant' => $this->dailyWork->tenant?->code,
            'date' => $this->dailyWork->work_date->toDateString(),
        ]);

        return implode("\n", $lines);
    }

    public function waEvent(): string
    {
        return 'daily_work.rejected';
    }
}
