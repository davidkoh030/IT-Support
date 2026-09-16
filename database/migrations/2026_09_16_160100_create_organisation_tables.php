<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-referencing parent lets one row model the group and its legal
        // companies without a separate "groups" table: a company with no
        // parent is the group/holding entity.
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('country_code', 2)->default('SG');
            $table->string('timezone')->default('Asia/Singapore');
            $table->string('currency', 3)->default('SGD');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->default('Asia/Singapore');
            // ISO-8601 day numbers (1 = Monday .. 7 = Sunday) that are working days.
            $table->json('working_days')->default(json_encode([1, 2, 3, 4, 5]));
            $table->time('start_time')->default('08:30:00');
            $table->time('end_time')->default('17:30:00');
            $table->timestamps();
        });

        Schema::create('calendar_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestamps();
            $table->unique(['calendar_id', 'date']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('lifecycle_stage', ['proposed', 'mobilising', 'active', 'demobilising', 'closed'])->default('proposed');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('information_manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('timezone')->default('Asia/Singapore');
            $table->string('currency', 3)->default('SGD');
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['hq', 'site_office', 'warehouse', 'precast_plant'])->default('site_office');
            $table->string('address')->nullable();
            $table->string('area_block_floor_zone')->nullable();
            $table->time('operating_hours_start')->nullable();
            $table->time('operating_hours_end')->nullable();
            $table->string('access_contact_name')->nullable();
            $table->string('access_contact_phone')->nullable();
            $table->foreignId('support_calendar_id')->nullable()->constrained('calendars')->nullOnDelete();
            $table->timestamps();
        });

        // A project can span multiple sites and a site can serve multiple projects.
        Schema::create('project_site', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'site_id']);
        });

        // Scoped, time-bound access: who may act as what role against which
        // company or project. Expiry/revocation here is what actually removes
        // access (enforced in policies), not just a UI toggle.
        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('scope_type', ['company', 'project']);
            $table->unsignedBigInteger('scope_id');
            $table->enum('role', ['member', 'project_manager', 'it_agent', 'it_manager', 'approver', 'auditor']);
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_grants');
        Schema::dropIfExists('project_site');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('calendar_holidays');
        Schema::dropIfExists('calendars');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');
    }
};
