<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\UserPortalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/permohonan');

Route::get('/permohonan', [UserPortalController::class, 'formPermohonan'])->name('user.request.form');
Route::post('/permohonan', [UserPortalController::class, 'simpanPermohonan'])->name('user.request.submit');

Route::get('/cek-token', [UserPortalController::class, 'formCekToken'])->name('user.status.form');
Route::post('/cek-token', [UserPortalController::class, 'cekToken'])->name('user.status.check');
Route::get('/unduh/{token}', [UserPortalController::class, 'unduh'])->name('user.download');

Route::get('/admin/login', [AdminSessionController::class, 'formLogin'])->name('admin.login.form');
Route::post('/admin/login', [AdminSessionController::class, 'login'])->name('admin.login.submit');

Route::middleware('admin.web')->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminPanelController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/requests/{tteRequest}', [AdminPanelController::class, 'detail'])->name('admin.detail');
    Route::post('/requests/{tteRequest}/setuju', [AdminPanelController::class, 'setuju'])->name('admin.request.setuju');
    Route::post('/requests/{tteRequest}/tolak', [AdminPanelController::class, 'tolak'])->name('admin.request.tolak');
    Route::post('/requests/{tteRequest}/unggah-tte', [AdminPanelController::class, 'unggahTte'])->name('admin.request.unggah_tte');
    Route::post('/logout', [AdminSessionController::class, 'logout'])->name('admin.logout');
});
