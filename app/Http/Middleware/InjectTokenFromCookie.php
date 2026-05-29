<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InjectTokenFromCookie
{
    public function handle(Request $request, Closure $next)
    {
        // Only inject when no Authorization header is already present.
        // This keeps CLI / mobile / direct API clients working unchanged.
        if (!$request->bearerToken() && $request->cookie('hrms_token')) {
            $request->headers->set(
                'Authorization',
                'Bearer ' . $request->cookie('hrms_token')
            );
        }

        return $next($request);
    }
}
