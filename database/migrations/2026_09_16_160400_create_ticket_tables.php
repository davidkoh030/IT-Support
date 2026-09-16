<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // immutable public identifier, e.g. TCK-000123
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete(); // null = HQ/shared-services ticket
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->enum('type', ['incident', 'service_request'])->default('incident');
            $table->enum('impact', ['high', 'medium', 'low']);
            $table->enum('urgency', ['high', 'medium', 'low']);
            $table->enum('priority', ['P1', 'P2', 'P3', 'P4']);
            $table->boolean('priority_overridden')->default(false);
            $table->foreignId('priority_overridden_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority_override_reason')->nullable();
            $table->enum('status', [
                'new', 'triaged', 'assigned', 'in_progress',
                'waiting_requester', 'waiting_vendor', 'waiting_approval', 'scheduled',
                'resolved', 'closed', 'cancelled',
            ])->default('new');
            $table->enum('approval_state', ['not_required', 'pending', 'approved', 'rejected'])->default('not_required');
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('restricted')->default(false); // security incident / sensitive HR-account request
            $table->string('summary');
            $table->text('description');
            $table->string('affected_users_note')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->string('deadline_reason')->nullable();
            $table->text('workaround')->nullable();
            $table->string('resolution_code')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('restoration_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('requester_confirmed')->default(false);
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vendor_case_number')->nullable();
            $table->timestamp('vendor_update_due_at')->nullable();
            $table->text('vendor_closure_evidence')->nullable();
            $table->foreignId('sla_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->json('sla_policy_snapshot')->nullable(); // targets frozen at creation time
            $table->foreignId('major_incident_id')->nullable()->constrained('tickets')->nullOnDelete(); // link reports to one major incident
            $table->string('idempotency_key')->unique(); // one submission = one ticket, even on retry
            $table->unsignedInteger('reopen_count')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type'); // status_change, priority_override, assignment, approval_decision, sla_pause, escalation, ...
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('body');
            $table->enum('visibility', ['public', 'internal'])->default('public');
            $table->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('ticket_comments')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users');
            $table->string('disk')->default('private');
            $table->string('path'); // random storage name, never the original filename
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        // Explicit sharing: a requester's own ticket plus anything shared with them;
        // a vendor sees only tickets assigned to them plus anything shared with them.
        Schema::create('ticket_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_asset', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->primary(['ticket_id', 'asset_id']);
        });

        // One row per tracked clock per ticket (first_response, restoration).
        Schema::create('ticket_sla_clocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('metric', ['first_response', 'restoration']);
            $table->timestamp('target_at');
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason')->nullable();
            $table->timestamp('warned_at')->nullable(); // pre-breach notice sent (dedup)
            $table->timestamp('breached_notified_at')->nullable(); // breach notice sent (dedup)
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'metric']);
        });

        Schema::create('satisfaction_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1-5
            $table->string('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_scores');
        Schema::dropIfExists('ticket_sla_clocks');
        Schema::dropIfExists('ticket_asset');
        Schema::dropIfExists('ticket_shares');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('tickets');
    }
};
