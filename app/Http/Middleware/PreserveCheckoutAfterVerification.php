<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreserveCheckoutAfterVerification
{
    public const SESSION_KEY = 'auth.after_verification';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasVerifiedEmail()) {
            $request->session()->put(self::SESSION_KEY, route('checkout.index'));
        }

        return $next($request);
    }
}
