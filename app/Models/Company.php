<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_company_id', 'name', 'code', 'country_code', 'timezone', 'currency'])]
class Company extends Model
{
    use HasFactory;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    public function subsidiaries(): HasMany
    {
        return $this->hasMany(Company::class, 'parent_company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * A company-scoped grant on a parent (e.g. the group) is meant to cover
     * its subsidiaries too - an IT manager granted access at group level
     * should see subsidiary tickets without a separate grant per company.
     * Returns [$companyId itself, ...every ancestor id up the parent chain].
     */
    public static function ancestorChain(int $companyId): array
    {
        $byId = self::query()->pluck('parent_company_id', 'id');
        $chain = [$companyId];
        $current = $companyId;

        while (($parent = $byId->get($current)) !== null) {
            $chain[] = $parent;
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Expands a set of granted company ids to include every descendant
     * company, so a grant at group level resolves down to its subsidiaries.
     */
    public static function idsIncludingDescendants(iterable $companyIds): array
    {
        $all = self::query()->get(['id', 'parent_company_id']);

        $result = collect($companyIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        $queue = $result;

        while ($queue !== []) {
            $id = array_shift($queue);
            $children = $all->where('parent_company_id', $id)->pluck('id')->all();
            foreach ($children as $childId) {
                if (! in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $result;
    }
}
