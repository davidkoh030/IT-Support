<div class="flex flex-wrap gap-2 mb-6">
    @foreach([
        'admin.companies.index' => 'Companies',
        'admin.projects.index' => 'Projects',
        'admin.sites.index' => 'Sites',
        'admin.calendars.index' => 'Calendars',
        'admin.categories.index' => 'Categories',
        'admin.sla-policies.index' => 'SLA policies',
        'admin.priority-matrix.index' => 'Priority matrix',
        'admin.vendors.index' => 'Vendors',
        'admin.assets.index' => 'Assets',
        'admin.users.index' => 'Users & access',
        'admin.knowledge-articles.index' => 'Knowledge articles',
        'admin.request-templates.index' => 'Request templates',
    ] as $route => $label)
        <a href="{{ route($route) }}" class="px-3 py-1.5 rounded text-sm {{ request()->routeIs($route) ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-700' }}">{{ $label }}</a>
    @endforeach
</div>
