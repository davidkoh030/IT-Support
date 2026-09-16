<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index()
    {
        return view('admin.companies.index', ['companies' => Company::with('parent')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.companies.form', ['company' => new Company, 'companies' => Company::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $company = Company::create($data);
        AuditLog::record($request->user(), 'company.created', $company, null, $data);

        return redirect()->route('admin.companies.index')->with('status', 'Company created.');
    }

    public function edit(Company $company)
    {
        return view('admin.companies.form', ['company' => $company, 'companies' => Company::where('id', '!=', $company->id)->orderBy('name')->get()]);
    }

    public function update(Request $request, Company $company)
    {
        $data = $this->validated($request, $company->id);
        $before = $company->toArray();
        $company->update($data);
        AuditLog::record($request->user(), 'company.updated', $company, $before, $data);

        return redirect()->route('admin.companies.index')->with('status', 'Company updated.');
    }

    public function destroy(Company $company)
    {
        abort(422, 'Companies are not deleted from the pilot UI to preserve ticket/asset history.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', Rule::unique('companies', 'code')->ignore($ignoreId)],
            'parent_company_id' => ['nullable', 'exists:companies,id'],
            'country_code' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
        ]);
    }
}
