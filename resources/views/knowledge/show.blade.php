<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $article->title }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="text-sm text-gray-500 mb-4">Audience: {{ $article->audience }} - Reviewed by {{ $article->review_date?->format('d M Y') }}</div>
                <div class="prose max-w-none whitespace-pre-line text-gray-800">{{ $article->body }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
