<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Only "administrator" is a global (spatie) role. Every other role in the
 * spec (project_manager, it_agent, it_manager, approver, auditor, vendor,
 * requester) is scoped per company/project via access_grants, or - for
 * vendor - via users.vendor_id, because the same person can be, say, an IT
 * agent on one project and nothing at all on another.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('administrator');
    }
}
