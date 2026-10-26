<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r): array
    {
        $r->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::query()->where('email', $r->input('email'))->first();

        if (! $user || ! $user->is_active || ! Hash::check($r->input('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $plainTextToken = Str::random(64);
        $user->apiTokens()->create([
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'token' => $plainTextToken,
            'user' => $user->load('roles:id,name'),
        ];
    }

    public function me(Request $r): User
    {
        return $r->user()->load('roles:id,name');
    }

    public function logout(Request $r): Response
    {
        $r->attributes->get('api_token')->delete();

        return response()->noContent();
    }
}
