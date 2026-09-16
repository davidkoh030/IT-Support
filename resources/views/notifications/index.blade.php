<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Notifications') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
                @forelse($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        <button type="submit" class="w-full text-left p-4 hover:bg-gray-50 flex justify-between items-center {{ $notification->read_at ? 'text-gray-500' : 'font-medium text-gray-800' }}">
                            <span>{{ $notification->data['message'] ?? 'Notification' }} @if(!empty($notification->data['ticket_number'])) ({{ $notification->data['ticket_number'] }}) @endif</span>
                            <span class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </button>
                    </form>
                @empty
                    <p class="p-4 text-gray-500">No notifications yet.</p>
                @endforelse
            </div>
            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
