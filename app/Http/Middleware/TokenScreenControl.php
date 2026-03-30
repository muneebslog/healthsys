<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenScreenControl
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $secret = config('hms.token_screen_control_secret');
        if (is_string($secret) && $secret !== '') {
            $provided = (string) $request->header('X-HMS-Control-Secret', '');
            if (hash_equals($secret, $provided)) {
                return $next($request);
            }
        }

        abort(403, 'Queue control requires sign-in or a valid control secret.');
    }
}
