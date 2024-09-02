<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\SupplierController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [LoginController::class, 'registerOwner']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::get('/validateToken', [LoginController::class, 'validationToken']);

        Route::apiResource('organizations', OrganizationController::class);
        Route::apiResource('stores', StoreController::class);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('suppliers', SupplierController::class);

        Route::get('suppliers/{supplier}/contacts', [SupplierController::class, 'contactIndex']);
        Route::post('suppliers/{supplier}/contacts', [SupplierController::class, 'contactStore']);
        Route::put('suppliers/{supplier}/contacts/{contact}', [SupplierController::class, 'contactUpdate']);
        Route::delete('suppliers/{supplier}/contacts/{contact}', [SupplierController::class, 'contactDestroy']);
    });
});
