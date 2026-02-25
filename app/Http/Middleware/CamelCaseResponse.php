<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;

class CamelCaseResponse
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Only transform JSON
        $contentType = $response->headers->get('Content-Type');
        if (!$contentType || !str_contains($contentType, 'application/json')) {
            return $response;
        }

        $data = json_decode($response->getContent(), true);
        if ($data === null) {
            return $response;
        }

        $camel = $this->toCamel($data);

        $response->setContent(json_encode($camel));

        return $response;
    }

    protected function toCamel($data)
    {
        if (is_array($data)) {
            $new = [];

            foreach ($data as $key => $value) {
                $newKey = is_string($key) ? Str::camel($key) : $key;
                $new[$newKey] = $this->toCamel($value);
            }

            return $new;
        }

        return $data;
    }
}
