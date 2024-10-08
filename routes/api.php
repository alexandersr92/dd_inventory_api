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
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\InventoryController;


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
        Route::apiResource('roles', RoleController::class);
        Route::get('roles/permissions', [RoleController::class, 'premmisionIndex']);
        Route::apiResource('tags', TagController::class)->except(['show']);
        Route::apiResource('categories', CategoryController::class)->except(['show']);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('inventories', InventoryController::class);
        Route::get('inventories/{inventory}/products', [InventoryController::class, 'showProducts']);
    });
});
