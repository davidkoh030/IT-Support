<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'phone', 'preferred_contact', 'vendor_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * Active (not expired, not revoked) scoped roles for this user, e.g.
     * project_manager on project 4, it_agent on company 1.
     */
    public function activeAccessGrants()
    {
        return $this->accessGrants()
            ->whereNull('revoked_at')
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString());
            });
    }

    /**
     * For company scope, a grant on $scopeId or any of its ancestors (e.g.
     * the group) counts - group-level IT/PM/auditor access covers every
     * subsidiary without a grant per company.
     */
    public function hasScopedRole(string $role, string $scopeType, int $scopeId): bool
    {
        $candidateIds = $scopeType === 'company' ? Company::ancestorChain($scopeId) : [$scopeId];

        return $this->activeAccessGrants()
            ->where('role', $role)
            ->where('scope_type', $scopeType)
            ->whereIn('scope_id', $candidateIds)
            ->exists();
    }

    /**
     * Projects this user may raise/view tickets against - any active grant
     * (plain membership or an elevated role) scoped to the project itself,
     * or to its owning company.
     */
    public function authorisedProjects()
    {
        $projectIds = $this->activeAccessGrants()->where('scope_type', 'project')->pluck('scope_id');
        $companyIds = $this->activeAccessGrants()->where('scope_type', 'company')->pluck('scope_id');

        return Project::query()
            ->where(function ($q) use ($projectIds, $companyIds) {
                $q->whereIn('id', $projectIds)->orWhereIn('company_id', $companyIds);
            })
            ->orderBy('name')
            ->get();
    }

    public function isItStaff(): bool
    {
        return $this->hasRole('administrator')
            || $this->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->exists();
    }

    public function canManageAdmin(): bool
    {
        return $this->hasRole('administrator') || $this->activeAccessGrants()->where('role', 'it_manager')->exists();
    }

    public function authorisedCompanies()
    {
        $companyIds = $this->activeAccessGrants()->where('scope_type', 'company')->pluck('scope_id');
        $projectCompanyIds = $this->authorisedProjects()->pluck('company_id');

        return Company::query()
            ->whereIn('id', $companyIds->merge($projectCompanyIds)->unique())
            ->orderBy('name')
            ->get();
    }
}
