<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_penalty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // late | early_leave | absence | manual
            $table->string('type', 20);
            $table->unsignedInteger('min_minutes')->default(0);
            $table->unsignedInteger('max_minutes')->nullable();
            // fixed | per_minute | percent_of_daily | daily_salary
            $table->string('calculation', 20);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'type', 'is_active']);
        });

        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('penalty_rule_id')->nullable()->constrained('attendance_penalty_rules')->nullOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('reason')->nullable();
            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');
            $table->boolean('is_automatic')->default(false);
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Idempotency for automatically generated penalties (manual ones have NULL attendance id or type=manual).
            $table->unique(['attendance_record_id', 'type']);
            $table->index(['organization_id', 'employee_id', 'date']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            // open | calculated | approved | closed
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'year', 'month']);
        });

        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('salary_type', 10);
            $table->decimal('rate', 15, 2);
            $table->decimal('base_salary', 15, 2);
            $table->unsignedSmallInteger('scheduled_days')->default(0);
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->unsignedSmallInteger('worked_days')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedSmallInteger('absence_days')->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('bonus_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->char('currency', 3);
            // draft | calculated | approved | paid | cancelled
            $table->string('status', 20)->default('draft');
            $table->json('details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['organization_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('penalties');
        Schema::dropIfExists('attendance_penalty_rules');
    }
};
