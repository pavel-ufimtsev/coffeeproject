@props(['title', 'description', 'lead' => null, 'crumbs' => [], 'jsonLd' => [], 'wide' => false])
{{-- A help page: breadcrumbs, heading and the help menu beside the text. --}}
@php
    $items = [['name' => __('Home'), 'url' => route('home')], ['name' => __('Help'), 'url' => route('help')]];
    if (! request()->routeIs('help')) {
        $items = [...$items, ...$crumbs, ['name' => $title, 'url' => url()->current()]];
    }
    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
            '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => $item['url'],
        ])->all(),
    ];
    $menu = [
        ['route' => 'help.delivery', 'name' => __('Delivery and payment'), 'match' => 'help.delivery*'],
        ['route' => 'help.returns', 'name' => __('Returns and exchanges'), 'match' => 'help.returns'],
        ['route' => 'help.faq', 'name' => __('Questions and answers'), 'match' => 'help.faq'],
        ['route' => 'wholesale', 'name' => __('For business'), 'match' => 'wholesale'],
        ['route' => 'contacts', 'name' => __('Contacts'), 'match' => 'contacts'],
    ];
@endphp
<x-layouts.shop :meta="[
    'title' => $title.' | '.config('shop.name'),
    'description' => $description,
    'canonical' => url()->current(),
]" :json-ld="[$breadcrumbLd, ...$jsonLd]">
    <div class="container help">
        <x-catalog.breadcrumbs :items="$items" />

        <div @class(['help__layout', 'help__layout--wide' => $wide])>
            <article class="help__main">
                <header class="help__header">
                    <h1>{{ $title }}</h1>
                    @if ($lead)
                        <p class="help__lead">{{ $lead }}</p>
                    @endif
                </header>
                {{ $slot }}
            </article>

            <nav class="help__nav" aria-label="{{ __('Help') }}">
                <p class="help__nav-title">{{ __('Help') }}</p>
                <ul>
                    @foreach ($menu as $entry)
                        <li><a href="{{ route($entry['route']) }}" @if (request()->routeIs($entry['match'])) aria-current="page" @endif>{{ $entry['name'] }}</a></li>
                    @endforeach
                </ul>
                <div class="help__ask">
                    <p>{{ __('Did not find the answer?') }}</p>
                    <a href="{{ route('contacts') }}" class="button button--secondary button--small">{{ __('Write to us') }}</a>
                </div>
            </nav>
        </div>
    </div>
</x-layouts.shop>
