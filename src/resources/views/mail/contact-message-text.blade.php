{{ __('Message from the website') }}

{{ __('Topic') }}: {{ $topic }}
{{ __('Name') }}: {{ $data['name'] }}
{{ __('Email') }}: {{ $data['email'] }}
@if ($data['phone'])
{{ __('Phone') }}: {{ $data['phone'] }}
@endif

{{ $data['message'] }}

--
{{ __('Reply to this email to answer :name.', ['name' => $data['name']]) }}
