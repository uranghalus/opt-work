<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Promotes branch identity out of the `data` JSON column into real columns.
     * The JSON path was unusable: `VirtualColumn::encodeAttributes()` discards the
     * `data` attribute it is handed on `creating` and replaces it with the
     * remaining virtual attributes, so `TenantSeeder`'s payload never persisted
     * and every branch ended up with `data = '[]'`.
     *
     * `id` stays the human-readable slug and the URL segment — see ADR 0002.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
            $table->string('code')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('code');
        });

        // Backfill before tightening nullability. `data` is empty on every row
        // created by the current seeder, so the id fallback is the real path.
        DB::table('tenants')->orderBy('id')->each(function (object $row): void {
            $data = json_decode((string) ($row->data ?? '[]'), true) ?: [];

            DB::table('tenants')->where('id', $row->id)->update([
                'name' => $data['nama_cabang'] ?? $row->id,
                'code' => $data['kode_cabang'] ?? null,
            ]);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
            $table->unique('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropUnique(['code']);
            $table->dropColumn(['name', 'code', 'is_active']);
        });
    }
};
