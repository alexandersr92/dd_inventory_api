<?php

use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\CustomJobCalculationController;
use App\Http\Controllers\Api\V1\CustomJobCancellationController;
use App\Http\Controllers\Api\V1\CustomJobDeliveryController;
use App\Http\Controllers\Api\V1\CustomJobMaterialController;
use App\Http\Controllers\Api\V1\CustomJobOrderController;
use App\Http\Controllers\Api\V1\CustomJobOrderRevisionController;
use App\Http\Controllers\Api\V1\CustomJobPaymentController;
use App\Http\Controllers\Api\V1\CustomJobBillingController;
use App\Http\Controllers\Api\V1\CustomJobProductionController;
use App\Http\Controllers\Api\V1\CustomJobQuoteController;
use App\Http\Controllers\Api\V1\CustomJobResourceController;
use App\Http\Controllers\Api\V1\CustomJobResourcePreviewController;
use App\Http\Controllers\Api\V1\CustomJobsAccessController;
use App\Http\Controllers\Api\V1\CustomJobTemplateController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ErrorReportController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LandingPublicController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\MovementController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchasesController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SellerController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\SubscriptionInvoiceController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TenantNotificationSettingsController;
use App\Http\Controllers\Api\V1\TerminalActivationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WooCommerceIntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // TODO: Crear endpoint dedicado para upload de archivos
    // Route::post('/upload/{type}', [FileUploadController::class, 'upload']);

    Route::get('/test',
        function (Request $request) {
            return response()->json([
                'message' => 'Its working good',
                'url' => $request->url(),
                'env' => config('app.env'),
                'time' => date('Y-m-d H:i:s'),
            ]);
        }
    );

    // Ping liviano sin auth para detección de conectividad del POS offline
    Route::get('/ping', function () {
        return response()->json(['ok' => true, 'ts' => now()->timestamp]);
    });

    // SEGURIDAD: throttle en los endpoints públicos de auth (fuerza bruta / abuso).
    // Con trustProxies pineado, el límite es por IP real del cliente.
    Route::post('/admin/login', [LoginController::class, 'login'])->name('api.admin.login')->middleware('throttle:10,1');
    Route::post('/admin/register', [LoginController::class, 'registerOwner'])->name('api.admin.register')->middleware('throttle:10,1');
    Route::post('/admin/auth/google', [SocialAuthController::class, 'handleGoogle'])->name('api.admin.google')->middleware('throttle:10,1');

    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [LoginController::class, 'registerOwner'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:6,1');
    Route::post('/auth/google', [SocialAuthController::class, 'handleGoogle'])->middleware('throttle:10,1');
    Route::post('/terminals/claim-link', [TerminalActivationController::class, 'claimLink'])->middleware('throttle:10,1');

    // Reenviar el correo de verificación. Autenticado pero SIN tenant.switch:
    // un usuario recién registrado aún no tiene organización.
    Route::post('/email/verification-notification', [LoginController::class, 'resendVerificationEmail'])
        ->middleware(['auth:sanctum', 'throttle:6,1']);

    // Public Landing Page Routes
    Route::get('/landing/content', [LandingPublicController::class, 'getPublicContent']);
    Route::get('/landing/plans', [LandingPublicController::class, 'getPublicPlans']);

    Route::middleware(['auth:sanctum', 'tenant.switch'])->group(function () {
        Route::get('/custom-jobs/access', CustomJobsAccessController::class)
            ->middleware('module:custom_jobs');

        Route::prefix('custom-jobs')->middleware('custom_jobs:custom_job.index')->group(function () {
            Route::get('/orders/{id}/payments', [CustomJobPaymentController::class, 'show']);
            Route::post('/orders/{id}/payments', [CustomJobPaymentController::class, 'record'])->defaults('kind', 'payment')->middleware('custom_jobs:custom_job.payment');
            Route::post('/orders/{id}/payments/refund', [CustomJobPaymentController::class, 'record'])->defaults('kind', 'refund')->middleware('custom_jobs:custom_job.payment,custom_job.return');
            Route::get('/orders/{id}/cancellations', [CustomJobCancellationController::class, 'history'])->middleware('custom_jobs:custom_job.cancel');
            Route::post('/orders/{id}/cancellations/preview', [CustomJobCancellationController::class, 'preview'])->middleware('custom_jobs:custom_job.cancel');
            Route::post('/orders/{id}/cancellations', [CustomJobCancellationController::class, 'apply'])->middleware('custom_jobs:custom_job.cancel');
            Route::post('/orders/{id}/payments/annul', [CustomJobCancellationController::class, 'apply'])->defaults('operation', 'annul_payment')->middleware('custom_jobs:custom_job.annul_payment');
            Route::get('/orders/{id}/billing', [CustomJobBillingController::class, 'show'])->middleware('custom_jobs:custom_job.order,invoices');
            Route::post('/orders/{id}/billing', [CustomJobBillingController::class, 'issue'])->middleware('custom_jobs:custom_job.order,invoices');
            Route::get('/orders/{id}/revisions', [CustomJobOrderRevisionController::class, 'history']);
            Route::post('/orders/{id}/revisions/preview', [CustomJobOrderRevisionController::class, 'preview'])->middleware('custom_jobs:custom_job.order,products,inventories');
            Route::post('/orders/{id}/revisions', [CustomJobOrderRevisionController::class, 'apply'])->middleware('custom_jobs:custom_job.order,products,inventories');
            Route::get('/orders/{id}/deliveries', [CustomJobDeliveryController::class, 'show']);
            Route::post('/orders/{id}/deliveries', [CustomJobDeliveryController::class, 'record'])->defaults('kind', 'delivery')->middleware('custom_jobs:custom_job.delivery');
            Route::post('/orders/{id}/deliveries/return', [CustomJobDeliveryController::class, 'record'])->defaults('kind', 'return')->middleware('custom_jobs:custom_job.return');
            Route::post('/orders/{id}/deliveries/correct', [CustomJobDeliveryController::class, 'record'])->defaults('kind', 'correct_delivery')->middleware('custom_jobs:custom_job.delivery');
            Route::post('/orders/{id}/deliveries/correct-return', [CustomJobDeliveryController::class, 'record'])->defaults('kind', 'correct_return')->middleware('custom_jobs:custom_job.return');
            Route::get('/orders/{id}/production', [CustomJobProductionController::class, 'show']);
            Route::post('/orders/{id}/production', [CustomJobProductionController::class, 'generate'])->middleware('custom_jobs:custom_job.production,products,inventories');
            Route::post('/orders/{id}/production/events', [CustomJobProductionController::class, 'record'])->middleware('custom_jobs:custom_job.production,products,inventories');
            Route::get('/orders/{id}/materials', [CustomJobMaterialController::class, 'show'])->middleware('custom_jobs:custom_job.index,products,inventories');
            Route::post('/orders/{id}/materials/additional', [CustomJobMaterialController::class, 'mutate'])->defaults('kind', 'additional')->middleware('custom_jobs:custom_job.order,products,inventories');
            Route::post('/orders/{id}/materials/return', [CustomJobMaterialController::class, 'mutate'])->defaults('kind', 'return')->middleware('custom_jobs:custom_job.return,products,inventories');
            Route::post('/orders/{id}/materials/recover', [CustomJobMaterialController::class, 'mutate'])->defaults('kind', 'recover')->middleware('custom_jobs:custom_job.return,products,inventories');
            Route::get('/orders', [CustomJobOrderController::class, 'index']);
            Route::get('/orders/{id}', [CustomJobOrderController::class, 'show']);
            Route::middleware('custom_jobs:custom_job.order')->group(function () {
                Route::post('/quotes/{id}/convert', [CustomJobOrderController::class, 'convert']);
                Route::patch('/orders/{id}/commitment', [CustomJobOrderController::class, 'commitment']);
                Route::post('/orders/{id}/confirm', [CustomJobOrderController::class, 'confirm'])->middleware('custom_jobs:custom_job.order,products,inventories');
            });
            Route::get('/quotes', [CustomJobQuoteController::class, 'index']);
            Route::get('/quote-clients', [CustomJobQuoteController::class, 'clients'])->middleware(['module:clients', 'custom_jobs:custom_job.quote']);
            Route::get('/quotes/{id}', [CustomJobQuoteController::class, 'show']);
            Route::middleware('custom_jobs:custom_job.quote,custom_job.costs,products,inventories')->group(function () {
                Route::post('/quotes', [CustomJobQuoteController::class, 'store']);
                Route::put('/quotes/{id}', [CustomJobQuoteController::class, 'update']);
                Route::post('/quotes/{id}/duplicate', [CustomJobQuoteController::class, 'duplicate']);
                Route::post('/quotes/{id}/issue', [CustomJobQuoteController::class, 'issue'])->middleware('module:invoices');
            });
            Route::get('/resources/{kind}', [CustomJobResourceController::class, 'index']);
            Route::middleware('custom_jobs:custom_job.costs')->group(function () {
                Route::post('/resource-preview', CustomJobResourcePreviewController::class);
                Route::middleware('custom_jobs:custom_job.costs,products,inventories')->group(function () {
                    Route::get('/templates', [CustomJobTemplateController::class, 'index']);
                    Route::post('/templates/{id}/instantiate', [CustomJobTemplateController::class, 'instantiate']);
                    Route::middleware('custom_jobs:custom_job.configure,custom_job.costs,products,inventories')->group(function () {
                        Route::post('/templates', [CustomJobTemplateController::class, 'store']);
                        Route::put('/templates/{id}', [CustomJobTemplateController::class, 'update']);
                        Route::post('/templates/{id}/archive', [CustomJobTemplateController::class, 'archive']);
                    });
                    Route::get('/calculation-materials', [CustomJobCalculationController::class, 'materials']);
                    Route::post('/calculation-preview', [CustomJobCalculationController::class, 'preview']);
                });
                Route::middleware('custom_jobs:custom_job.configure,custom_job.costs')->group(function () {
                    Route::post('/resources/{kind}', [CustomJobResourceController::class, 'store']);
                    Route::put('/resources/{kind}/{id}', [CustomJobResourceController::class, 'update']);
                    Route::post('/resources/{kind}/{id}/archive', [CustomJobResourceController::class, 'archive']);
                });
            });
        });

        // Excel Reports
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
            Route::get('notifications/settings', [TenantNotificationSettingsController::class, 'index']);
            Route::put('notifications/settings/{key}', [TenantNotificationSettingsController::class, 'update']);
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
        Route::apiResource('expense-categories', ExpenseCategoryController::class)->except(['show']);

        // Planes y uso (sin módulo: disponible para toda organización autenticada)
        Route::get('plans', [PlanController::class, 'index']);
        Route::get('plans/current', [PlanController::class, 'current']);

        // Pagos: métodos activos + comprobantes de renovación
        Route::get('payments/providers', [PaymentController::class, 'providers']);
        // Throttle contra spam de archivos de 5MB y de correos a root (6/min).
        Route::post('payments/submit', [PaymentController::class, 'submit'])
            ->middleware('throttle:6,1');
        Route::get('payments/my-submissions', [PaymentController::class, 'mySubmissions']);
        Route::get('payments/{id}/receipt', [PaymentController::class, 'downloadReceipt']);

        // Facturas de suscripción emitidas por DipleBill al cliente (descargables).
        Route::get('subscription-invoices', [SubscriptionInvoiceController::class, 'index']);
        Route::get('subscription-invoices/{id}/download', [SubscriptionInvoiceController::class, 'download']);

        // Reporte de errores desde la app del negocio (llega al panel root).
        Route::post('error-reports', [ErrorReportController::class, 'store']);

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
            Route::post('credits/payment/{id}/void', [CreditController::class, 'voidPayment']);
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
            Route::post('/terminals/magic-links', [TerminalActivationController::class, 'generateLink']);
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

        // Landing Page Admin: ELIMINADAS. Estas rutas quedaban bajo auth:sanctum
        // (cualquier dueño de tenant autenticado) y sin ninguna autorización, así
        // que un cliente cualquiera podía editar la landing GLOBAL. La edición de
        // la landing vive en el panel root (guard 'admin', AdminLandingController,
        // ver routes/web.php). Ningún frontend consumía estos endpoints.

        // Notifications Module
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
    });
});
