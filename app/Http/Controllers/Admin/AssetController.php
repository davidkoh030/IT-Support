<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Asset::class);

        return view('admin.assets.index', ['assets' => Asset::with(['company', 'site', 'assignedUser'])->orderBy('tag')->paginate(25)]);
    }

    public function create()
    {
        $this->authorize('manage', Asset::class);

        return $this->form(new Asset);
    }

    public function store(Request $request)
    {
        $this->authorize('manage', Asset::class);

        $data = $this->validated($request);
        $asset = Asset::create($data);
        $this->recordEvent($asset, 'assigned', $request->user(), null, $data['assigned_user_id'] ?? null);

        return redirect()->route('admin.assets.index')->with('status', 'Asset created.');
    }

    public function edit(Asset $asset)
    {
        $this->authorize('manage', $asset);

        return $this->form($asset);
    }

    public function update(Request $request, Asset $asset)
    {
        $this->authorize('manage', $asset);

        $data = $this->validated($request);
        $previousUser = $asset->assigned_user_id;
        $previousSite = $asset->site_id;
        $asset->update($data);

        if (($data['assigned_user_id'] ?? null) !== $previousUser) {
            $this->recordEvent($asset, 'transferred', $request->user(), $previousUser, $data['assigned_user_id'] ?? null, $previousSite, $data['site_id'] ?? null);
        }

        return redirect()->route('admin.assets.index')->with('status', 'Asset updated.');
    }

    public function destroy(Asset $asset)
    {
        $this->authorize('manage', $asset);
        $asset->update(['status' => 'disposed']);
        $this->recordEvent($asset, 'disposed', request()->user(), $asset->assigned_user_id, null);

        return redirect()->route('admin.assets.index')->with('status', 'Asset marked disposed (history preserved).');
    }

    private function recordEvent(Asset $asset, string $type, $actor, ?int $fromUser, ?int $toUser, ?int $fromSite = null, ?int $toSite = null): void
    {
        $asset->events()->create([
            'event_type' => $type,
            'from_user_id' => $fromUser,
            'to_user_id' => $toUser,
            'from_site_id' => $fromSite,
            'to_site_id' => $toSite,
            'actor_id' => $actor?->id,
            'occurred_at' => now(),
        ]);
    }

    private function form(Asset $asset)
    {
        return view('admin.assets.form', [
            'asset' => $asset->load('events.actor', 'events.fromUser', 'events.toUser'),
            'companies' => Company::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'vendors' => Vendor::orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $ignoreId = $request->route('asset')?->id;

        return $request->validate([
            'tag' => ['required', 'string', 'max:50', Rule::unique('assets', 'tag')->ignore($ignoreId)],
            'type' => ['required', 'string', 'max:100'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial' => ['nullable', 'string', 'max:150'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'company_id' => ['required', 'exists:companies,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'status' => ['required', 'in:in_stock,assigned,loaned,in_repair,retired,disposed'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_expiry' => ['nullable', 'date'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'lease_subscription_ref' => ['nullable', 'string', 'max:150'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'approved_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'cost_code' => ['nullable', 'string', 'max:100'],
            'network_notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
