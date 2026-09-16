<?php

namespace Database\Seeders;

use App\Models\AccessGrant;
use App\Models\Approval;
use App\Models\Asset;
use App\Models\Calendar;
use App\Models\Category;
use App\Models\Company;
use App\Models\ExternalAccessGrant;
use App\Models\KnowledgeArticle;
use App\Models\PriorityMatrixRule;
use App\Models\Project;
use App\Models\Site;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A modest, entirely fictional dataset per BUILD PROMPT section 13: two
 * companies, an HQ, two projects, two site offices, one precast plant, all
 * named roles, valid assets/categories and ~20 representative tickets
 * covering the acceptance scenarios. No real personal or infrastructure
 * data. This is NOT the legacy 1,000-row faker seeder - every row here is
 * deliberate and internally consistent.
 */
class DemoDataSeeder extends Seeder
{
    private TicketService $tickets;

    public function run(): void
    {
        $this->tickets = app(TicketService::class);

        [$sgCalendar, $myCalendar] = $this->calendars();
        [$group, $sg, $my] = $this->companies();
        [$hq, $siteA, $siteB, $precast] = $this->sites($sg, $my, $sgCalendar, $myCalendar);
        [$projectA, $projectB] = $this->projects($sg, $my, $siteA, $siteB, $precast);
        $this->slaPolicies($sgCalendar, $myCalendar, $sg, $my);
        $this->priorityMatrix();
        $categories = $this->categories();
        $vendor = $this->vendor();
        $users = $this->users($group, $sg, $my, $projectA, $projectB, $vendor);
        $assets = $this->assets($sg, $my, $siteA, $siteB, $users);
        $this->knowledgeArticles($users);
        $this->tickets($sg, $my, $projectA, $projectB, $siteA, $siteB, $categories, $vendor, $users, $assets);
    }

    private function calendars(): array
    {
        $sg = Calendar::create([
            'name' => 'Singapore Standard Support',
            'timezone' => 'Asia/Singapore',
            'working_days' => [1, 2, 3, 4, 5],
            'start_time' => '08:30',
            'end_time' => '17:30',
        ]);
        $sg->holidays()->createMany([
            ['date' => '2026-01-01', 'name' => "New Year's Day"],
            ['date' => '2026-02-17', 'name' => 'Chinese New Year'],
            ['date' => '2026-02-18', 'name' => 'Chinese New Year Holiday'],
        ]);

        $my = Calendar::create([
            'name' => 'Malaysia Standard Support',
            'timezone' => 'Asia/Kuala_Lumpur',
            'working_days' => [1, 2, 3, 4, 5],
            'start_time' => '08:30',
            'end_time' => '17:30',
        ]);
        $my->holidays()->createMany([
            ['date' => '2026-01-01', 'name' => "New Year's Day"],
            ['date' => '2026-02-17', 'name' => 'Chinese New Year'],
            ['date' => '2026-08-31', 'name' => 'Merdeka Day'],
        ]);

        return [$sg, $my];
    }

    private function companies(): array
    {
        $group = Company::create([
            'name' => 'Straits Build Group',
            'code' => 'SBG-GRP',
            'country_code' => 'SG',
            'timezone' => 'Asia/Singapore',
            'currency' => 'SGD',
        ]);

        $sg = Company::create([
            'parent_company_id' => $group->id,
            'name' => 'Straits Build Pte Ltd',
            'code' => 'SBG-SG',
            'country_code' => 'SG',
            'timezone' => 'Asia/Singapore',
            'currency' => 'SGD',
        ]);

        $my = Company::create([
            'parent_company_id' => $group->id,
            'name' => 'Straits Build Sdn Bhd',
            'code' => 'SBG-MY',
            'country_code' => 'MY',
            'timezone' => 'Asia/Kuala_Lumpur',
            'currency' => 'MYR',
        ]);

        return [$group, $sg, $my];
    }

