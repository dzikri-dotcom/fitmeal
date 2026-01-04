<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FitmealController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    return view('welcome');
});

// --- Jalur Publik ---
Route::get('auth/google', [FitmealController::class, 'redirectToGoogle'])->name('google.login');
Route::get('auth/google/callback', [FitmealController::class, 'handleGoogleCallback']);
Route::post('midtrans-webhook', [FitmealController::class, 'webhook']);

// --- Jalur Khusus User Login ---
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [FitmealController::class, 'dashboard'])->name('dashboard');
    Route::post('/bmi', [FitmealController::class, 'bmi'])->name('bmi');
    Route::post('/pay', [FitmealController::class, 'subscribe'])->name('pay');

    // Profile standar Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Profile custom Fitmeal
    Route::post('/profile/custom-update', [FitmealController::class, 'updateProfile'])->name('profile.custom_update');
});

// --- Jalur Khusus Admin (Dipisah agar tidak bentrok) ---
Route::middleware(['auth', 'can:admin'])->prefix('admin')->group(function () {
    Route::get('/', [FitmealController::class, 'admin'])->name('admin.index');
    Route::post('/plan', [FitmealController::class, 'storePlan'])->name('admin.plan');
    Route::delete('/plan/delete/{id}', [FitmealController::class, 'deletePlan'])->name('admin.plan.delete');
    Route::post('/user/update/{id}', [FitmealController::class, 'updateUser'])->name('admin.user.update');
});

require __DIR__.'/auth.php';
