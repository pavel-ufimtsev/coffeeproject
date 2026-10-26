@props(['name'])
{{-- Line icons for the "About this coffee" facts, drawn like the header icons. --}}
@php
    $paths = [
        'country' => '<circle cx="12" cy="12" r="9.5"/><path d="M2.5 12h19M12 2.5c2.6 2.8 3.9 6 3.9 9.5s-1.3 6.7-3.9 9.5c-2.6-2.8-3.9-6-3.9-9.5s1.3-6.7 3.9-9.5Z"/>',
        'region' => '<path d="M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'farm' => '<path d="M3.5 20.5V10L12 4l8.5 6v10.5"/><path d="M9.5 20.5v-6h5v6M2 20.5h20"/>',
        'altitude' => '<path d="m2.5 20 7-12 4 6.5 2.5-3.5 5.5 9Z"/><path d="M9.5 8V3.5l3.5 1.25-3.5 1.25"/>',
        'harvest' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'process' => '<path d="M12 3.5c3 3.8 6 6.4 6 10a6 6 0 0 1-12 0c0-3.6 3-6.2 6-10Z"/><path d="M9.5 14.5a2.5 2.5 0 0 0 2.5 2.5"/>',
        'varieties' => '<path d="M12 21v-9"/><path d="M12 12c0-4.4 3.1-7.5 8-7.5 0 4.9-3.1 7.5-8 7.5ZM12 15c0-3.3-2.3-5.5-6.5-5.5 0 3.7 2.3 5.5 6.5 5.5Z"/>',
        'roast' => '<path d="M12 3c1 3.6 5.5 5.6 5.5 10.5a5.5 5.5 0 0 1-11 0c0-2.6 1.4-4.3 2.6-5.4.4 1.9 1.4 2.9 2.4 2.9 0-3.2-.9-5.2.5-8Z"/>',
        'sca_score' => '<circle cx="12" cy="9" r="5.5"/><path d="m8.6 13.4-1.6 7.6 5-2.8 5 2.8-1.6-7.6"/>',
        'tasting_notes' => '<path d="M4 10h13v4a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5Z"/><path d="M17 11.5h1.25a2.25 2.25 0 0 1 0 4.5H17M8.5 3.5c-.8 1 .8 2 0 3.5M12.5 3.5c-.8 1 .8 2 0 3.5"/>',
    ];
@endphp
@isset($paths[$name])
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $paths[$name] !!}</svg>
@endisset
