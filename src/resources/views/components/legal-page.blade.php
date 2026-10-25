@props(['title', 'description', 'updated', 'sections' => []])
{{-- A legal document: heading, edition date, contents and the numbered sections in the slot. --}}
<x-layouts.shop :meta="[
    'title' => $title.' | '.config('shop.name'),
    'description' => $description,
    'canonical' => url()->current(),
]">
    <article class="container legal">
        <header class="legal__header">
            <h1>{{ $title }}</h1>
            <p class="legal__updated">{{ __('Edition of :date', ['date' => $updated]) }}</p>
        </header>

        @if ($sections)
            <nav class="legal__toc" aria-labelledby="legal-toc">
                <p id="legal-toc" class="legal__toc-title">{{ __('Contents') }}</p>
                <ol>
                    @foreach ($sections as $id => $name)
                        <li><a href="#{{ $id }}">{{ $name }}</a></li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <div class="legal__body prose">
            {{ $slot }}
        </div>
    </article>
</x-layouts.shop>
