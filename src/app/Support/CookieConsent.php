<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The visitor's answer to the cookie banner, kept in a cookie for a year.
 *
 * "necessary" allows only the cookies the site cannot work without; "all"
 * also allows analytics (Yandex.Metrica). Ask allowsAnalytics() before
 * loading such a script.
 */
class CookieConsent
{
    public const COOKIE = 'cookie_consent';

    public const ALL = 'all';

    public const NECESSARY = 'necessary';

    /** How long one answer lasts before the banner asks again. */
    public const LIFETIME_DAYS = 365;

    public static function given(Request $request): bool
    {
        return in_array($request->cookie(self::COOKIE), [self::ALL, self::NECESSARY], true);
    }

    public static function allowsAnalytics(Request $request): bool
    {
        return $request->cookie(self::COOKIE) === self::ALL;
    }

    public static function cookie(string $choice): Cookie
    {
        return cookie(self::COOKIE, $choice, self::LIFETIME_DAYS * 24 * 60, sameSite: 'lax');
    }
}
