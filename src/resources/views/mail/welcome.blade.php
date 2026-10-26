<x-mail.layout :heading="$texts['heading']" :preheader="$texts['intro_text']">
    <div class="text">{{ $texts['intro'] }}</div>

    <x-mail.button :url="$shopUrl">{{ __('Choose coffee') }}</x-mail.button>

    <div class="text">{{ $texts['outro'] }}</div>
</x-mail.layout>
