<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The site is Russian by default. Adding ?lang=en (or ?lang=ru) to any URL
 * remembers that choice in a cookie and reloads the same page without the
 * parameter, so links and search engines only ever see the clean URL.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        if (is_string($requested) && $request->isMethod('GET')) {
            if ($this->isSupported($requested)) {
                Cookie::queue(self::COOKIE, $requested, 60 * 24 * 365);
            }

            return $this->redirectWithoutLangParameter($request);
        }

        $locale = $request->cookie(self::COOKIE);

        if (is_string($locale) && $this->isSupported($locale)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Determine whether the locale is one the site is translated into.
     */
    private function isSupported(string $locale): bool
    {
        return in_array($locale, config('app.locales'), true);
    }

    /**
     * Reload the current page without the "lang" query parameter.
     *
     * Inertia visits get a full page load, so the admin picks up the new
     * translations instead of keeping the ones it already has.
     */
    private function redirectWithoutLangParameter(Request $request): Response
    {
        $query = Arr::query(Arr::except($request->query(), 'lang'));
        $url = $request->url().($query !== '' ? '?'.$query : '');

        return $request->header('X-Inertia')
            ? Inertia::location($url)
            : redirect($url);
    }
}
