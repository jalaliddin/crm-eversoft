<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_number', 32);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_type', 20)->default('full_time');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('salary_type', 10)->default('monthly');
            $table->decimal('salary_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'employee_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'department_id']);
            $table->index(['organization_id', 'last_name']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // fixed | shift | flexible
            $table->string('type', 20)->default('fixed');
            // NULL = use organization timezone.
            $table->string('timezone', 64)->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_grace_minutes')->default(0);
            // after_grace: late = actual - (start + grace); from_start: once grace is exceeded, late = actual - start.
            $table->string('late_mode', 20)->default('after_grace');
            $table->unsignedSmallInteger('required_daily_minutes')->nullable();
            $table->boolean('overtime_enabled')->default(false);
            $table->unsignedSmallInteger('overtime_threshold_minutes')->default(0);
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            // ISO-8601: 1 = Monday ... 7 = Sunday
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            // Used for flexible schedules or when break has no fixed window.
            $table->unsignedSmallInteger('break_minutes')->nullable();
            $table->timestamps();

            $table->unique(['work_schedule_id', 'day_of_week']);
        });

        Schema::create('employee_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestamps();

            $table->unique(['organization_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_work_schedules');
        Schema::dropIfExists('work_schedule_days');
        Schema::dropIfExists('work_schedules');
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
