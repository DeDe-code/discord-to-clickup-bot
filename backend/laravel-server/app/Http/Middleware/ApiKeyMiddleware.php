<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-Key') 
            ?? $request->query('api_key') 
            ?? $request->input('api_key');

        if ($token !== config('app.api_secret')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
