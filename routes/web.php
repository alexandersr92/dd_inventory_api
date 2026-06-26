<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;

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
        Route::post('/admins', [AdminDashboardController::class, 'storeAdmin'])->name('admin.admins.store');

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
    });
});
