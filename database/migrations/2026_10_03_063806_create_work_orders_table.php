<?php

use App\WorkOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->string('nomor_wo', 50);
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('target_department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('title');
            $table->text('description');
            $table->json('attachments')->nullable();
            $table->string('status', 50)->default(WorkOrderStatus::WaitingHod->value)->index();
            $table->date('requested_schedule_date')->nullable();
            $table->date('deadline_date')->nullable();
            $table->timestamp('escalation_h3_sent_at')->nullable();
            $table->timestamp('escalation_h5_sent_at')->nullable();
            $table->timestamp('escalation_h6_sent_at')->nullable();
            $table->boolean('is_escalated')->default(false);
            $table->unsignedInteger('extend_count')->default(0);
            $table->text('extend_reason')->nullable();
            $table->timestamp('extended_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'nomor_wo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
