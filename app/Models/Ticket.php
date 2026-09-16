<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'ticket_number', 'requester_id', 'company_id', 'project_id', 'site_id',
    'category_id', 'type', 'impact', 'urgency', 'priority', 'priority_overridden',
    'priority_overridden_by_id', 'priority_override_reason', 'status', 'approval_state',
    'assigned_agent_id', 'restricted', 'summary', 'description', 'affected_users_note',
    'deadline_at', 'deadline_reason', 'workaround', 'resolution_code', 'resolution_notes',
    'first_response_at', 'restoration_at', 'resolved_at', 'closed_at', 'requester_confirmed',
    'vendor_id', 'vendor_case_number', 'vendor_update_due_at', 'vendor_closure_evidence',
    'sla_policy_id', 'sla_policy_snapshot', 'major_incident_id', 'idempotency_key', 'reopen_count',
])]
class Ticket extends Model
{
    use HasFactory;

    /**
     * Legal next states per current status (section 6). This is the single
     * source of truth for the lifecycle - TicketController enforces it on
     * every transition request so a direct API call cannot skip it.
     */
    public const ALLOWED_TRANSITIONS = [
        'new' => ['triaged', 'assigned', 'cancelled'],
        'triaged' => ['assigned', 'waiting_approval', 'cancelled'],
        'assigned' => ['in_progress', 'waiting_requester', 'waiting_vendor', 'waiting_approval', 'scheduled', 'cancelled'],
        'in_progress' => ['waiting_requester', 'waiting_vendor', 'waiting_approval', 'scheduled', 'resolved', 'cancelled'],
        'waiting_requester' => ['in_progress', 'resolved', 'cancelled'],
        'waiting_vendor' => ['in_progress', 'resolved', 'cancelled'],
        'waiting_approval' => ['assigned', 'in_progress', 'cancelled'],
        'scheduled' => ['in_progress', 'resolved', 'cancelled'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
        'cancelled' => [],
    ];

    protected function casts(): array
    {
        return [
            'priority_overridden' => 'boolean',
            'restricted' => 'boolean',
            'requester_confirmed' => 'boolean',
            'deadline_at' => 'datetime',
            'first_response_at' => 'datetime',
            'restoration_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'vendor_update_due_at' => 'datetime',
            'sla_policy_snapshot' => 'array',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function majorIncident(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'major_incident_id');
    }

    public function linkedReports(): HasMany
    {
        return $this->hasMany(Ticket::class, 'major_incident_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(TicketShare::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'ticket_asset');
    }

    public function slaClocks(): HasMany
    {
        return $this->hasMany(TicketSlaClock::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function checklistTasks(): HasMany
    {
        return $this->hasMany(ChecklistTask::class);
    }

    public function externalAccessGrant(): HasMany
    {
        return $this->hasMany(ExternalAccessGrant::class);
    }

    public function satisfactionScore(): HasMany
    {
        return $this->hasMany(SatisfactionScore::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved', 'closed', 'cancelled'], true);
    }

    /**
     * Server-side scope applied to every ticket listing (dashboards, search,
     * exports) so a user can never enumerate tickets outside their
     * authorised scope by any route - this is the query-level twin of
     * TicketPolicy::view and must stay consistent with it.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('administrator')) {
            return $query;
        }

        if ($user->vendor_id !== null) {
            return $query->where(function ($q) use ($user) {
                $q->where('vendor_id', $user->vendor_id)
                    ->orWhereHas('shares', fn ($s) => $s->where('user_id', $user->id));
            });
        }

        $companyIds = Company::idsIncludingDescendants($user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->where('scope_type', 'company')->pluck('scope_id'));
        $projectIdsItStaff = $user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->where('scope_type', 'project')->pluck('scope_id');
        $projectIdsPm = $user->activeAccessGrants()->whereIn('role', ['project_manager', 'auditor'])->where('scope_type', 'project')->pluck('scope_id');
        $companyIdsPm = Company::idsIncludingDescendants($user->activeAccessGrants()->whereIn('role', ['project_manager', 'auditor'])->where('scope_type', 'company')->pluck('scope_id'));

        return $query->where(function ($q) use ($user, $companyIds, $projectIdsItStaff, $projectIdsPm, $companyIdsPm) {
            $q->where('requester_id', $user->id)
                ->orWhereHas('shares', fn ($s) => $s->where('user_id', $user->id))
                ->orWhere('assigned_agent_id', $user->id)
                ->orWhere(function ($q2) use ($companyIds, $projectIdsItStaff) {
                    $q2->where(function ($q3) use ($companyIds, $projectIdsItStaff) {
                        $q3->whereIn('company_id', $companyIds)->orWhereIn('project_id', $projectIdsItStaff);
                    });
                })
                ->orWhere(function ($q2) use ($companyIdsPm, $projectIdsPm) {
                    $q2->where('restricted', false)
                        ->where(function ($q3) use ($companyIdsPm, $projectIdsPm) {
                            $q3->whereIn('company_id', $companyIdsPm)->orWhereIn('project_id', $projectIdsPm);
                        });
                });
        });
    }
}
