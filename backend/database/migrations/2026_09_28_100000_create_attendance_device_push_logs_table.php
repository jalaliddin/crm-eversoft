<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every request a terminal pushes is recorded (without pictures) so the real payload
        // format can be verified and delivery problems diagnosed from the UI.
        Schema::create('attendance_device_push_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('attendance_devices')->cascadeOnDelete();
            // accepted | heartbeat | ignored | unparsed | rejected | failed
            $table->string('status', 20);
            $table->string('event_type', 64)->nullable();
            $table->unsignedSmallInteger('events_count')->default(0);
            $table->unsignedSmallInteger('stored_count')->default(0);
            $table->string('summary', 255)->nullable();
            $table->string('content_type', 191)->nullable();
            $table->string('remote_ip', 45)->nullable();
            $table->text('body_excerpt')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['device_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });

        // Hikvision event serial numbers wrap around (range 1–100000), so "sn:<serial>" alone is not unique
        // over a device's lifetime. Keys now include the event time; convert existing rows to the new form.
        DB::table('attendance_raw_events')
            ->where('external_event_id', 'like', 'sn:%')
            ->where('external_event_id', 'not like', 'sn:%:%')
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('attendance_raw_events')->where('id', $row->id)->update([
                        'external_event_id' => $row->external_event_id.':'.date('YmdHis', strtotime($row->event_time)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_push_logs');
    }
};
