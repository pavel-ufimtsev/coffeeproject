@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'bag' => 'default',
    'span' => null,
])
@php
    $id = $attributes->get('id', "checkout-{$name}");
    $error = $errors->getBag($bag)->first($name);
@endphp
{{-- Shopify-style field: the label sits inside the box and moves up once there is a value. --}}
<div @class(['float-field', "float-field--{$span}" => $span, 'float-field--textarea' => $type === 'textarea']) data-field>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" placeholder=" " rows="3"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id') }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" placeholder=" "
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id') }}>
    @endif
    <label for="{{ $id }}">{{ $label }}</label>
    <p class="float-field__error" id="{{ $id }}-error" @unless ($error) hidden @endunless>{{ $error }}</p>
</div>
