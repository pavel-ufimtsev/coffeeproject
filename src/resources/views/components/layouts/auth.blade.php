@props(['title', 'description' => null])
<x-layouts.shop :title="$title">
    <div class="container auth">
        <div class="card auth__card">
            <h1 class="auth__title">{{ $title }}</h1>
            @if ($description)
                <p class="muted">{{ $description }}</p>
            @endif

            {{ $slot }}
        </div>

        @isset($footer)
            <p class="auth__footer">{{ $footer }}</p>
        @endisset
    </div>
</x-layouts.shop>
