@props(['heading', 'preheader' => null])
{{-- Customer emails: logo, white card, contacts. Table layout and inline styles so Gmail, Outlook and the Mail apps agree. --}}
<!DOCTYPE html>
<html lang="ru" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $heading }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; }
        .text p { margin: 0 0 16px; }
        .text p:last-child { margin-bottom: 0; }
        .text a { color: #2e7d32; }
        .text strong { color: #151515; }
        @media only screen and (max-width: 600px) {
            .card { padding: 28px 20px !important; }
            .heading { font-size: 22px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background: #f3f2ef;">
    @if ($preheader)
        <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">{{ $preheader }}&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f3f2ef;">
        <tr>
            <td align="center" style="padding: 32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; font-family: Nunito, 'Segoe UI', Helvetica, Arial, sans-serif;">
                    <tr>
                        <td style="padding: 0 4px 20px;">
                            <a href="{{ route('home') }}" style="text-decoration: none; color: #151515;">
                                <img src="{{ asset('images/shop/logo.png') }}" alt="" width="36" height="36" style="vertical-align: middle; border: 0;">
                                <span style="vertical-align: middle; font-size: 20px; font-weight: 800; color: #151515; padding-left: 8px;">{{ config('shop.name') }}</span>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td class="card" style="background: #ffffff; border-radius: 16px; padding: 36px 40px; color: #333333; font-size: 16px; line-height: 1.6;">
                            <h1 class="heading" style="margin: 0 0 20px; font-size: 26px; line-height: 1.25; font-weight: 800; color: #151515;">{{ $heading }}</h1>
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px 4px 0; font-size: 13px; line-height: 1.6; color: #8a8a8a;">
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', config('shop.phone')) }}" style="color: #555555; text-decoration: none;">{{ config('shop.phone') }}</a>
                            &nbsp;·&nbsp;
                            <a href="mailto:{{ config('shop.email') }}" style="color: #555555; text-decoration: none;">{{ config('shop.email') }}</a>
                            &nbsp;·&nbsp;
                            <a href="{{ route('home') }}" style="color: #555555; text-decoration: none;">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a>
                            <br>
                            {{ config('shop.legal_name') }}, {{ config('shop.legal_address') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
