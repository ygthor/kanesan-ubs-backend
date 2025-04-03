<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        // Example: Check if the request has a valid token
        // if ($request->header('X-APP-TOKEN') !== env('APP_SECRET_TOKEN')) {
        //     return response()->json(['message' => 'Invalid token'], 403);
        // }

        return $next($request);
    }
}
