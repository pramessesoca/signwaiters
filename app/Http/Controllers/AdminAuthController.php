<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $admin = User::query()
            ->where('username', $data['username'])
            ->where('is_admin', true)
            ->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            return response()->json(['pesan' => 'Username atau password admin salah.'], 401);
        }

        $plainToken = Str::random(60);
        $admin->update([
            'token_api' => hash('sha256', $plainToken),
        ]);

        return response()->json([
            'pesan' => 'Login admin berhasil.',
            'token' => $plainToken,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'username' => $admin->username,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $admin = $request->user();

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'username' => $admin->username,
        ]);
    }

    public function logout(Request $request)
    {
        $admin = $request->user();

        $admin->update([
            'token_api' => null,
        ]);

        return response()->json(['pesan' => 'Logout admin berhasil.']);
    }
}
