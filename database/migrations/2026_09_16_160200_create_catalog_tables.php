<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['incident', 'service_request'])->default('incident');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('support_hours')->nullable();
            $table->string('warranty_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('priority', ['P1', 'P2', 'P3', 'P4']);
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('restoration_minutes');
            $table->foreignId('calendar_id')->constrained('calendars');
            // Null = the default policy for this priority; a company-specific
            // row (e.g. a Malaysia legal entity on its own calendar) takes
            // precedence over the default when both exist for a priority.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Admin-editable impact x urgency -> priority matrix (section 7).
        Schema::create('priority_matrix_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('impact', ['high', 'medium', 'low']);
            $table->enum('urgency', ['high', 'medium', 'low']);
            $table->enum('priority', ['P1', 'P2', 'P3', 'P4']);
            $table->timestamps();
            $table->unique(['impact', 'urgency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_matrix_rules');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('categories');
    }
};
