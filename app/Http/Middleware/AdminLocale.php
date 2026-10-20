<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The admin panel is English-only (the public site defaults to Bangla). */
class AdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('en');

        return $next($request);
    }
}
