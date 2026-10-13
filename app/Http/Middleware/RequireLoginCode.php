<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireLoginCode
{
    public function handle(Request $request, Closure $next)
    {
        if (config('login_code.enabled') && ($request->session()->get('verified_owner_email') !== app(\App\Services\LoginCodeService::class)->email($request->user()) ||
            !$request->session()->get('verified_owner_email') || $request->user()->username !== config('login_code.owner_username'))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) abort(401);
            return redirect()->guest(route('login'));
        }
        return $next($request);
    }
}