    private function sites(Company $sg, Company $my, Calendar $sgCal, Calendar $myCal): array
    {
        $hq = Site::create([
            'company_id' => $sg->id,
            'name' => 'HQ - Raffles Place',
            'type' => 'hq',
            'address' => '1 Raffles Place, Singapore',
            'area_block_floor_zone' => 'Tower 2, Level 18',
            'operating_hours_start' => '08:30',
            'operating_hours_end' => '18:00',
            'access_contact_name' => 'Facilities Desk',
            'access_contact_phone' => '+65 6000 1000',
            'support_calendar_id' => $sgCal->id,
        ]);

        $siteA = Site::create([
            'company_id' => $sg->id,
            'name' => 'Marina Parkview Site Office',
            'type' => 'site_office',
            'address' => '88 Marina Parkview Ave, Singapore',
            'area_block_floor_zone' => 'Site Office Block C',
            'operating_hours_start' => '07:00',
            'operating_hours_end' => '19:00',
            'access_contact_name' => 'Site Admin - Marina Parkview',
            'access_contact_phone' => '+65 6000 2000',
            'support_calendar_id' => $sgCal->id,
        ]);

        $siteB = Site::create([
            'company_id' => $my->id,
            'name' => 'Iskandar Logistics Site Office',
            'type' => 'site_office',
            'address' => 'Jalan Iskandar, Johor Bahru, Malaysia',
            'area_block_floor_zone' => 'Site Office Block A',
            'operating_hours_start' => '07:30',
            'operating_hours_end' => '18:30',
            'access_contact_name' => 'Site Admin - Iskandar',
            'access_contact_phone' => '+60 7-000 3000',
            'support_calendar_id' => $myCal->id,
        ]);

        $precast = Site::create([
            'company_id' => $my->id,
            'name' => 'Iskandar Precast Plant',
            'type' => 'precast_plant',
            'address' => 'Lot 5, Iskandar Industrial Park, Malaysia',
            'area_block_floor_zone' => 'Production Hall 1',
            'operating_hours_start' => '00:00',
            'operating_hours_end' => '23:59',
            'access_contact_name' => 'Plant Supervisor',
            'access_contact_phone' => '+60 7-000 4000',
            'support_calendar_id' => $myCal->id,
        ]);

        return [$hq, $siteA, $siteB, $precast];
    }

    private function projects(Company $sg, Company $my, Site $siteA, Site $siteB, Site $precast): array
    {
        $projectA = Project::create([
            'company_id' => $sg->id,
            'code' => 'PRJ-MPV',
            'name' => 'Marina Parkview Residences',
            'lifecycle_stage' => 'active',
            'start_date' => '2025-03-01',
            'end_date' => '2027-06-30',
            'timezone' => 'Asia/Singapore',
            'currency' => 'SGD',
        ]);
        $projectA->sites()->attach($siteA->id);

        $projectB = Project::create([
            'company_id' => $my->id,
            'code' => 'PRJ-ILH',
            'name' => 'Iskandar Logistics Hub',
            'lifecycle_stage' => 'mobilising',
            'start_date' => '2026-01-15',
            'end_date' => '2028-12-31',
            'timezone' => 'Asia/Kuala_Lumpur',
            'currency' => 'MYR',
        ]);
        $projectB->sites()->attach([$siteB->id, $precast->id]);

        return [$projectA, $projectB];
    }

    private function slaPolicies(Calendar $sgCal, Calendar $myCal, Company $sg, Company $my): void
    {
        $targets = [
            'P1' => [15, 240],
            'P2' => [60, 480],
            'P3' => [240, 1440],
            'P4' => [480, 2400],
        ];

        foreach ($targets as $priority => [$firstResponse, $restoration]) {
            SlaPolicy::create([
                'name' => "{$priority} Default",
                'priority' => $priority,
                'first_response_minutes' => $firstResponse,
                'restoration_minutes' => $restoration,
                'calendar_id' => $sgCal->id,
                'company_id' => null,
            ]);

            SlaPolicy::create([
                'name' => "{$priority} Malaysia",
                'priority' => $priority,
                'first_response_minutes' => $firstResponse,
                'restoration_minutes' => $restoration,
                'calendar_id' => $myCal->id,
                'company_id' => $my->id,
            ]);
        }
    }

    private function priorityMatrix(): void
    {
        $matrix = [
            ['high', 'high', 'P1'], ['high', 'medium', 'P2'], ['high', 'low', 'P3'],
            ['medium', 'high', 'P2'], ['medium', 'medium', 'P3'], ['medium', 'low', 'P4'],
            ['low', 'high', 'P3'], ['low', 'medium', 'P4'], ['low', 'low', 'P4'],
        ];

        foreach ($matrix as [$impact, $urgency, $priority]) {
            PriorityMatrixRule::create(compact('impact', 'urgency', 'priority'));
        }
    }

