<?php

namespace Tests\Concerns;

use App\Models\AccessGrant;
use App\Models\Calendar;
use App\Models\Category;
use App\Models\Company;
use App\Models\PriorityMatrixRule;
use App\Models\Project;
use App\Models\Site;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Builds the minimum org structure a test needs, directly - not via the
 * demo seeder - so each test's fixture is easy to read and fast to build.
 */
trait SeedsMinimalOrg
{
    protected function makeCalendar(array $overrides = []): Calendar
    {
        return Calendar::create(array_merge([
            'name' => 'Test Calendar',
            'timezone' => 'Asia/Singapore',
            'working_days' => [1, 2, 3, 4, 5],
            'start_time' => '08:30',
            'end_time' => '17:30',
        ], $overrides));
    }

    protected function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Test Co',
            'code' => 'TST-'.Str::random(6),
            'country_code' => 'SG',
            'timezone' => 'Asia/Singapore',
            'currency' => 'SGD',
        ], $overrides));
    }

    protected function makeProject(Company $company, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'company_id' => $company->id,
            'code' => 'PRJ-'.Str::random(6),
            'name' => 'Test Project',
            'lifecycle_stage' => 'active',
            'timezone' => 'Asia/Singapore',
            'currency' => 'SGD',
        ], $overrides));
    }

    protected function makeSite(Company $company, array $overrides = []): Site
    {
        return Site::create(array_merge([
            'company_id' => $company->id,
            'name' => 'Test Site',
            'type' => 'site_office',
        ], $overrides));
    }

    protected function makeCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Test Category '.Str::random(4),
            'type' => 'incident',
        ], $overrides));
    }

    protected function makeSlaPolicies(Calendar $calendar, ?Company $company = null): void
    {
        $targets = ['P1' => [15, 240], 'P2' => [60, 480], 'P3' => [240, 1440], 'P4' => [480, 2400]];
        foreach ($targets as $priority => [$firstResponse, $restoration]) {
            SlaPolicy::create([
                'name' => "{$priority} Test",
                'priority' => $priority,
                'first_response_minutes' => $firstResponse,
                'restoration_minutes' => $restoration,
                'calendar_id' => $calendar->id,
                'company_id' => $company?->id,
            ]);
        }
    }

    protected function makePriorityMatrix(): void
    {
        $matrix = [
            ['high', 'high', 'P1'], ['high', 'medium', 'P2'], ['high', 'low', 'P3'],
            ['medium', 'high', 'P2'], ['medium', 'medium', 'P3'], ['medium', 'low', 'P4'],
            ['low', 'high', 'P3'], ['low', 'medium', 'P4'], ['low', 'low', 'P4'],
        ];
        foreach ($matrix as [$impact, $urgency, $priority]) {
            PriorityMatrixRule::updateOrCreate(compact('impact', 'urgency'), compact('priority'));
        }
    }

    protected function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User '.Str::random(4),
            'email' => Str::random(10).'@demo.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    protected function grant(User $user, string $role, string $scopeType, int $scopeId, array $overrides = []): AccessGrant
    {
        return AccessGrant::create(array_merge([
            'user_id' => $user->id,
            'role' => $role,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'effective_date' => now()->subDay()->toDateString(),
        ], $overrides));
    }
}
