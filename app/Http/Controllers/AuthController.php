<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\ApiService;
class AuthController extends Controller
{
    protected $apiService;
    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        $credentials = $request->only('email', 'password');

        $token = Auth::attempt($credentials);
        $token = auth()->attempt($credentials);


        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos Incorrectos',
            ], 400);
        }

        $user = Auth::user();

        if($user->status == 0){
            return $this->apiService->sendResponse([], 'Usuaro suspendido', 404, false);
        }
        $roleWithPermissions = $user->roles()->with('permissions')->first();
        $user['role'] =  $roleWithPermissions;
        return response()->json([
            'status' => true,
            'data' => $user,
            'access_token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = Auth::login($user);
        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'user' => $user,
            'authorisation' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status' => true,
            'user' => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }
}
