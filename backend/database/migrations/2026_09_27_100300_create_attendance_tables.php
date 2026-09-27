<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vendor', 32);
            $table->string('model', 64)->nullable();
            $table->string('serial_number', 64)->nullable();
            // face_terminal | fingerprint | card_reader
            $table->string('type', 32)->default('face_terminal');
            // direct: server pulls from device; bridge: on-site agent pushes events to the API.
            $table->string('connection_mode', 16)->default('direct');
            $table->string('ip_address', 255)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username', 100)->nullable();
            $table->text('encrypted_password')->nullable();
            $table->string('protocol', 10)->default('http');
            $table->string('bridge_token_hash', 64)->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('employee_device_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // NULL device = identifier is valid on every device of the organization.
            $table->foreignId('device_id')->nullable()->constrained('attendance_devices')->cascadeOnDelete();
            $table->string('identifier', 64);
            $table->string('card_number', 64)->nullable();
            $table->string('biometric_identifier', 64)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'device_id', 'identifier'], 'edi_org_device_identifier_unique');
            $table->index(['organization_id', 'identifier'], 'edi_org_identifier_index');
            $table->index(['organization_id', 'card_number'], 'edi_org_card_index');
        });

        Schema::create('attendance_raw_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('attendance_devices')->cascadeOnDelete();
            // Device-supplied id, or "h:<sha1>" deterministic hash when the device supplies none.
            $table->string('external_event_id', 100);
            $table->string('employee_identifier', 64)->nullable();
            $table->string('card_number', 64)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 20)->default('unknown');
            $table->timestamp('event_time');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            // pending | processed | unmatched | failed | ignored
            $table->string('processing_status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'external_event_id']);
            $table->index(['organization_id', 'processing_status']);
            $table->index(['organization_id', 'employee_id', 'event_time'], 'are_org_employee_time_index');
            $table->index(['organization_id', 'event_time']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Business date in the tenant/schedule timezone.
            $table->date('date');
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            // Instants below are stored in UTC.
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('actual_check_in')->nullable();
            $table->timestamp('actual_check_out')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status', 20);
            $table->string('source', 20)->default('biometric');
            $table->foreignId('device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
            $table->foreignId('raw_event_id')->nullable()->constrained('attendance_raw_events')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->string('edit_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['organization_id', 'date', 'status']);
            $table->index(['organization_id', 'employee_id', 'date']);
        });

        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('action', 20);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_audit_logs');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_raw_events');
        Schema::dropIfExists('employee_device_identifiers');
        Schema::dropIfExists('attendance_devices');
    }
};
