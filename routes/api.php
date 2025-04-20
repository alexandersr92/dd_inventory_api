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
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\PurchasesController;


Route::prefix('v1')->group(function () {
    Route::get('/test',
        function (Request $request) {
            return response()->json([
                'message' => 'Its working good',
                'url' => $request->url(),
                'env' => config('app.env')
            ]);
        }
    );

    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [LoginController::class, 'registerOwner']);
    Route::middleware('auth:sanctum')->group(function () {
            //Excel Reports
            Route::get('invoices/export', [InvoiceController::class, 'exportInvoices']);
            Route::get('inventories/export', [InventoryController::class, 'exportInventory']);


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
        Route::delete('products/{product}/removeImage', [ProductController::class, 'removeImage']);
        Route::post('products/{product}/addImageToProduct', [ProductController::class, 'addImageToProduct']);
        Route::apiResource('inventories', InventoryController::class);
        Route::get('inventories/{inventory}/products', [InventoryController::class, 'showProducts']);
        Route::post('inventories/{inventory}/addProducts', [InventoryController::class, 'addProducts']);
        Route::post('inventories/{inventory}/removeProducts', [InventoryController::class, 'removeProducts']);
        Route::get('inventories/stores/{store}', [InventoryController::class, 'getProductByStore']);

        Route::apiResource('invoices', InvoiceController::class)->except(['destroy', 'update']);
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'cancel']);
        Route::post('credits/payment', [CreditController::class, 'payment']);
        Route::apiResource('credits', CreditController::class)->except(['store', 'update', 'destroy']);
        Route::get('credits-by-client', [CreditController::class, 'indexByClient']);
        Route::get('credits-by-client/{client_id}', [CreditController::class, 'indexByClientID']);

        Route::apiResource('purchases', PurchasesController::class);
        Route::post('purchases/upload', [PurchasesController::class, 'upload']);

    });
});
