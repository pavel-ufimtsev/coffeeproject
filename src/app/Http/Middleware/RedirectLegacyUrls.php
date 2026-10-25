<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends requests for old URLs (stored in the redirects table) to their new
 * address, keeping the query string, and counts each hit.
 */
class RedirectLegacyUrls
{
    public const CACHE_KEY = 'redirects.map';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = Redirect::normalizePath($request->getPathInfo());
        $target = self::map()[$path] ?? null;

        if ($target === null) {
            return $next($request);
        }

        Redirect::where('from_path', $path)->incrementEach(['hits' => 1], ['last_hit_at' => now()]);

        $query = $request->getQueryString();

        return redirect($target['to'].($query ? "?{$query}" : ''), $target['code']);
    }

    /**
     * Get all redirects keyed by old path. Cached until a redirect changes.
     *
     * @return array<string, array{to: string, code: int}>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Redirect::all(['from_path', 'to_path', 'status_code'])
            ->mapWithKeys(fn (Redirect $r) => [$r->from_path => ['to' => $r->to_path, 'code' => $r->status_code]])
            ->all());
    }
}
