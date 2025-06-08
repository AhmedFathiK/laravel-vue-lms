<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     * 
     * This middleware needs to be registered in the HTTP Kernel file in the web middleware group
     * Add to the $middlewareGroups array:
     * 'web' => [
     *    // ...
     *    \App\Http\Middleware\SetLocale::class,
     * ],
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated and has a locale preference
        if (Auth::check() && Auth::user()->locale) {
            App::setLocale(Auth::user()->locale);
        }
        // Else check for Accept-Language header
        else if ($request->hasHeader('Accept-Language')) {
            $locale = substr($request->header('Accept-Language'), 0, 2);
            App::setLocale($locale);
        }

        return $next($request);
    }
}
