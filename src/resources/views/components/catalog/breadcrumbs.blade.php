@props(['items'])
<nav class="breadcrumbs" aria-label="{{ __('Breadcrumbs') }}">
    <ol>
        @foreach ($items as $item)
            <li>
                @if ($loop->last)
                    <span aria-current="page">{{ $item['name'] }}</span>
                @else
                    <a href="{{ $item['url'] }}">{{ $item['name'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
