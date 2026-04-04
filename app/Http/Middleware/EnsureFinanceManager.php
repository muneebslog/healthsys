<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceManager
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('hms.skip_role_page_guards')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user?->role === UserRole::FinanceManager) {
            return $next($request);
        }

        abort(403);
    }
}
