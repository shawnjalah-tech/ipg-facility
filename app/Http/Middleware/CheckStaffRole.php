<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStaffRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === 'staff', 403, 'Staff access required.');

        return $next($request);
    }
}
