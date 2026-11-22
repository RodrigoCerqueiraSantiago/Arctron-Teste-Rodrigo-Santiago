<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrf(Request $request)
    {
        return response()->json(['token' => $request->session()->token()]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        if (strlen($credentials['password']) > 72 || str_contains($credentials['password'], "\0") || ! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json(['user' => $this->userData($request), 'token' => $request->session()->token()]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userData($request)]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function userData(Request $request): array
    {
        $user = $request->user();

        return ['name' => $user->name, 'email' => $user->email, 'role' => $user->role];
    }
}
