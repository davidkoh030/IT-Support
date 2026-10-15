@php
    $user = Auth::user();
    $role = $user->hasRole('administrator') ? 'Admin' : null;
    $unread = $user->unreadNotifications()->count();
    $links = [
        ['route' => 'dashboard', 'active' => request()->routeIs('dashboard'), 'label' => 'Home', 'icon' => '🏠'],
        ['route' => 'tickets.create', 'active' => request()->routeIs('tickets.create'), 'label' => 'Report a problem', 'icon' => '📝'],
        ['route' => 'tickets.index', 'active' => request()->routeIs('tickets.index', 'tickets.show'), 'label' => 'Tickets', 'icon' => '🎫'],
        ['route' => 'service-status.index', 'active' => request()->routeIs('service-status.*'), 'label' => 'Service status', 'icon' => '📡'],
        ['route' => 'knowledge.index', 'active' => request()->routeIs('knowledge.*'), 'label' => 'Knowledge', 'icon' => '📚'],
    ];
@endphp
<nav x-data="{ open: false }" class="bg-white border-b-4 border-transparent" style="border-image: linear-gradient(90deg,#1a8a3c,#e9a400) 1;">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Brand bar -->
        <div class="flex items-center justify-between py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-4">
                <img src="{{ asset('images/soilbuild-logo.png') }}" alt="Soilbuild" class="h-12 w-auto">
                <span class="text-2xl font-extrabold tracking-wide"><span class="text-brand">SOIL</span><span class="text-brand-gold">BUILD</span> <span class="text-base font-semibold text-gray-500">IT</span></span>
                <span class="hidden md:inline text-sm font-semibold tracking-[0.2em] text-gray-500 uppercase">Service Desk</span>
            </a>

            <div class="hidden sm:flex items-center gap-3">
                <a href="{{ route('notifications.index') }}" class="relative px-3 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50" title="Notifications">
                    🔔
                    @if($unread > 0)
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 min-w-4 px-1 flex items-center justify-center">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>
                <span class="inline-flex items-center gap-2 pl-1 pr-4 py-1 rounded-full border border-gray-200 bg-brand-soft/40">
                    <span class="h-9 w-9 rounded-full bg-brand text-white text-sm font-bold flex items-center justify-center">{{ \Illuminate\Support\Str::of($user->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                    <span class="text-gray-800">{{ $user->name }}</span>
                    @if($role)<span class="text-xs font-bold uppercase tracking-wider text-brand">{{ $role }}</span>@endif
                </span>
                <a href="{{ route('profile.edit') }}" class="px-4 py-2 border border-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-50">Account</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-gray-200 rounded-lg font-semibold text-gray-800 hover:bg-gray-50">Sign out</button>
                </form>
            </div>

            <button @click="open = ! open" class="sm:hidden p-2 rounded-md text-gray-500 hover:bg-gray-100" aria-label="Menu">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>

        <!-- Tab bar -->
        <div class="hidden sm:flex flex-wrap gap-x-4 -mb-px">
            @foreach($links as $l)
                <x-nav-link :href="route($l['route'])" :active="$l['active']" :icon="$l['icon']">{{ $l['label'] }}</x-nav-link>
            @endforeach
            @can('manage-admin')
                <x-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.*')" icon="⚙️">Admin</x-nav-link>
            @endcan
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="open" x-cloak class="sm:hidden border-t border-gray-100">
        <div class="py-2">
            @foreach($links as $l)
                <x-responsive-nav-link :href="route($l['route'])" :active="$l['active']">{{ $l['icon'] }} {{ $l['label'] }}</x-responsive-nav-link>
            @endforeach
            @can('manage-admin')
                <x-responsive-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.*')">⚙️ Admin</x-responsive-nav-link>
            @endcan
        </div>
        <div class="pt-3 pb-2 border-t border-gray-200">
            <div class="px-4 font-medium text-gray-800">{{ $user->name }}</div>
            <div class="px-4 text-sm text-gray-500">{{ $user->email }}</div>
            <x-responsive-nav-link :href="route('profile.edit')">Account</x-responsive-nav-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Sign out</x-responsive-nav-link>
            </form>
        </div>
    </div>
</nav>
