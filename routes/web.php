<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\StockRequestController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    // Main dashboard. Legacy lets every signed-in user see it.
    Route::get('stockrequests/main-dashboard', [StockRequestController::class, 'dashboard'])->name('dashboard');
    Route::redirect('dashboard', 'stockrequests/main-dashboard');

    // Stock requests (requestor side). URLs follow the legacy app.
    Route::prefix('stockrequests')->name('stockrequests.')->group(function () {
        $manage = StockRequestController::PAGE_MANAGE;
        $request = StockRequestController::PAGE_REQUEST;
        $unsaved = StockRequestController::PAGE_UNSAVED;

        Route::get('dashboard', [StockRequestController::class, 'index'])->middleware("permission:{$manage},view")->name('index');
        Route::get('export', [StockRequestController::class, 'export'])->name('export');
        Route::get('unsaved-dashboard', [StockRequestController::class, 'unsaved'])->middleware("permission:{$unsaved},view")->name('unsaved');
        Route::get('create', [StockRequestController::class, 'create'])->middleware("permission:{$request},create")->name('create');
        Route::post('/', [StockRequestController::class, 'store'])->middleware("permission:{$request},create")->name('store');
        Route::get('view/{stockRequest}', [StockRequestController::class, 'show'])->middleware("permission:{$request},view")->name('show');
        Route::get('edit/{stockRequest}', [StockRequestController::class, 'edit'])->middleware("permission:{$request},edit")->name('edit');
        Route::put('{stockRequest}', [StockRequestController::class, 'update'])->middleware("permission:{$request},edit")->name('update');
        Route::patch('{stockRequest}/submit', [StockRequestController::class, 'submit'])->middleware("permission:{$request},edit")->name('submit');
        Route::delete('{stockRequest}', [StockRequestController::class, 'destroy'])->name('destroy');
        Route::get('print/{stockRequest}', [StockRequestController::class, 'print'])->middleware("permission:{$request},view")->name('print');
    });

    Route::get('products/search', [StockRequestController::class, 'products'])->name('products.search');

    // Maintenance: user management.
    Route::prefix('users')->name('users.')->group(function () {
        $page = UserController::PAGE;

        Route::get('dashboard', [UserController::class, 'index'])->middleware("permission:{$page},view")->name('index');
        Route::get('create', [UserController::class, 'create'])->middleware("permission:{$page},create")->name('create');
        Route::get('employees', [UserController::class, 'employees'])->middleware("permission:{$page},create")->name('employees');
        Route::post('/', [UserController::class, 'store'])->middleware("permission:{$page},create")->name('store');
        Route::get('edit/{user}', [UserController::class, 'edit'])->middleware("permission:{$page},edit")->name('edit');
        Route::put('{user}', [UserController::class, 'update'])->middleware("permission:{$page},edit")->name('update');
        Route::patch('{user}/activate', [UserController::class, 'activate'])->middleware("permission:{$page},edit")->name('activate');
        Route::patch('{user}/deactivate', [UserController::class, 'deactivate'])->middleware("permission:{$page},edit")->name('deactivate');
    });

    // Maintenance: roles.
    Route::prefix('roles')->name('roles.')->group(function () {
        $page = RoleController::PAGE;

        Route::get('dashboard', [RoleController::class, 'index'])->middleware("permission:{$page},view")->name('index');
        Route::get('create', [RoleController::class, 'create'])->middleware("permission:{$page},create")->name('create');
        Route::post('/', [RoleController::class, 'store'])->middleware("permission:{$page},create")->name('store');
        Route::get('edit/{role}', [RoleController::class, 'edit'])->middleware("permission:{$page},edit")->name('edit');
        Route::put('{role}', [RoleController::class, 'update'])->middleware("permission:{$page},edit")->name('update');
    });
});
