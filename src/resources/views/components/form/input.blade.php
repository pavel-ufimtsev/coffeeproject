@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'bag' => 'default',
    'hint' => null,
])
@php
    $id = $attributes->get('id', $bag === 'default' ? $name : "{$bag}-{$name}");
    $error = $errors->getBag($bag)->first($name);
    $describedBy = collect([$hint ? "{$id}-hint" : null, $error ? "{$id}-error" : null])->filter()->implode(' ');
@endphp
<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('id') }}
    >
    @if ($hint)
        <p class="field__hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @if ($error)
        <p class="field__error" id="{{ $id }}-error">{{ $error }}</p>
    @endif
</div>
