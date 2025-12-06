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
            $snakeQuery = $this->convertToSnakeCase($request->query->all());
            $request->query->replace($snakeQuery);
        }

        // Convert request body (POST, JSON, PUT…)
        if ($request->request->count()) {
            $snakeBody = $this->convertToSnakeCase($request->request->all());
            $request->request->replace($snakeBody);
        }

        if ($request->has('sort_by')) {
            $request->merge([
                'sort_by' => \Illuminate\Support\Str::snake($request->sort_by)
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
