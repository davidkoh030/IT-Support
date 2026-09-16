<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        return view('admin.projects.index', ['projects' => Project::with('company')->orderBy('name')->get()]);
    }

    public function create()
    {
        return $this->form(new Project);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $project = Project::create($data);
        $this->syncSites($request, $project);
        AuditLog::record($request->user(), 'project.created', $project, null, $data);

        return redirect()->route('admin.projects.index')->with('status', 'Project created.');
    }

    public function edit(Project $project)
    {
        return $this->form($project);
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project->id);
        $before = $project->toArray();
        $project->update($data);
        $this->syncSites($request, $project);
        AuditLog::record($request->user(), 'project.updated', $project, $before, $data);

        return redirect()->route('admin.projects.index')->with('status', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        abort(422, 'Projects are closed via lifecycle_stage, not deleted, to preserve history.');
    }

    private function form(Project $project)
    {
        return view('admin.projects.form', [
            'project' => $project,
            'companies' => Company::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'managers' => User::where('is_active', true)->orderBy('name')->get(),
            'selectedSiteIds' => $project->exists ? $project->sites()->pluck('sites.id')->all() : [],
        ]);
    }

    private function syncSites(Request $request, Project $project): void
    {
        $project->sites()->sync($request->input('site_ids', []));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('projects', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:150'],
            'lifecycle_stage' => ['required', 'in:proposed,mobilising,active,demobilising,closed'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'manager_user_id' => ['nullable', 'exists:users,id'],
            'information_manager_user_id' => ['nullable', 'exists:users,id'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
        ]);
    }
}
