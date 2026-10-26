@php
    $label = 'padding: 6px 16px 6px 0; vertical-align: top; color: #8a8a8a; white-space: nowrap;';
    $value = 'padding: 6px 0; vertical-align: top; color: #151515;';
@endphp
<x-mail.layout :heading="__('Message from the website')" :preheader="\Illuminate\Support\Str::limit($data['message'], 120)">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 15px; line-height: 1.45;">
        <tr>
            <td style="{{ $label }}">{{ __('Topic') }}</td>
            <td style="{{ $value }}">{{ $topic }}</td>
        </tr>
        <tr>
            <td style="{{ $label }}">{{ __('Name') }}</td>
            <td style="{{ $value }}">{{ $data['name'] }}</td>
        </tr>
        <tr>
            <td style="{{ $label }}">{{ __('Email') }}</td>
            <td style="{{ $value }}"><a href="mailto:{{ $data['email'] }}" style="color: #2e7d32;">{{ $data['email'] }}</a></td>
        </tr>
        @if ($data['phone'])
            <tr>
                <td style="{{ $label }}">{{ __('Phone') }}</td>
                <td style="{{ $value }}"><a href="tel:{{ preg_replace('/[^\d+]/', '', $data['phone']) }}" style="color: #2e7d32;">{{ $data['phone'] }}</a></td>
            </tr>
        @endif
        @foreach ($data['details'] ?? [] as $detailLabel => $detailValue)
            <tr>
                <td style="{{ $label }}">{{ $detailLabel }}</td>
                <td style="{{ $value }}">{{ $detailValue }}</td>
            </tr>
        @endforeach
    </table>

    @if (filled($data['message']))
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #eaeaea; white-space: pre-line;">{{ $data['message'] }}</div>
    @endif

    <p style="margin: 24px 0 0; font-size: 13px; color: #8a8a8a;">{{ __('Reply to this email to answer :name.', ['name' => $data['name']]) }}</p>
</x-mail.layout>
