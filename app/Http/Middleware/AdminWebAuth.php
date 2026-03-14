<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminWebAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('admin_id');

        if (! $adminId) {
            return redirect()->route('admin.login.form');
        }

        $admin = User::query()
            ->where('id', $adminId)
            ->where('is_admin', true)
            ->first();

        if (! $admin) {
            $request->session()->forget('admin_id');

            return redirect()->route('admin.login.form');
        }

        $request->attributes->set('admin', $admin);

        return $next($request);
    }
}
