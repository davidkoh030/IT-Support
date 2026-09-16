<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Calendar;
use App\Models\Company;
use App\Models\Site;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index()
    {
        return view('admin.sites.index', ['sites' => Site::with('company', 'supportCalendar')->orderBy('name')->get()]);
    }

    public function create()
    {
        return $this->form(new Site);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $site = Site::create($data);
        AuditLog::record($request->user(), 'site.created', $site, null, $data);

        return redirect()->route('admin.sites.index')->with('status', 'Site created.');
    }

    public function edit(Site $site)
    {
        return $this->form($site);
    }

    public function update(Request $request, Site $site)
    {
        $data = $this->validated($request);
        $before = $site->toArray();
        $site->update($data);
        AuditLog::record($request->user(), 'site.updated', $site, $before, $data);

        return redirect()->route('admin.sites.index')->with('status', 'Site updated.');
    }

    public function destroy(Site $site)
    {
        abort(422, 'Sites are not deleted from the pilot UI to preserve ticket/asset history.');
    }

    private function form(Site $site)
    {
        return view('admin.sites.form', [
            'site' => $site,
            'companies' => Company::orderBy('name')->get(),
            'calendars' => Calendar::orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:hq,site_office,warehouse,precast_plant'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_block_floor_zone' => ['nullable', 'string', 'max:150'],
            'operating_hours_start' => ['nullable', 'date_format:H:i'],
            'operating_hours_end' => ['nullable', 'date_format:H:i'],
            'access_contact_name' => ['nullable', 'string', 'max:150'],
            'access_contact_phone' => ['nullable', 'string', 'max:50'],
            'support_calendar_id' => ['nullable', 'exists:calendars,id'],
        ]);
    }
}
