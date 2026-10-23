<?php

namespace App\Notifications;

use App\Channels\WahaWhatsAppChannel;
use App\Models\DailyWork;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the linked akun of a karyawan when the HOD assigns an extra
 * (adhoc) Daily Work for a specific date (FR-5.2). Template materialize
 * is NOT notified (not a new assignment — see .scratch/daily-work/design.md).
 */
class DailyWorkAssigned extends Notification
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
            'type' => 'daily_work.assigned',
            'daily_work_id' => $this->dailyWork->getKey(),
            'work_date' => $this->dailyWork->work_date->toDateString(),
            'title' => $this->dailyWork->title,
            'location' => $this->dailyWork->location,
            'message' => sprintf(
                'Tugas tambahan "%s" untuk %s ditambahkan HOD.',
                $this->dailyWork->title,
                $this->dailyWork->work_date->translatedFormat('d M Y'),
            ),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Outbox WhatsApp (pola ADR-0009; no-op tanpa WAHA_URL): tugas tambahan
     * harian harus segera diketahui pelaksana di lapangan.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '🧰 *TUGAS HARIAN BARU*',
            '',
            '*Tugas:* '.$this->dailyWork->title,
            '*Tanggal:* '.$this->dailyWork->work_date->translatedFormat('l, d M Y'),
        ];

        if (filled($this->dailyWork->location)) {
            $lines[] = '*Lokasi:* '.$this->dailyWork->location;
        }

        if (filled($this->dailyWork->description)) {
            $lines[] = '*Ket:* '.str($this->dailyWork->description)->limit(120);
        }

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
        return 'daily_work.assigned';
    }
}
