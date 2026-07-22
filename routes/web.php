<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminErrorReportController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->group(function () {
    // Public login/logout routes
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // Protected dashboard & client actions routes
    Route::middleware(['auth:admin'])->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/clients/{id}', [AdminDashboardController::class, 'showClient'])->name('admin.clients.show');
        Route::post('/clients', [AdminDashboardController::class, 'storeClient'])->name('admin.clients.store');
        Route::post('/clients/{id}/toggle-status', [AdminDashboardController::class, 'toggleClientStatus'])->name('admin.clients.toggle-status');
        Route::post('/clients/{id}/toggle-module/{moduleId}', [AdminDashboardController::class, 'toggleClientModule'])->name('admin.clients.toggle-module');
        Route::post('/clients/{id}/license', [AdminDashboardController::class, 'updateLicense'])->name('admin.clients.license');
        Route::post('/clients/{id}/plan', [AdminDashboardController::class, 'assignPlan'])->name('admin.clients.plan');
        Route::post('/clients/{id}/destroy', [AdminDashboardController::class, 'destroyClient'])->name('admin.clients.destroy');
        Route::post('/admins', [AdminDashboardController::class, 'storeAdmin'])->name('admin.admins.store');
        
        // CRUD de planes funcionales (límites asignables a organizaciones)
        Route::get('/plans', [AdminPlanController::class, 'index'])->name('admin.plans.index');
        Route::post('/plans', [AdminPlanController::class, 'store'])->name('admin.plans.store');
        Route::post('/plans/{id}/update', [AdminPlanController::class, 'update'])->name('admin.plans.update');
        Route::post('/plans/{id}/delete', [AdminPlanController::class, 'destroy'])->name('admin.plans.delete');

        // Pagos: métodos de pago + validación de comprobantes
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('admin.payments.index');
        Route::post('/payments/providers', [AdminPaymentController::class, 'storeProvider'])->name('admin.payments.providers.store');
        Route::post('/payments/providers/{id}/update', [AdminPaymentController::class, 'updateProvider'])->name('admin.payments.providers.update');
        Route::post('/payments/providers/{id}/toggle', [AdminPaymentController::class, 'toggleProvider'])->name('admin.payments.providers.toggle');
        Route::post('/payments/providers/{id}/delete', [AdminPaymentController::class, 'destroyProvider'])->name('admin.payments.providers.delete');
        Route::get('/payments/{id}/receipt', [AdminPaymentController::class, 'viewReceipt'])->name('admin.payments.receipt');
        Route::get('/payments/{id}/invoice', [AdminPaymentController::class, 'downloadInvoice'])->name('admin.payments.invoice');
        Route::post('/payments/{id}/approve', [AdminPaymentController::class, 'approveSubmission'])->name('admin.payments.approve');
        Route::post('/payments/{id}/reject', [AdminPaymentController::class, 'rejectSubmission'])->name('admin.payments.reject');

        // Configuración de notificaciones
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
        Route::post('/notifications', [AdminNotificationController::class, 'update'])->name('admin.notifications.update');
        Route::post('/notifications/test', [AdminNotificationController::class, 'test'])->name('admin.notifications.test');

        // Reportes de error (gestión de los reportes enviados por los clientes)
        Route::get('/error-reports', [AdminErrorReportController::class, 'index'])->name('admin.error-reports.index');
        Route::get('/error-reports/{id}/screenshot', [AdminErrorReportController::class, 'screenshot'])->name('admin.error-reports.screenshot');
        Route::post('/error-reports/{id}/resolve', [AdminErrorReportController::class, 'resolve'])->name('admin.error-reports.resolve');
        Route::post('/error-reports/{id}/delete', [AdminErrorReportController::class, 'destroy'])->name('admin.error-reports.destroy');

        // Historial de auditoría
        Route::get('/audit', [AdminDashboardController::class, 'auditLog'])->name('admin.audit.index');

        Route::get('/settings', [AdminDashboardController::class, 'globalSettings'])->name('admin.settings.index');
        Route::post('/settings', [AdminDashboardController::class, 'updateGlobalSettings'])->name('admin.settings.update');

        // Backup management routes
        Route::post('/backups/generate', [AdminDashboardController::class, 'generateBackup'])->name('admin.backups.generate');
        Route::get('/backups/{filename}/download', [AdminDashboardController::class, 'downloadBackup'])->name('admin.backups.download');
        Route::post('/backups/{filename}/delete', [AdminDashboardController::class, 'deleteBackup'])->name('admin.backups.delete');

        // Email management routes
        Route::get('/email-settings', [\App\Http\Controllers\Admin\AdminEmailController::class, 'index'])->name('admin.emails.index');
        Route::post('/email-settings/smtp', [\App\Http\Controllers\Admin\AdminEmailController::class, 'updateSmtp'])->name('admin.emails.smtp.update');
        Route::post('/email-settings/test', [\App\Http\Controllers\Admin\AdminEmailController::class, 'sendTestEmail'])->name('admin.emails.test');
        Route::get('/email-settings/templates/{id}', [\App\Http\Controllers\Admin\AdminEmailController::class, 'getTemplate'])->name('admin.emails.template.get');
        Route::post('/email-settings/templates/{id}', [\App\Http\Controllers\Admin\AdminEmailController::class, 'updateTemplate'])->name('admin.emails.template.update');

        // Landing Page management routes
        Route::get('/landing', [\App\Http\Controllers\Admin\AdminLandingController::class, 'index'])->name('admin.landing.index');
        Route::post('/landing/media', [\App\Http\Controllers\Admin\AdminLandingController::class, 'uploadMedia'])->name('admin.landing.media.upload');
        Route::post('/landing/media/{id}/delete', [\App\Http\Controllers\Admin\AdminLandingController::class, 'deleteMedia'])->name('admin.landing.media.delete');
        Route::post('/landing/content/{key}', [\App\Http\Controllers\Admin\AdminLandingController::class, 'updateContent'])->name('admin.landing.content.update');
        Route::post('/landing/plans', [\App\Http\Controllers\Admin\AdminLandingController::class, 'storePlan'])->name('admin.landing.plans.store');
        Route::post('/landing/plans/{id}/update', [\App\Http\Controllers\Admin\AdminLandingController::class, 'storePlan'])->name('admin.landing.plans.update');
        Route::post('/landing/plans/{id}/delete', [\App\Http\Controllers\Admin\AdminLandingController::class, 'deletePlan'])->name('admin.landing.plans.delete');
    });
});
