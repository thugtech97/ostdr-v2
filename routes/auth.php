<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

// URLs follow the legacy app so existing bookmarks keep working.
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('auth/login', [AuthenticatedSessionController::class, 'store'])
        ->name('auth.login');

    Route::get('auth/adminlogin', [AuthenticatedSessionController::class, 'createAdmin'])
        ->name('auth.adminlogin');

    Route::post('auth/adminlogin', [AuthenticatedSessionController::class, 'storeAdmin'])
        ->name('auth.adminlogin.store');

    Route::get('auth/forgot_password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');

    Route::post('auth/forgot_password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
});

Route::middleware('auth')->group(function () {
    Route::get('auth/change_password', [PasswordController::class, 'edit'])
        ->name('password.edit');

    Route::put('auth/change_password', [PasswordController::class, 'update'])
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
