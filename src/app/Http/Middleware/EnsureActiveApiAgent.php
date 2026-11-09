<?php

namespace App\Http\Middleware;

use App\Models\ApiAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API is for agents only, and only while the agent is switched on.
 */
class EnsureActiveApiAgent
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agent = auth('sanctum')->user();

        if (! $agent instanceof ApiAgent || ! $agent->is_active) {
            return response()->json(['message' => 'This token is not allowed to use the API.'], 403);
        }

        return $next($request);
    }
}
