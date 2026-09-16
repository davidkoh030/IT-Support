<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Company;
use App\Models\SlaPolicy;
use Illuminate\Http\Request;

class SlaPolicyController extends Controller
{
    public function index()
    {
        return view('admin.sla-policies.index', ['policies' => SlaPolicy::with('calendar')->orderBy('priority')->get()]);
    }

    public function create()
    {
        return $this->form(new SlaPolicy);
    }

    public function store(Request $request)
    {
        SlaPolicy::create($this->validated($request));

        return redirect()->route('admin.sla-policies.index')->with('status', 'SLA policy created.');
    }

    public function edit(SlaPolicy $slaPolicy)
    {
        return $this->form($slaPolicy);
    }

    public function update(Request $request, SlaPolicy $slaPolicy)
    {
        $slaPolicy->update($this->validated($request));

        return redirect()->route('admin.sla-policies.index')->with('status', 'SLA policy updated.');
    }

    private function form(SlaPolicy $slaPolicy)
    {
        return view('admin.sla-policies.form', [
            'policy' => $slaPolicy,
            'calendars' => Calendar::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'priority' => ['required', 'in:P1,P2,P3,P4'],
            'first_response_minutes' => ['required', 'integer', 'min:1'],
            'restoration_minutes' => ['required', 'integer', 'min:1'],
            'calendar_id' => ['required', 'exists:calendars,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
