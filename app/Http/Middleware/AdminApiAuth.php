<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['pesan' => 'Token admin wajib dikirim.'], 401);
        }

        $hash = hash('sha256', $token);
        $admin = User::query()
            ->where('is_admin', true)
            ->where('token_api', $hash)
            ->first();

        if (! $admin) {
            return response()->json(['pesan' => 'Token admin tidak valid.'], 401);
        }

        $request->setUserResolver(fn () => $admin);

        return $next($request);
    }
}
