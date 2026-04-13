<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) { return redirect()->route("landingpage"); } return $next($request);
    }
}