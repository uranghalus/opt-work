<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // A varchar slug, not a uuid: path-based tenancy puts this value in
            // the URL (/{tenant}/work-orders). Every `tenant_id` foreign key must
            // therefore be declared `string`, never `foreignUuid` — MySQL/InnoDB
            // rejects a char(36) column referencing this varchar primary key.
            // See ADR 0002.
            $table->string('id')->primary();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
