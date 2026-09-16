<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Help articles') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white rounded-lg shadow-sm p-4">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search help articles..." class="w-full rounded-md border-gray-300">
            </form>

            <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
                @forelse($articles as $article)
                    <a href="{{ route('knowledge.show', $article) }}" class="block p-4 hover:bg-gray-50">
                        <div class="font-medium text-indigo-600">{{ $article->title }}</div>
                        <div class="text-sm text-gray-500">{{ $article->audience }}</div>
                    </a>
                @empty
                    <p class="p-4 text-gray-500">No articles found.</p>
                @endforelse
            </div>

            {{ $articles->links() }}
        </div>
    </div>
</x-app-layout>
