<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\AuthService;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $result = $this->authService->registerUser($validatedData);

        return response()->json([
            'message' => 'Registrasi berhasil',
            'data' => $result['user'],
            'access_token' => $result['token'],
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->loginUser($credentials);

        if (!$result) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        return response()->json([
            'message' => 'Login berhasil',
            'data' => $result['user'],
            'access_token' => $result['token'],
        ]);
    }

    public function logout(Request $request)
    {
        $this->authService->logoutUser($request->user());

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }
}