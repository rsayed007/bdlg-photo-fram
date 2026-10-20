<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UI language: session choice (via the switcher) → default `bn`.
 */
class SetLocale
{
    public const SUPPORTED = ['bn', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale', 'bn');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
