@props(['active'])

@php
$fontSize = auth()->user()?->isAdmin() ? 'text-[13px]' : 'text-sm';
$classes = ($active ?? false)
            ? "inline-flex shrink-0 items-center whitespace-nowrap px-1 pt-1 border-b-2 border-indigo-400 {$fontSize} font-bold leading-5 text-white focus:outline-none transition duration-150 ease-in-out"
            : "inline-flex shrink-0 items-center whitespace-nowrap px-1 pt-1 border-b-2 border-transparent {$fontSize} font-medium leading-5 text-slate-300 hover:text-white hover:border-slate-600 focus:outline-none focus:text-white focus:border-slate-600 transition duration-150 ease-in-out";
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
