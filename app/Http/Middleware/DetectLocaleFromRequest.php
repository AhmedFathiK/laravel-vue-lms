<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class DetectLocaleFromRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // First check header (from API requests)
        $locale = $request->header('X-Locale');

        // If not in header, try cookie (from browser)
        if (!$locale) {
            // Find any cookie ending with -language
            foreach ($request->cookies as $name => $value) {
                if (str_ends_with($name, '-language')) {
                    $locale = $value;
                    break;
                }
            }
        }

        // If locale is found, set it as the application locale
        if ($locale) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
