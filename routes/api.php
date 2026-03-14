<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\TteRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/request', [TteRequestController::class, 'simpan']);
Route::post('/status', [TteRequestController::class, 'cek']);
Route::get('/download/{token}', [TteRequestController::class, 'unduh']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::middleware('admin.api')->prefix('admin')->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::patch('/requests/{tteRequest}/setuju', [TteRequestController::class, 'setuju']);
    Route::patch('/requests/{tteRequest}/tolak', [TteRequestController::class, 'tolak']);
    Route::patch('/requests/{tteRequest}/unggah-tte', [TteRequestController::class, 'unggahTte']);
});
