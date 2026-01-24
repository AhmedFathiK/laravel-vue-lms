<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConvertCamelCase
{
    /**
     * Handle an incoming request.
     *
     * @param  \\Illuminate\\Http\\Request  $request
     * @param  \\Closure(\\Illuminate\\Http\\Request): (\\Illuminate\\Http\\Response|\\Illuminate\\Http\\RedirectResponse)  $next
     * @return \\Illuminate\\Http\\Response|\\Illuminate\\Http\\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Convert query parameters (GET)
        if ($request->query->count()) {
            $request->query->replace($this->convertToSnakeCase($request->query->all()));
        }

        // Convert request body (POST, JSON, PUT…)
        if ($request->isJson()) {
            $request->json()->replace($this->convertToSnakeCase($request->json()->all()));
        } else {
            $request->request->replace($this->convertToSnakeCase($request->request->all()));
        }

        // Convert files
        if ($request->files->count()) {
            $request->files->replace($this->convertToSnakeCase($request->files->all()));
        }

        // Handle sort_by specifically if it exists after conversion
        if ($request->has('sort_by')) {
            $sortBy = $request->input('sort_by');
            if (is_array($sortBy)) {
                $sortBy = array_map(function ($item) {
                    if (is_array($item) && isset($item['key'])) {
                        $item['key'] = \Illuminate\Support\Str::snake($item['key']);
                    }

                    return $item;
                }, $sortBy);
            } else {
                $sortBy = \Illuminate\Support\Str::snake($sortBy);
            }
            $request->merge([
                'sort_by' => $sortBy,
            ]);
        }

        return $next($request);
    }



    /**
     * Convert array keys to snake_case.
     *
     * @param  array  $data
     * @return array
     */
    protected function convertToSnakeCase(array $data): array
    {
        $converted = [];
        foreach ($data as $key => $value) {
            $converted[Str::snake($key)] = is_array($value)
                ? $this->convertToSnakeCase($value)
                : $value;
        }
        return $converted;
    }
}
