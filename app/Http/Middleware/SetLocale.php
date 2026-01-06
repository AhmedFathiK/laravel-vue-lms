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
        if (Auth::check() && Auth::user()->interface_language) {
            App::setLocale(Auth::user()->interface_language);
        }

        return $next($request);
    }
}
