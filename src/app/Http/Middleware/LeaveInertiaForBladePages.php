<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin is an Inertia app, but login, password confirmation and the shop
 * are plain Blade pages. When an Inertia visit from the admin lands on one of
 * them (logging out, a password confirmation redirect, "view shop"), tell the
 * client to load it as a full page instead of showing it in an error modal.
 */
class LeaveInertiaForBladePages
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && $request->header('X-Inertia')
            && ! $response->headers->has('X-Inertia')
            && ! $response->isRedirection()
        ) {
            return Inertia::location($request->fullUrl());
        }

        return $response;
    }
}
