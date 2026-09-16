<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $article->exists ? 'Edit' : 'New' }} article</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $article->exists ? route('admin.knowledge-articles.update', $article) : route('admin.knowledge-articles.store') }}" class="space-y-4">
                @csrf
                @if($article->exists) @method('PUT') @endif
                <div><x-input-label value="Title" /><x-text-input name="title" class="mt-1 block w-full" :value="old('title', $article->title)" required /></div>
                <div><x-input-label value="Body" /><textarea name="body" rows="8" required class="mt-1 block w-full rounded-md border-gray-300">{{ old('body', $article->body) }}</textarea></div>
                <div><x-input-label value="Audience" /><x-text-input name="audience" class="mt-1 block w-full" :value="old('audience', $article->audience)" /></div>
                <div><x-input-label value="Review date" /><input type="date" name="review_date" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('review_date', $article->review_date?->toDateString()) }}"></div>
                <div>
                    <x-input-label value="Status" />
                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="draft" @selected(old('status', $article->status ?? 'draft') === 'draft')>Draft</option>
                        <option value="published" @selected(old('status', $article->status ?? '') === 'published')>Published</option>
                    </select>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
