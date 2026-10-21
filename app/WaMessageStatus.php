<?php

namespace App;

/**
 * Status hidup satu baris outbox WhatsApp (ADR-0009). Transisi hanya maju:
 * pending → sent | failed; skipped final sejak pembuatan (nomor kosong /
 * tidak ternormalisasi — retry tidak akan membuat nomor valid).
 */
enum WaMessageStatus: string
{
    case Pending = 'pending';

    case Sent = 'sent';

    case Failed = 'failed';

    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pengiriman',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
        };
    }
}
