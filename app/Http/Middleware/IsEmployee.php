<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->user()->role !== "employee") { return redirect()->route("error.permission"); } return $next($request);
    }
}