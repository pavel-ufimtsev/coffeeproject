<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends admins who have not set up two-factor authentication to their
 * security settings until they do. Controlled by ADMIN_REQUIRE_TWO_FACTOR.
 */
class EnsureTwoFactorIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            config('app.admin.require_two_factor')
            && Features::enabled(Features::twoFactorAuthentication())
            && ! $request->user()->hasEnabledTwoFactorAuthentication()
            && ! $request->routeIs('admin.profile.*', 'admin.security.*', 'admin.password.*', 'admin.appearance.*')
        ) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Set up two-factor authentication to use the admin area.'),
            ]);

            return to_route('admin.security.edit');
        }

        return $next($request);
    }
}
