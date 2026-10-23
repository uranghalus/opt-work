<?php

namespace App;

/**
 * Status sesi WhatsApp tenant — hasil pemantauan `app:waha-sync-sessions`
 * dari WAHA, ditambah kondisi lokal. Nilai = status WAHA dipetakan huruf
 * kecil; hanya `Working` yang boleh mengirim pesan.
 */
enum TenantWaSessionStatus: string
{
    case Stopped = 'stopped';

    case Starting = 'starting';

    case ScanQr = 'scan_qr';

    case Working = 'working';

    case Stopping = 'stopping';

    case Error = 'error';

    /** Ada di database tenant tetapi tidak muncul lagi di daftar sesi WAHA. */
    case Missing = 'missing';

    /** Satu-satunya status yang boleh menjadi sesi pengirim. */
    public function isActive(): bool
    {
        return $this === self::Working;
    }

    public function label(): string
    {
        return match ($this) {
            self::Stopped => 'Terputus',
            self::Starting => 'Memulai',
            self::ScanQr => 'Menunggu pemindaian QR',
            self::Working => 'Terhubung',
            self::Stopping => 'Memutuskan',
            self::Error => 'Galat',
            self::Missing => 'Tidak ada di WAHA',
        };
    }

    /**
     * Petakan status mentah WAHA (WORKING, SCAN_QR_CODE, STARTING, FAILED, …).
     * Status yang tidak dikenal dianggap Error — jangan pernah dianggap aktif.
     */
    public static function fromWahaStatus(string $raw): self
    {
        return match (strtoupper(trim($raw))) {
            'WORKING', 'CONNECTED' => self::Working,
            'SCAN_QR_CODE', 'SCAN_QR' => self::ScanQr,
            'STARTING', 'INITIALIZING' => self::Starting,
            'STOPPING' => self::Stopping,
            'STOPPED' => self::Stopped,
            default => self::Error,
        };
    }
}
