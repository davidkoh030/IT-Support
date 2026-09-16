<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'code', 'name', 'lifecycle_stage', 'start_date', 'end_date',
    'manager_user_id', 'information_manager_user_id', 'timezone', 'currency',
])]
class Project extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function informationManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'information_manager_user_id');
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'project_site');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function accessGrants()
    {
        return AccessGrant::query()->where('scope_type', 'project')->where('scope_id', $this->id);
    }
}
