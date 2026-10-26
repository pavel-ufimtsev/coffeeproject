@props(['url'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0;">
    <tr>
        <td style="background: #2e7d32; border-radius: 10px;">
            <a href="{{ $url }}" style="display: inline-block; padding: 13px 28px; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none;">{{ $slot }}</a>
        </td>
    </tr>
</table>
