<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Report an IT problem or request') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm p-6">

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-50 text-red-700 rounded" role="alert">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data"
                      x-data="ticketForm()" @submit="onSubmit">
                    @csrf
                    <input type="hidden" name="idempotency_key" x-bind:value="idempotencyKey">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="company_id" value="Company" />
                            <select id="company_id" name="company_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 text-lg py-3">
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected(old('company_id', $prefill['company_id']) == $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="project_id" value="Project (leave blank for HQ / shared services)" />
                            <select id="project_id" name="project_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-lg py-3">
                                <option value="">HQ / shared services</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }} ({{ $project->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="category_id" value="What is this about?" />
                            <select id="category_id" name="category_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 text-lg py-3">
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label value="Type" />
                            <div class="mt-2 flex gap-4">
                                <label class="inline-flex items-center text-base"><input type="radio" name="type" value="incident" checked class="mr-2 h-5 w-5"> Something is broken (incident)</label>
                            </div>
                            <div class="mt-1 flex gap-4">
                                <label class="inline-flex items-center text-base"><input type="radio" name="type" value="service_request" class="mr-2 h-5 w-5"> I need something (service request)</label>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="summary" value="Summary (one line)" />
                            <x-text-input id="summary" name="summary" class="mt-1 block w-full text-lg py-3" required maxlength="150" :value="old('summary')" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="description" value="Description" />
                            <textarea id="description" name="description" rows="4" required maxlength="5000"
                                      class="mt-1 block w-full rounded-md border-gray-300 text-lg">{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <x-input-label value="How is this affecting your work? (impact)" />
                            <select name="impact" required class="mt-1 block w-full rounded-md border-gray-300 text-lg py-3">
                                <option value="high">High - site/group-wide or a critical shared service</option>
                                <option value="medium">Medium - a team or important project function</option>
                                <option value="low">Low - an individual, non-critical function</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label value="How urgent is it?" />
                            <select name="urgency" required class="mt-1 block w-full rounded-md border-gray-300 text-lg py-3">
                                <option value="high">High - work stopped, no workable alternative, or an imminent critical deadline</option>
                                <option value="medium">Medium - degraded, with a temporary workaround</option>
                                <option value="low">Low - can reasonably wait</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2 border-t pt-4">
                            <label class="inline-flex items-center text-base">
                                <input type="checkbox" name="security_flag" value="1" class="mr-2 h-5 w-5">
                                This may be a security incident (suspected compromise, phishing, lost/stolen device)
                            </label>
                            <p class="text-sm text-gray-500 mt-1">This restricts visibility to IT and routes it for immediate triage. IT will confirm the classification.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="affected_users_note" value="Who else is affected? (optional)" />
                            <x-text-input id="affected_users_note" name="affected_users_note" class="mt-1 block w-full" maxlength="255" :value="old('affected_users_note')" />
                        </div>

                        <div>
                            <x-input-label for="deadline_at" value="Deadline, if any (optional)" />
                            <input type="datetime-local" id="deadline_at" name="deadline_at" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('deadline_at') }}">
                        </div>
                        <div>
                            <x-input-label for="deadline_reason" value="Deadline reason (e.g. tender submission)" />
                            <x-text-input id="deadline_reason" name="deadline_reason" class="mt-1 block w-full" maxlength="255" :value="old('deadline_reason')" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="attachments" value="Photos or screenshots (optional, up to 5)" />
                            <input type="file" id="attachments" name="attachments[]" multiple accept="image/*,.pdf" class="mt-1 block w-full text-base">
                        </div>

                        @if($prefill['asset_id'])
                            <input type="hidden" name="asset_ids[]" value="{{ $prefill['asset_id'] }}">
                            <p class="sm:col-span-2 text-sm text-gray-500">Linked to the scanned asset (#{{ $prefill['asset_id'] }}).</p>
                        @endif
                        @if($prefill['site_id'])
                            <input type="hidden" name="site_id" value="{{ $prefill['site_id'] }}">
                        @endif
                    </div>

                    <div class="mt-6 flex items-center gap-4">
                        <button type="submit" x-bind:disabled="submitting"
                                class="px-6 py-4 bg-indigo-600 text-white text-lg font-semibold rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                            <span x-show="!submitting">Submit ticket</span>
                            <span x-show="submitting">Submitting...</span>
                        </button>
                        <span class="text-sm text-gray-500">Your form is kept if submission fails - nothing is marked submitted until confirmed.</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function ticketForm() {
            return {
                submitting: false,
                idempotencyKey: (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random(),
                onSubmit() {
                    // One idempotency key per attempt; a retry after a network
                    // error re-sends the same key so the server returns the
                    // same ticket instead of creating a duplicate.
                    this.submitting = true;
                },
            };
        }
    </script>
</x-app-layout>
