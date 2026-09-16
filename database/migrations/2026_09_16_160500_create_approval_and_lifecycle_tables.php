<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic so both tickets and (later) other approvable records can
        // carry one or more approval steps, kept separate from ticket.status
        // so an approval can never silently authorise unrelated work.
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->enum('approver_role', ['resource_owner', 'manager', 'information_manager', 'finance']);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('decision', ['pending', 'approved', 'rejected', 'withdrawn', 'expired'])->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('threshold_amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
        });

        // Joiner/transfer/leaver/mobilisation/demobilisation checklist items.
        // A completed task records that a responsible person performed an
        // action; it does not imply an external system API executed it.
        Schema::create('checklist_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('assigned_role')->nullable();
            $table->enum('status', ['pending', 'completed', 'not_applicable'])->default('pending');
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('evidence_note')->nullable();
            $table->timestamps();
        });

        Schema::create('external_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_user_id')->constrained('users');
            $table->string('external_party_name');
            $table->string('employer')->nullable();
            $table->string('resource');
            $table->string('access_level');
            $table->string('business_purpose')->nullable();
            $table->date('expiry_date');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('audience')->nullable(); // free-text role/audience label
            $table->date('review_date')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('external_access_grants');
        Schema::dropIfExists('checklist_tasks');
        Schema::dropIfExists('approvals');
    }
};
