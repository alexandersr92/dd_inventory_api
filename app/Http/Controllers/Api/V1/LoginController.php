<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;



class LoginController extends Controller
{
    public function login(Request $request)
    {


        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || ! Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNPROCESSABLE_ENTITY); //422

        }



        return response()->json([
            'data' => [
                'attributes' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'organization_id' => $user->organization_id,
                    'device_name' => $request->device_name,
                    'is_seller' => $user->is_seller,
                    'is_owner' => $user->is_owner,
                ],
                'token' => $user->createToken($request->device_name)->plainTextToken,
            ]
        ], Response::HTTP_OK); //200
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ], Response::HTTP_OK); //200
    }

    //register new user 

    //login seller
    public function loginSeller(Request $request) {}
}
