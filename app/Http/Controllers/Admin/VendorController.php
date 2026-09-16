<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        return view('admin.vendors.index', ['vendors' => Vendor::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.vendors.form', ['vendor' => new Vendor]);
    }

    public function store(Request $request)
    {
        Vendor::create($this->validated($request));

        return redirect()->route('admin.vendors.index')->with('status', 'Vendor created.');
    }

    public function edit(Vendor $vendor)
    {
        return view('admin.vendors.form', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($this->validated($request));

        return redirect()->route('admin.vendors.index')->with('status', 'Vendor updated.');
    }

    public function destroy(Vendor $vendor)
    {
        abort(422, 'Vendors linked to assets/tickets are not deleted from the pilot UI.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'support_hours' => ['nullable', 'string', 'max:150'],
            'warranty_reference' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
