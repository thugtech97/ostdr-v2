<?php

use App\Http\Controllers\Admin\AccessRightController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SatelliteController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\McdRecordController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\ReportController;
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

    // Receiving (legacy MCD Receiving). Approval itself happens in WFS.
    Route::prefix('mcds')->name('mcds.')->group(function () {
        $manage = ReceivingController::PAGE_MANAGE;
        $completed = ReceivingController::PAGE_COMPLETED;

        Route::get('dashboard', [ReceivingController::class, 'index'])->middleware("permission:{$manage},view")->name('index');
        Route::get('completed-dashboard', [ReceivingController::class, 'completed'])->middleware("permission:{$completed},view")->name('completed');
        Route::get('export', [ReceivingController::class, 'export'])->name('export');
        Route::get('view/{stockRequest}', [ReceivingController::class, 'show'])->name('show');
        Route::patch('{stockRequest}/receive', [ReceivingController::class, 'receive'])->middleware("permission:{$manage},edit")->name('receive');
        Route::get('edit/{stockRequest}', [ReceivingController::class, 'edit'])->middleware("permission:{$manage},edit")->name('edit');
        Route::post('{stockRequest}/issue', [ReceivingController::class, 'issue'])->middleware("permission:{$manage},edit")->name('issue');
        Route::patch('items/{item}/close', [ReceivingController::class, 'close'])->middleware("permission:{$manage},edit")->name('items.close');
        Route::delete('items/{item}', [ReceivingController::class, 'exclude'])->middleware("permission:{$manage},edit")->name('items.exclude');
        Route::get('printStockRequest/{stockRequest}', [ReceivingController::class, 'print'])->name('print');
    });

    // MCD Records.
    Route::prefix('mcdrecords')->name('mcdrecords.')->group(function () {
        $completed = McdRecordController::PAGE_COMPLETED;
        $batches = McdRecordController::PAGE_BATCHES;
        $items = McdRecordController::PAGE_ITEMS;

        Route::get('dashboard', [McdRecordController::class, 'index'])->middleware("permission:{$completed},view")->name('index');
        Route::get('export', [McdRecordController::class, 'export'])->middleware("permission:{$completed},view")->name('export');
        Route::get('request/{stockRequest}', [McdRecordController::class, 'showRequest'])->middleware("permission:{$completed},view")->name('request');
        Route::get('request/{stockRequest}/print', [McdRecordController::class, 'printRequest'])->middleware("permission:{$completed},view")->name('print');

        Route::get('batches', [McdRecordController::class, 'batches'])->middleware("permission:{$batches},view")->name('batches');
        Route::get('batches/export', [McdRecordController::class, 'exportBatches'])->name('batches.export');
        Route::get('create', [McdRecordController::class, 'create'])->middleware("permission:{$batches},create")->name('create');
        Route::post('/', [McdRecordController::class, 'store'])->middleware("permission:{$batches},create")->name('store');
        Route::get('view/{batch}', [McdRecordController::class, 'show'])->middleware("permission:{$batches},view")->name('show');
        Route::get('edit/{batch}', [McdRecordController::class, 'edit'])->middleware("permission:{$batches},edit")->name('edit');
        Route::put('{batch}', [McdRecordController::class, 'update'])->middleware("permission:{$batches},edit")->name('update');
        Route::post('{batch}/include', [McdRecordController::class, 'include'])->middleware("permission:{$batches},edit")->name('include');
        Route::patch('{batch}/items/{item}/exclude', [McdRecordController::class, 'exclude'])->middleware("permission:{$batches},edit")->name('exclude');
        Route::delete('{batch}', [McdRecordController::class, 'destroy'])->middleware("permission:{$batches},delete")->name('destroy');

        Route::get('unbatched', [McdRecordController::class, 'unbatched'])->middleware("permission:{$batches},view")->name('unbatched');
        Route::get('unserved', [McdRecordController::class, 'unserved'])->middleware("permission:{$batches},view")->name('unserved');
        Route::get('items-export/{report}', [McdRecordController::class, 'exportItems'])->whereIn('report', ['unbatched', 'unserved'])->name('items.export');

        Route::get('update-items', [McdRecordController::class, 'catalogue'])->middleware("permission:{$items},view")->name('update-items');
        Route::get('update-items/export', [McdRecordController::class, 'exportCatalogue'])->name('update-items.export');
        Route::post('uploadStocks', [McdRecordController::class, 'uploadStocks'])->middleware("permission:{$items},create")->name('upload-stocks');
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

    // Maintenance: satellites.
    Route::prefix('satellites')->name('satellites.')->group(function () {
        $page = SatelliteController::PAGE;

        Route::get('dashboard', [SatelliteController::class, 'index'])->middleware("permission:{$page},view")->name('index');
        Route::get('create', [SatelliteController::class, 'create'])->middleware("permission:{$page},create")->name('create');
        Route::post('/', [SatelliteController::class, 'store'])->middleware("permission:{$page},create")->name('store');
        Route::get('edit/{satellite}', [SatelliteController::class, 'edit'])->middleware("permission:{$page},edit")->name('edit');
        Route::put('{satellite}', [SatelliteController::class, 'update'])->middleware("permission:{$page},edit")->name('update');
    });

    // Maintenance: permission pages.
    Route::prefix('permissions')->name('permissions.')->group(function () {
        $page = PermissionController::PAGE;

        Route::get('dashboard', [PermissionController::class, 'index'])->middleware("permission:{$page},view")->name('index');
        Route::get('create', [PermissionController::class, 'create'])->middleware("permission:{$page},create")->name('create');
        Route::post('/', [PermissionController::class, 'store'])->middleware("permission:{$page},create")->name('store');
        Route::get('edit/{permission}', [PermissionController::class, 'edit'])->middleware("permission:{$page},edit")->name('edit');
        Route::put('{permission}', [PermissionController::class, 'update'])->middleware("permission:{$page},edit")->name('update');
    });

    // Maintenance: access rights grids.
    Route::prefix('accessrights')->name('accessrights.')->group(function () {
        $user = AccessRightController::PAGE_USER;
        $role = AccessRightController::PAGE_ROLE;

        Route::get('user', [AccessRightController::class, 'user'])->middleware("permission:{$user},view")->name('user');
        Route::put('user/{user}', [AccessRightController::class, 'updateUser'])->middleware("permission:{$user},edit")->name('user.update');
        Route::get('role', [AccessRightController::class, 'role'])->middleware("permission:{$role},view")->name('role');
        Route::put('role/{role}', [AccessRightController::class, 'updateRole'])->middleware("permission:{$role},edit")->name('role.update');
    });

    // Maintenance: scheduled shutdowns, start / stop, re-index.
    Route::prefix('applications')->name('applications.')->group(function () {
        $page = ApplicationController::PAGE;

        Route::get('dashboard', [ApplicationController::class, 'index'])->middleware("permission:{$page},view")->name('index');
        Route::get('create', [ApplicationController::class, 'create'])->middleware("permission:{$page},create")->name('create');
        Route::post('/', [ApplicationController::class, 'store'])->middleware("permission:{$page},create")->name('store');
        Route::get('edit/{application}', [ApplicationController::class, 'edit'])->middleware("permission:{$page},edit")->name('edit');
        Route::put('{application}', [ApplicationController::class, 'update'])->middleware("permission:{$page},edit")->name('update');
        Route::delete('{application}', [ApplicationController::class, 'destroy'])->middleware("permission:{$page},delete")->name('destroy');
        Route::post('reindex', [ApplicationController::class, 'reindex'])->middleware("permission:{$page},edit")->name('reindex');
        Route::post('systemDown', [ApplicationController::class, 'systemDown'])->middleware("permission:{$page},edit")->name('down');
        Route::post('systemUp', [ApplicationController::class, 'systemUp'])->middleware("permission:{$page},edit")->name('up');
    });

    // Reports. The two stock request reports are open to every user, like legacy.
    Route::prefix('reports')->name('reports.')->group(function () {
        $audit = ReportController::PAGE_AUDIT;
        $errors = ReportController::PAGE_ERRORS;

        Route::get('stockRequests', [ReportController::class, 'stockRequests'])->name('stock-requests');
        Route::get('stockRequests/export', [ReportController::class, 'exportStockRequests'])->name('stock-requests.export');
        Route::get('stockRequests/view/{stockRequest}', [ReportController::class, 'showStockRequest'])->name('stock-requests.show');
        Route::get('stockRequests/print/{stockRequest}', [ReportController::class, 'printStockRequest'])->name('stock-requests.print');
        Route::get('unserved', [ReportController::class, 'unserved'])->name('unserved');
        Route::get('unserved/export', [ReportController::class, 'exportUnserved'])->name('unserved.export');
        Route::get('audit-logs', [ReportController::class, 'auditLogs'])->middleware("permission:{$audit},view")->name('audit-logs');
        Route::get('audit-logs/export', [ReportController::class, 'exportAuditLogs'])->middleware("permission:{$audit},view")->name('audit-logs.export');
        Route::get('error-logs', [ReportController::class, 'errorLogs'])->middleware("permission:{$errors},view")->name('error-logs');
        Route::get('error-logs/export', [ReportController::class, 'exportErrorLogs'])->middleware("permission:{$errors},view")->name('error-logs.export');
    });

    Route::get('users/user-manual', [ReportController::class, 'manual'])->name('user.manual');
});