    private function categories(): array
    {
        $names = [
            ['Account, password and MFA issues', 'incident'],
            ['Site internet, network, Wi-Fi and VPN outage', 'incident'],
            ['Desktop/laptop/tablet/mobile hardware', 'incident'],
            ['Printers, plotters and meeting room equipment', 'incident'],
            ['BIM/IDD/CDE applications and licences', 'incident'],
            ['ERP/finance/procurement applications', 'incident'],
            ['Backup/file restoration', 'service_request'],
            ['Suspicious email / lost device / cybersecurity', 'incident'],
            ['New starter', 'service_request'],
            ['Transfer', 'service_request'],
            ['Leaver', 'service_request'],
            ['External access', 'service_request'],
            ['Site setup', 'service_request'],
            ['Site closure', 'service_request'],
            ['Equipment issue/return', 'service_request'],
        ];

        $categories = [];
        foreach ($names as [$name, $type]) {
            $categories[$name] = Category::create(['name' => $name, 'type' => $type]);
        }

        return $categories;
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'NetLink Managed Services',
            'contact_name' => 'Vendor Support Desk',
            'contact_email' => 'support@netlink-demo.example',
            'contact_phone' => '+65 6000 9000',
            'support_hours' => 'Mon-Fri 09:00-18:00 SGT',
            'warranty_reference' => 'NLK-WARR-2026-001',
        ]);
    }

    private function users(Company $group, Company $sg, Company $my, Project $projectA, Project $projectB, Vendor $vendor): array
    {
        $make = fn (string $name, string $email) => User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $admin = $make('Alex Tan (System Administrator)', 'admin@demo.test');
        $admin->assignRole('administrator');

        $itManager = $make('Priya Nair (IT Manager)', 'it.manager@demo.test');
        AccessGrant::create(['user_id' => $itManager->id, 'scope_type' => 'company', 'scope_id' => $group->id, 'role' => 'it_manager', 'effective_date' => '2025-01-01']);

        $itAgentSg = $make('Marcus Lee (IT Agent, SG)', 'it.agent.sg@demo.test');
        AccessGrant::create(['user_id' => $itAgentSg->id, 'scope_type' => 'company', 'scope_id' => $sg->id, 'role' => 'it_agent', 'effective_date' => '2025-01-01']);

        $itAgentMy = $make('Farah Aziz (IT Agent, MY)', 'it.agent.my@demo.test');
        AccessGrant::create(['user_id' => $itAgentMy->id, 'scope_type' => 'company', 'scope_id' => $my->id, 'role' => 'it_agent', 'effective_date' => '2025-01-01']);

        $pmA = $make('David Ong (Project Manager, Marina Parkview)', 'pm.mpv@demo.test');
        AccessGrant::create(['user_id' => $pmA->id, 'scope_type' => 'project', 'scope_id' => $projectA->id, 'role' => 'project_manager', 'effective_date' => '2025-03-01']);
        Project::where('id', $projectA->id)->update(['manager_user_id' => $pmA->id, 'information_manager_user_id' => $pmA->id]);

        $pmB = $make('Siti Rahman (Project Manager, Iskandar)', 'pm.ilh@demo.test');
        AccessGrant::create(['user_id' => $pmB->id, 'scope_type' => 'project', 'scope_id' => $projectB->id, 'role' => 'project_manager', 'effective_date' => '2026-01-15']);
        Project::where('id', $projectB->id)->update(['manager_user_id' => $pmB->id, 'information_manager_user_id' => $pmB->id]);

        $auditor = $make('Jamie Koh (Auditor)', 'auditor@demo.test');
        AccessGrant::create(['user_id' => $auditor->id, 'scope_type' => 'company', 'scope_id' => $group->id, 'role' => 'auditor', 'effective_date' => now()->subDays(10)->toDateString(), 'expiry_date' => now()->addDays(20)->toDateString(), 'notes' => 'Q3 internal audit - time-bound scope']);

        $requesterA = $make('Wei Ling Tan (QS, Marina Parkview)', 'requester.mpv@demo.test');
        AccessGrant::create(['user_id' => $requesterA->id, 'scope_type' => 'project', 'scope_id' => $projectA->id, 'role' => 'member', 'effective_date' => '2025-03-01']);

        $requesterB = $make('Ah Beng Lim (Site Engineer, Marina Parkview)', 'requester2.mpv@demo.test');
        AccessGrant::create(['user_id' => $requesterB->id, 'scope_type' => 'project', 'scope_id' => $projectA->id, 'role' => 'member', 'effective_date' => '2025-03-01']);

        $requesterC = $make('Nurul Huda (Site Admin, Iskandar)', 'requester.ilh@demo.test');
        AccessGrant::create(['user_id' => $requesterC->id, 'scope_type' => 'project', 'scope_id' => $projectB->id, 'role' => 'member', 'effective_date' => '2026-01-15']);

        // Transferred out of Marina Parkview last month; access should now be gone.
        $transferredOut = $make('Kevin Goh (Transferred off Marina Parkview)', 'transferred@demo.test');
        AccessGrant::create([
            'user_id' => $transferredOut->id, 'scope_type' => 'project', 'scope_id' => $projectA->id, 'role' => 'member',
            'effective_date' => '2025-03-01', 'expiry_date' => now()->subDays(15)->toDateString(),
        ]);

        $bimUser = $make('Chloe Ang (BIM Coordinator, Marina Parkview)', 'bim.mpv@demo.test');
        AccessGrant::create(['user_id' => $bimUser->id, 'scope_type' => 'project', 'scope_id' => $projectA->id, 'role' => 'member', 'effective_date' => '2025-03-01']);

        $vendorUser = $make('Ravi Kumar (NetLink Vendor Contact)', 'vendor@demo.test');
        $vendorUser->update(['vendor_id' => $vendor->id]);

        return compact('admin', 'itManager', 'itAgentSg', 'itAgentMy', 'pmA', 'pmB', 'auditor', 'requesterA', 'requesterB', 'requesterC', 'transferredOut', 'bimUser', 'vendorUser');
    }

    private function assets(Company $sg, Company $my, Site $siteA, Site $siteB, array $users): array
    {
        $router = Asset::create([
            'tag' => 'NET-0001', 'type' => 'Router/Firewall', 'make' => 'Fortinet', 'model' => 'FortiGate 60F',
            'company_id' => $sg->id, 'site_id' => $siteA->id, 'status' => 'assigned',
            'purchase_date' => '2025-02-01', 'warranty_expiry' => '2028-02-01', 'supplier' => 'NetLink Managed Services',
            'network_notes' => 'WAN uplink via fibre + 4G failover. Admin VLAN 10.',
        ]);

        $plotter = Asset::create([
            'tag' => 'PRN-0002', 'type' => 'Plotter', 'make' => 'HP', 'model' => 'DesignJet T730',
            'company_id' => $sg->id, 'site_id' => $siteA->id, 'status' => 'assigned', 'purchase_date' => '2025-04-01',
        ]);

        $laptopA = Asset::create([
            'tag' => 'LAP-0003', 'type' => 'Laptop', 'make' => 'Dell', 'model' => 'Latitude 5450',
            'assigned_user_id' => $users['bimUser']->id, 'company_id' => $sg->id, 'site_id' => $siteA->id,
            'status' => 'assigned', 'purchase_date' => '2025-05-01', 'warranty_expiry' => '2028-05-01',
        ]);

        $ups = Asset::create([
            'tag' => 'UPS-0004', 'type' => 'UPS', 'make' => 'APC', 'model' => 'Smart-UPS 1500VA',
            'company_id' => $my->id, 'site_id' => $siteB->id, 'status' => 'assigned', 'purchase_date' => '2026-01-10',
        ]);

        $switch = Asset::create([
            'tag' => 'NET-0005', 'type' => 'Switch', 'make' => 'Ubiquiti', 'model' => 'UniFi Switch 24 PoE',
            'company_id' => $my->id, 'site_id' => $siteB->id, 'status' => 'assigned', 'purchase_date' => '2026-01-10',
        ]);

        $meetingRoomDisplay = Asset::create([
            'tag' => 'AVR-0006', 'type' => 'Meeting Room Display', 'make' => 'Samsung', 'model' => 'Flip Pro 65"',
            'company_id' => $sg->id, 'status' => 'in_stock', 'purchase_date' => '2025-06-01',
        ]);

        return compact('router', 'plotter', 'laptopA', 'ups', 'switch', 'meetingRoomDisplay');
    }

    private function knowledgeArticles(array $users): void
    {
        KnowledgeArticle::create([
            'title' => 'Connecting to the site Wi-Fi',
            'slug' => 'connecting-to-site-wifi',
            'body' => "1. Select the site's SSID from your Wi-Fi list.\n2. Enter the passphrase shown on the site office noticeboard.\n3. If the connection drops repeatedly, raise a ticket under 'Site internet, network, Wi-Fi and VPN outage'.",
            'owner_id' => $users['itManager']->id,
            'audience' => 'All site staff',
            'review_date' => now()->addMonths(6)->toDateString(),
            'status' => 'published',
        ]);

        KnowledgeArticle::create([
            'title' => 'Requesting BIM/ACC licence access',
            'slug' => 'requesting-bim-acc-licence',
            'body' => "Raise a service request under 'BIM/IDD/CDE applications and licences' with your project code and the specific application (e.g. Revit, Navisworks, ACC). Your Information Manager approves project-level access.",
            'owner_id' => $users['itManager']->id,
            'audience' => 'BIM/Engineering staff',
            'review_date' => now()->addMonths(6)->toDateString(),
            'status' => 'published',
        ]);

        KnowledgeArticle::create([
            'title' => 'Reporting a lost or stolen device',
            'slug' => 'reporting-lost-stolen-device',
            'body' => "Raise a ticket immediately under 'Suspicious email / lost device / cybersecurity' and tick 'possible security incident'. Do not wait to confirm - IT will triage and, if needed, remote-wipe the device.",
            'owner_id' => $users['itManager']->id,
            'audience' => 'All staff',
            'review_date' => now()->addMonths(3)->toDateString(),
            'status' => 'published',
        ]);
    }

    /**
     * ~20 tickets exercising the acceptance scenarios in section 13.
     */
    private function tickets(Company $sg, Company $my, Project $projectA, Project $projectB, Site $siteA, Site $siteB, array $categories, Vendor $vendor, array $users, array $assets): void
    {
        // 1. Site-wide internet failure -> P1, immediate SG business-hours clock.
        $majorIncident = $this->tickets->create($users['requesterB'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Site internet, network, Wi-Fi and VPN outage']->id,
            'type' => 'incident', 'impact' => 'high', 'urgency' => 'high',
            'summary' => 'Entire Marina Parkview site office has no internet',
            'description' => 'Primary fibre link down since 08:05, no workaround, whole site office affected.',
            'affected_users_note' => 'All ~40 site office staff',
        ], (string) Str::uuid());
        $majorIncident->update(['vendor_id' => $vendor->id, 'vendor_case_number' => 'NLK-2026-0091', 'vendor_update_due_at' => now()->addHours(4)]);
        $this->tickets->assign($majorIncident, $users['itManager'], $users['itAgentSg']);
        $this->tickets->transition($majorIncident, $users['itAgentSg'], 'in_progress', 'Vendor engaged, on-site diagnosis underway.');

        // A second report linked to the same major incident.
        $linked = $this->tickets->create($users['bimUser'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Site internet, network, Wi-Fi and VPN outage']->id,
            'type' => 'incident', 'impact' => 'high', 'urgency' => 'high',
            'summary' => 'Cannot sync ACC files - no connectivity',
            'description' => 'Same outage as reported by site team, ACC sync failing.',
        ], (string) Str::uuid());
        $linked->update(['major_incident_id' => $majorIncident->id]);

        // 2. Routine plotter issue, mobile-reported, with an attachment context (attachment upload verified manually - see TEST_REPORT.md).
        $this->tickets->create($users['requesterA'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Printers, plotters and meeting room equipment']->id,
            'type' => 'incident', 'impact' => 'low', 'urgency' => 'low',
            'summary' => 'Plotter jamming on A1 prints',
            'description' => 'The site office plotter jams every time an A1 drawing is queued. Workaround: print A3 and tile manually.',
            'affected_users_note' => 'QS team',
        ], (string) Str::uuid())->assets()->attach($assets['plotter']->id);

        // 3. BIM licence problem with an imminent deadline - deadline captured, priority still matrix-derived (medium/medium here, not auto-critical).
        $bimTicket = $this->tickets->create($users['bimUser'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['BIM/IDD/CDE applications and licences']->id,
            'type' => 'incident', 'impact' => 'medium', 'urgency' => 'medium',
            'summary' => 'Revit licence checkout failing before tender submission',
            'description' => 'Revit reports "no licence available" intermittently. Tender package due soon.',
            'deadline_at' => now()->addHours(30), 'deadline_reason' => 'Tender submission deadline',
        ], (string) Str::uuid());
        $this->tickets->assign($bimTicket, $users['itManager'], $users['itAgentSg']);

        // Restricted security incident - must stay invisible to the project manager.
        $securityTicket = $this->tickets->create($users['requesterB'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Suspicious email / lost device / cybersecurity']->id,
            'type' => 'incident', 'impact' => 'high', 'urgency' => 'high',
            'summary' => 'Suspected phishing email with credential harvest link',
            'description' => 'Received an email impersonating IT asking to "verify" M365 password via an external link. Did not click.',
            'restricted' => true,
        ], (string) Str::uuid());
        $this->tickets->assign($securityTicket, $users['itManager'], $users['itAgentSg']);
        $this->tickets->addComment($securityTicket, $users['itAgentSg'], 'Confirmed phishing domain, blocked at mail gateway. Assessing whether any credentials were entered.', 'internal');

        // Sensitive HR/account request - restricted, PM should not see it.
        $hrTicket = $this->tickets->create($users['requesterC'], [
            'company_id' => $my->id, 'project_id' => $projectB->id, 'site_id' => $siteB->id,
            'category_id' => $categories['Account, password and MFA issues']->id,
            'type' => 'service_request', 'impact' => 'medium', 'urgency' => 'medium',
            'summary' => 'Payroll system access for confidential HR matter',
            'description' => 'Access request tied to an ongoing confidential HR case - restricted from general project visibility.',
            'restricted' => true,
        ], (string) Str::uuid());
        $this->tickets->assign($hrTicket, $users['itManager'], $users['itAgentMy']);

        // Waiting-for-requester ticket, to exercise SLA pause.
        $waitingTicket = $this->tickets->create($users['requesterA'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Desktop/laptop/tablet/mobile hardware']->id,
            'type' => 'incident', 'impact' => 'medium', 'urgency' => 'low',
            'summary' => 'Laptop running very slow after update',
            'description' => 'Laptop became slow after last Windows update.',
        ], (string) Str::uuid());
        $this->tickets->assign($waitingTicket, $users['itManager'], $users['itAgentSg']);
        $this->tickets->transition($waitingTicket, $users['itAgentSg'], 'waiting_requester', 'Need remote access window to run diagnostics.');

        // Waiting-for-vendor ticket - clock keeps counting.
        $vendorTicket = $this->tickets->create($users['requesterC'], [
            'company_id' => $my->id, 'project_id' => $projectB->id, 'site_id' => $siteB->id,
            'category_id' => $categories['Site internet, network, Wi-Fi and VPN outage']->id,
            'type' => 'incident', 'impact' => 'medium', 'urgency' => 'medium',
            'summary' => 'Intermittent Wi-Fi drops in site office',
            'description' => 'Wi-Fi drops every afternoon around 2pm.',
        ], (string) Str::uuid());
        $vendorTicket->update(['vendor_id' => $vendor->id, 'vendor_case_number' => 'NLK-2026-0104']);
        $this->tickets->assign($vendorTicket, $users['itManager'], $users['itAgentMy']);
        $this->tickets->transition($vendorTicket, $users['itAgentMy'], 'waiting_vendor', 'Vendor investigating interference on the 5GHz band.');

        // Resolved + reopened ticket.
        $reopened = $this->tickets->create($users['requesterA'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Account, password and MFA issues']->id,
            'type' => 'incident', 'impact' => 'low', 'urgency' => 'medium',
            'summary' => 'MFA prompts not arriving on phone',
            'description' => 'Not receiving MFA push notifications since this morning.',
        ], (string) Str::uuid());
        $this->tickets->assign($reopened, $users['itManager'], $users['itAgentSg']);
        $this->tickets->transition($reopened, $users['itAgentSg'], 'in_progress');
        $reopened->update(['resolution_code' => 'Reconfigured', 'resolution_notes' => 'Re-registered MFA device.']);
        $this->tickets->transition($reopened, $users['itAgentSg'], 'resolved');
        $this->tickets->transition($reopened, $users['requesterA'], 'in_progress', 'Issue recurred this afternoon.');

        // Priority-overridden ticket, with audit trail.
        $overridden = $this->tickets->create($users['requesterA'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['ERP/finance/procurement applications']->id,
            'type' => 'incident', 'impact' => 'medium', 'urgency' => 'low',
            'summary' => 'Synergix ERP timesheet module unavailable',
            'description' => 'Cannot submit weekly timesheets before payroll cutoff tonight.',
            'deadline_at' => now()->addHours(6), 'deadline_reason' => 'Payroll processing cutoff',
        ], (string) Str::uuid());
        $this->tickets->overridePriority($overridden, $users['itManager'], 'P2', 'Payroll cutoff tonight confirmed with finance - escalating ahead of matrix default.');

        // A joiner/leaver/mobilisation-style service request with checklist + approval, for the access lifecycle scenario.
        $leaverCategory = $categories['Leaver'];
        $leaver = $this->tickets->create($users['itAgentSg'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id,
            'category_id' => $leaverCategory->id, 'type' => 'service_request', 'impact' => 'medium', 'urgency' => 'medium',
            'summary' => 'Leaver: Kevin Goh (transferred off project, resigning from group)',
            'description' => 'Kevin Goh is leaving the company; revoke access and recover assets.',
            'approval_required' => true,
        ], (string) Str::uuid());
        foreach (config('itsm.request_templates.leaver.checklist') as $item) {
            $leaver->checklistTasks()->create(['name' => $item['name'], 'assigned_role' => $item['assigned_role']]);
        }
        Approval::create([
            'approvable_type' => Ticket::class, 'approvable_id' => $leaver->id,
            'approver_role' => 'manager', 'approver_user_id' => $users['pmA']->id,
        ]);

        // External access request, expired and not yet revoked - overdue revocation scenario.
        $externalTicket = $this->tickets->create($users['pmA'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id,
            'category_id' => $categories['External access']->id, 'type' => 'service_request',
            'impact' => 'low', 'urgency' => 'low',
            'summary' => 'External access for structural consultant',
            'description' => 'Temporary CDE access for an external structural consultant reviewing drawings.',
        ], (string) Str::uuid());
        ExternalAccessGrant::create([
            'ticket_id' => $externalTicket->id,
            'sponsor_user_id' => $users['pmA']->id,
            'external_party_name' => 'Jonathan Wee',
            'employer' => 'Wee & Partners Structural Consultants',
            'resource' => 'ACC project folder - structural',
            'access_level' => 'Read/comment',
            'business_purpose' => 'Structural drawing review',
            'expiry_date' => now()->subDays(3)->toDateString(),
        ]);

        // Site mobilisation ticket for the Malaysia project.
        $mobilisation = $this->tickets->create($users['pmB'], [
            'company_id' => $my->id, 'project_id' => $projectB->id, 'site_id' => $siteB->id,
            'category_id' => $categories['Site setup']->id, 'type' => 'service_request',
            'impact' => 'medium', 'urgency' => 'medium',
            'summary' => 'Site mobilisation - Iskandar Logistics Hub',
            'description' => 'Set up connectivity, firewall, printers and UPS for new site office.',
            'approval_required' => true,
        ], (string) Str::uuid());
        foreach (config('itsm.request_templates.site_mobilisation.checklist') as $item) {
            $mobilisation->checklistTasks()->create(['name' => $item['name'], 'assigned_role' => $item['assigned_role']]);
        }
        $mobilisation->checklistTasks()->first()->update(['status' => 'completed', 'completed_by_id' => $users['itAgentMy']->id, 'completed_at' => now(), 'evidence_note' => 'ISP confirmed install for next Monday.']);
        Approval::create([
            'approvable_type' => Ticket::class, 'approvable_id' => $mobilisation->id,
            'approver_role' => 'manager', 'approver_user_id' => $users['pmB']->id, 'decision' => 'approved', 'decided_at' => now()->subDay(),
        ]);
        $mobilisation->update(['approval_state' => 'approved']);

        // Site demobilisation - closed project, history retained.
        $demobilisation = $this->tickets->create($users['itAgentSg'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Site closure']->id, 'type' => 'service_request',
            'impact' => 'low', 'urgency' => 'low',
            'summary' => 'Demobilise temporary hoarding office (phase 1 complete)',
            'description' => 'Phase 1 site office closing; assets to be returned/redeployed.',
        ], (string) Str::uuid());
        foreach (config('itsm.request_templates.site_demobilisation.checklist') as $item) {
            $demobilisation->checklistTasks()->create(['name' => $item['name'], 'assigned_role' => $item['assigned_role']]);
        }
        $demobilisation->checklistTasks()->take(2)->get()->each(fn ($t) => $t->update(['status' => 'completed', 'completed_by_id' => $users['itAgentSg']->id, 'completed_at' => now()]));

        // Vendor-shared ticket.
        $vendorShared = $this->tickets->create($users['itAgentSg'], [
            'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
            'category_id' => $categories['Site internet, network, Wi-Fi and VPN outage']->id,
            'type' => 'incident', 'impact' => 'medium', 'urgency' => 'medium',
            'summary' => 'Firewall firmware upgrade required (vendor advisory)',
            'description' => 'Vendor advisory recommends firmware upgrade to patch a known vulnerability.',
        ], (string) Str::uuid());
        $vendorShared->update(['vendor_id' => $vendor->id]);
        $vendorShared->shares()->create(['user_id' => $users['vendorUser']->id, 'shared_by_id' => $users['itAgentSg']->id]);
        $vendorShared->assets()->attach($assets['router']->id);

        // Remaining routine/varied tickets to round out ~20 and populate reporting views.
        $routine = [
            ['requester' => 'requesterB', 'category' => 'Desktop/laptop/tablet/mobile hardware', 'summary' => 'Keyboard keys sticking on site laptop', 'impact' => 'low', 'urgency' => 'low'],
            ['requester' => 'requesterC', 'category' => 'Backup/file restoration', 'summary' => 'Need last week\'s drawing folder restored', 'impact' => 'medium', 'urgency' => 'medium'],
            ['requester' => 'bimUser', 'category' => 'BIM/IDD/CDE applications and licences', 'summary' => 'Navisworks crashing on clash detection', 'impact' => 'medium', 'urgency' => 'low'],
            ['requester' => 'requesterA', 'category' => 'Account, password and MFA issues', 'summary' => 'Locked out after too many failed logins', 'impact' => 'high', 'urgency' => 'medium'],
            ['requester' => 'requesterB', 'category' => 'Printers, plotters and meeting room equipment', 'summary' => 'Meeting room display will not pair via HDMI', 'impact' => 'low', 'urgency' => 'low'],
            ['requester' => 'requesterC', 'category' => 'ERP/finance/procurement applications', 'summary' => 'Procurement approval workflow stuck', 'impact' => 'medium', 'urgency' => 'medium'],
            ['requester' => 'requesterA', 'category' => 'Equipment issue/return', 'summary' => 'Requesting a spare mouse and dock', 'impact' => 'low', 'urgency' => 'low'],
        ];

        foreach ($routine as $t) {
            $ticket = $this->tickets->create($users[$t['requester']], [
                'company_id' => $sg->id, 'project_id' => $projectA->id, 'site_id' => $siteA->id,
                'category_id' => $categories[$t['category']]->id,
                'type' => str_contains($t['category'], 'Equipment') || str_contains($t['category'], 'Backup') ? 'service_request' : 'incident',
                'impact' => $t['impact'], 'urgency' => $t['urgency'],
                'summary' => $t['summary'],
                'description' => $t['summary'].' - reported via the pilot demo dataset.',
            ], (string) Str::uuid());

            $willResolve = fake()->boolean(30);
            if ($willResolve || fake()->boolean(50)) {
                $this->tickets->assign($ticket, $users['itManager'], $users['itAgentSg']);
            }
            if ($willResolve) {
                $this->tickets->transition($ticket, $users['itAgentSg'], 'in_progress');
                $ticket->update(['resolution_code' => 'Fixed', 'resolution_notes' => 'Resolved during demo seeding.']);
                $this->tickets->transition($ticket, $users['itAgentSg'], 'resolved');
                $this->tickets->transition($ticket, $users['itAgentSg'], 'closed');
                $ticket->satisfactionScore()->create(['score' => fake()->numberBetween(3, 5)]);
            }
        }
    }
}
