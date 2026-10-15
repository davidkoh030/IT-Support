@props(['active', 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center gap-2 px-3 py-4 border-b-[3px] border-brand text-base font-semibold text-brand focus:outline-none transition'
            : 'inline-flex items-center gap-2 px-3 py-4 border-b-[3px] border-transparent text-base font-medium text-gray-700 hover:text-brand hover:border-brand/40 focus:outline-none transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)<span aria-hidden="true">{{ $icon }}</span>@endif
    {{ $slot }}
</a>
