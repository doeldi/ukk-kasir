<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('IsGuest')->group(function () {
    Route::get('/login', function () {
        return view('login');
    })->name('login');

    Route::post('/login', [UserController::class, 'loginAuth'])->name('login.auth');
});

Route::get('/', function () {
    return view('landing');
})->name('landingpage');

Route::get('/logout', [UserController::class, 'logout'])->name('logout');

Route::get('/error-permission', function() {
    return view('error.permission');
})->name('error.permission');

//admin
Route::middleware('IsLogin', 'IsAdmin')->prefix('admin')->name('admin.')->group(function() {
    Route::get('/dashboard', [UserController::class, 'dashboardAdmin'])->name('dashboard');

    //product
    Route::prefix('/product')->group(function() {
        Route::get('/', [ProductController::class, 'index'])->name('ProductHome');
        Route::get('/create', [ProductController::class, 'create'])->name('ProductCreate');
        Route::post('/store', [ProductController::class, 'store'])->name('ProductStore');
        Route::get('/{id}', [ProductController::class, 'edit'])->name('ProductEdit');
        Route::patch('/{id}', [ProductController::class, 'update'])->name('ProductUpdate');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->name('ProductDelete');
        Route::put('/stock/{id}', [ProductController::class, 'updateStock'])->name('ProductStock');
    });

    //user
    Route::prefix('/user')->group(function() {
        Route::get('/', [UserController::class, 'index'])->name('UserHome');
        Route::get('/create', [UserController::class, 'create'])->name('UserCreate');
        Route::post('/store', [UserController::class, 'store'])->name('UserStore');
        Route::get('/{id}', [UserController::class, 'edit'])->name('UserEdit');
        Route::patch('/{id}', [UserController::class, 'update'])->name('UserUpdate');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('UserDelete');
    });

    //purchases
    Route::prefix('/sale')->group(function() {
        Route::get('/', [SaleController::class, 'adminIndex'])->name('SaleHome');
        Route::get('/print/{id}', [SaleController::class, 'exportPDFAd'])->name('exportPDFAd');
        Route::get('/excel', [SaleController::class, 'Excel'])->name('Excel');
    });
});