<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'company_id', 'name', 'type', 'address', 'area_block_floor_zone',
    'operating_hours_start', 'operating_hours_end', 'access_contact_name',
    'access_contact_phone', 'support_calendar_id',
])]
class Site extends Model
{
    use HasFactory;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supportCalendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'support_calendar_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_site');
    }
}
