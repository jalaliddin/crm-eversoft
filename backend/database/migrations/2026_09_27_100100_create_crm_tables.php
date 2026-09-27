<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('individual');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('secondary_phone', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('source', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'phone']);
            $table->index(['organization_id', 'company_name']);
            $table->index(['organization_id', 'last_name']);
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->string('color', 20)->default('blue');
            $table->unsignedTinyInteger('probability')->default(0);
            // open | won | lost — lets tenants rename stages while reports stay correct.
            $table->string('type', 10)->default('open');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['pipeline_id', 'key']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('source', 50)->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('expected_value', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->json('custom_fields')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('value', 15, 2)->default(0);
            $table->char('currency', 3)->default('UZS');
            // Denormalized from stage type for fast reporting: open | won | lost.
            $table->string('status', 10)->default('open');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->date('expected_close_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->json('custom_fields')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'pipeline_stage_id']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('pending');
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status', 'due_at']);
            $table->index(['organization_id', 'assigned_to', 'status']);
            $table->index(['organization_id', 'type']);
        });

        // Reusable timeline. An event is linked to every CRM entity whose timeline should show it.
        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->nullableMorphs('subject');
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['organization_id', 'customer_id', 'occurred_at']);
            $table->index(['organization_id', 'lead_id', 'occurred_at']);
            $table->index(['organization_id', 'deal_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};
