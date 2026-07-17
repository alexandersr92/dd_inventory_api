<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\UserController;
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
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\SellerController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\MovementController;
use App\Http\Controllers\Api\V1\WooCommerceIntegrationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LandingAdminController;
use App\Http\Controllers\Api\V1\LandingPublicController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\SocialAuthController;

Route::prefix('v1')->group(function () {
    // TODO: Crear endpoint dedicado para upload de archivos
    // Route::post('/upload/{type}', [FileUploadController::class, 'upload']);
    
    Route::get('/test',
        function (Request $request) {
            return response()->json([
                'message' => 'Its working good',
                'url' => $request->url(),
                'env' => config('app.env'),
                'time' => date('Y-m-d H:i:s')
            ]);
        }
    );

    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [LoginController::class, 'registerOwner']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    Route::post('/auth/google', [SocialAuthController::class, 'handleGoogle']);

    // Public Landing Page Routes
    Route::get('/landing/content', [LandingPublicController::class, 'getPublicContent']);
    Route::get('/landing/plans', [LandingPublicController::class, 'getPublicPlans']);

    Route::middleware(['auth:sanctum', 'tenant.switch'])->group(function () {
            //Excel Reports
            Route::get('invoices/export', [InvoiceController::class, 'exportInvoices']);
            Route::get('inventories/export', [InventoryController::class, 'exportInventory']);


        Route::post('/logout', [LoginController::class, 'logout']);
        Route::get('/validateToken', [LoginController::class, 'validationToken']);
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);
        Route::get('/dashboard/chart', [DashboardController::class, 'chart']);
        Route::get('/sellerValidateToken', [LoginController::class, 'sellerValidateToken']);
        Route::put('/user/password', [LoginController::class, 'updatePassword']);
        Route::post('/user/google/link', [SocialAuthController::class, 'linkGoogle']);
        Route::post('/user/google/unlink', [SocialAuthController::class, 'unlinkGoogle']);


        Route::apiResource('organizations', OrganizationController::class, ['except' => ['destroy']]);
        Route::apiResource('users', UserController::class);
        Route::post('users/{id}/roles', [UserController::class, 'assignRole']);
        Route::post('users/{id}/stores', [UserController::class, 'assignStores']);
        Route::get('roles/permissions', [RoleController::class, 'premmisionIndex']);
        Route::apiResource('roles', RoleController::class);

        // Module: settings
        Route::middleware('module:settings')->group(function () {
            Route::apiResource('stores', StoreController::class);
            Route::patch('stores/{store}', [StoreController::class, 'updatePrintJson']);
            Route::delete('stores/{store}/removeImage', [StoreController::class, 'removeImage']);
            Route::post('stores/{store}/addImageToStore', [StoreController::class, 'addImageToStore']);
            Route::get('stores/{store}/printLogo', [StoreController::class, 'printLogo']);

            Route::apiResource('clients', ClientController::class);
            Route::apiResource('suppliers', SupplierController::class);
            Route::get('suppliers/{supplier}/contacts', [SupplierController::class, 'contactIndex']);
            Route::post('suppliers/{supplier}/contacts', [SupplierController::class, 'contactStore']);
            Route::put('suppliers/{supplier}/contacts/{contact}', [SupplierController::class, 'contactUpdate']);
            Route::delete('suppliers/{supplier}/contacts/{contact}', [SupplierController::class, 'contactDestroy']);

            Route::apiResource('settings', SettingController::class)->only(['index', 'store', 'update', 'destroy']);
            
            // Notification Settings
            Route::get('notifications/settings', [\App\Http\Controllers\Api\V1\TenantNotificationSettingsController::class, 'index']);
            Route::put('notifications/settings/{key}', [\App\Http\Controllers\Api\V1\TenantNotificationSettingsController::class, 'update']);
        });


        // Module: products
        Route::middleware('module:products')->group(function () {
            Route::apiResource('tags', TagController::class)->except(['show']);
            Route::apiResource('categories', CategoryController::class)->except(['show']);
            Route::apiResource('products', ProductController::class);
            Route::delete('products/{product}/removeImage', [ProductController::class, 'removeImage']);
            Route::post('products/{product}/addImageToProduct', [ProductController::class, 'addImageToProduct']);
        });

        // Module: inventories
        Route::middleware('module:inventories')->group(function () {
            Route::apiResource('inventories', InventoryController::class);
            Route::get('inventories/{inventory}/products', [InventoryController::class, 'showProducts']);
            Route::post('inventories/{inventory}/addProducts', [InventoryController::class, 'addProducts']);
            Route::post('inventories/{inventory}/removeProducts', [InventoryController::class, 'removeProducts']);
            Route::get('inventories/stores/{store}', [InventoryController::class, 'getProductByStore']);

            // Movimientos de inventario
            Route::get('inventories/{inventory}/movements', [MovementController::class, 'index']);
            Route::post('inventories/movements', [MovementController::class, 'store']);
            Route::post('inventories/transfer', [MovementController::class, 'transfer']);
            Route::get('inventories/movements/{movement}', [MovementController::class, 'show']);
            Route::delete('inventories/movements/{movement}', [MovementController::class, 'destroy']);
        });

        // Cash Session Control
        Route::get('cash-sessions', [CashSessionController::class, 'index']);
        Route::get('cash-sessions/active', [CashSessionController::class, 'active']);
        Route::get('cash-sessions/{id}', [CashSessionController::class, 'show']);
        Route::post('cash-sessions/open', [CashSessionController::class, 'open']);
        Route::post('cash-sessions/close', [CashSessionController::class, 'close']);
        Route::post('cash-sessions/transactions', [CashSessionController::class, 'addTransaction']);
        Route::put('cash-sessions/{id}', [CashSessionController::class, 'update']);
        Route::put('cash-sessions/transactions/{id}', [CashSessionController::class, 'updateTransaction']);
        Route::delete('cash-sessions/transactions/{id}', [CashSessionController::class, 'destroyTransaction']);

        // Expense Categories
        Route::apiResource('expense-categories', \App\Http\Controllers\Api\V1\ExpenseCategoryController::class)->except(['show']);

        // Module: invoices
        Route::middleware('module:invoices')->group(function () {
            Route::apiResource('invoices', InvoiceController::class)->except(['destroy', 'update']);
            Route::delete('invoices/{invoice}', [InvoiceController::class, 'cancel']);
            Route::post('invoices/{invoice}/replace', [InvoiceController::class, 'replace']);
        });

        // Module: credits
        Route::middleware('module:credits')->group(function () {
            Route::get('credits/search-active', [CreditController::class, 'searchActive']);
            Route::post('credits/payment', [CreditController::class, 'payment']);
            Route::apiResource('credits', CreditController::class)->except(['store', 'update', 'destroy']);
            Route::get('credits-by-client', [CreditController::class, 'indexByClient']);
            Route::get('credits-by-client/{client_id}', [CreditController::class, 'indexByClientID']);
        });

        // Module: purchases
        Route::middleware('module:purchases')->group(function () {
            Route::apiResource('purchases', PurchasesController::class);
            Route::post('purchases/upload', [PurchasesController::class, 'upload']);
        });

        // Module: sellers
        Route::middleware('module:sellers')->group(function () {
            Route::apiResource('sellers', SellerController::class);
            Route::post('/sellers/{seller}/assign-stores', [SellerController::class, 'assignStores']);
            Route::delete('/sellers/{seller}/remove-stores', [SellerController::class, 'removeStores']);
            Route::post('/sellers/seller-login', [SellerController::class, 'sellerLogin']);
            Route::post('/sellers/generate-owner', [SellerController::class, 'generateOwnerSeller']);
        });

        // Module: reports
        Route::middleware('module:reports')->group(function () {
            Route::get('reports/types', [ReportController::class, 'types']);
            Route::apiResource('reports', ReportController::class)->only(['index', 'store', 'destroy']);
            Route::get('reports/{report}/download', [ReportController::class, 'download']);
        });

        // WooCommerce Integration Settings
        Route::get('woocommerce/integration', [WooCommerceIntegrationController::class, 'index']);
        Route::post('woocommerce/integration', [WooCommerceIntegrationController::class, 'store']);
        Route::post('woocommerce/integration/test', [WooCommerceIntegrationController::class, 'testConnection']);

        // Landing Page Admin Routes
        Route::post('/landing/admin/media', [LandingAdminController::class, 'uploadMedia']);
        Route::get('/landing/admin/media', [LandingAdminController::class, 'getMedia']);
        Route::delete('/landing/admin/media/{id}', [LandingAdminController::class, 'deleteMedia']);
        Route::put('/landing/admin/content/{key}', [LandingAdminController::class, 'saveSectionContent']);
        Route::post('/landing/admin/plans', [LandingAdminController::class, 'managePlan']);
        Route::put('/landing/admin/plans/{id}', [LandingAdminController::class, 'managePlan']);
        Route::delete('/landing/admin/plans/{id}', [LandingAdminController::class, 'deletePlan']);

        // Notifications Module
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
    });
});
