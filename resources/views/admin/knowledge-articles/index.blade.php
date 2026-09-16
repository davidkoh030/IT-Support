<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        @if(session('status'))<div class="mb-4 p-3 bg-green-50 text-green-700 rounded">{{ session('status') }}</div>@endif
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between mb-4">
                <h3 class="font-semibold text-gray-800">Knowledge articles</h3>
                <a href="{{ route('admin.knowledge-articles.create') }}" class="text-sm px-3 py-1.5 bg-indigo-600 text-white rounded">+ New article</a>
            </div>
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead><tr class="text-left text-gray-500"><th class="py-2">Title</th><th>Status</th><th>Review date</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($articles as $article)
                        <tr>
                            <td class="py-2">{{ $article->title }}</td>
                            <td class="capitalize">{{ $article->status }}</td>
                            <td>{{ $article->review_date?->toDateString() }}</td>
                            <td class="flex gap-2">
                                <a href="{{ route('admin.knowledge-articles.edit', $article) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.knowledge-articles.destroy', $article) }}" onsubmit="return confirm('Delete this article?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
</x-app-layout>
