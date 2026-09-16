<?php

namespace App\Services;

use App\Models\PriorityMatrixRule;

class PriorityCalculator
{
    /**
     * Derive priority from the admin-editable impact x urgency matrix
     * (section 7). Falls back to the lowest priority if a combination has
     * no configured rule, rather than failing ticket creation.
     */
    public function calculate(string $impact, string $urgency): string
    {
        $rule = PriorityMatrixRule::query()
            ->where('impact', $impact)
            ->where('urgency', $urgency)
            ->first();

        return $rule?->priority ?? 'P4';
    }
}
