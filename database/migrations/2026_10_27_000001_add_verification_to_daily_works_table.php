<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verifikasi Daily Work (design .scratch/daily-work-verification/design.md):
 * `done` kini hanya dicapai lewat submit bukti + verifikasi HOD/TL.
 * Kolom nullable semua — baris legacy `done` pra-fitur tetap sah tanpa
 * verifikator; tanpa backfill destruktif. Index (tenant_id, status,
 * work_date) melayani inbox "Perlu Verifikasi".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_works', function (Blueprint $table) {
            $table->text('proof_notes')->nullable()->after('done_at');
            // Path foto di disk s3 (dir daily-work/{tenantKey}), maks 3.
            $table->json('proof_attachments')->nullable()->after('proof_notes');
            $table->timestamp('submitted_at')->nullable()->after('proof_attachments');
            $table->foreignId('verified_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->foreignId('rejected_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_note')->nullable()->after('rejected_at');

            $table->index(['tenant_id', 'status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_works', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'work_date']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'proof_notes', 'proof_attachments', 'submitted_at',
                'verified_at', 'rejected_at', 'rejection_note',
            ]);
        });
    }
};
