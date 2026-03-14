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
    Route::post('/requests/generate-txt', [AdminPanelController::class, 'generateTxt'])->name('admin.request.generate_txt');
    Route::post('/bulk-download', [AdminPanelController::class, 'bulkDownloadStore'])->name('admin.bulk_download.store');
    Route::get('/bulk-download', [AdminPanelController::class, 'bulkDownloadForm'])->name('admin.bulk_download.form');
    Route::post('/bulk-download/clear', [AdminPanelController::class, 'bulkDownloadClear'])->name('admin.bulk_download.clear');
    Route::post('/bulk/clear-all', [AdminPanelController::class, 'bulkClearAll'])->name('admin.bulk.clear_all');
    Route::get('/bulk-download/{bulkDownload}/status', [AdminPanelController::class, 'bulkDownloadStatus'])->name('admin.bulk_download.status');
    Route::get('/bulk-download/{bulkDownload}/file', [AdminPanelController::class, 'bulkDownloadFile'])->name('admin.bulk_download.file');
    Route::get('/bulk-upload', [AdminPanelController::class, 'bulkUploadForm'])->name('admin.bulk_upload.form');
    Route::post('/bulk-upload', [AdminPanelController::class, 'bulkUploadStore'])->name('admin.bulk_upload.store');
    Route::post('/bulk-upload/clear', [AdminPanelController::class, 'bulkUploadClear'])->name('admin.bulk_upload.clear');
    Route::get('/bulk-upload/{bulkUpload}/status', [AdminPanelController::class, 'bulkUploadStatus'])->name('admin.bulk_upload.status');
    Route::get('/requests/{tteRequest}', [AdminPanelController::class, 'detail'])->name('admin.detail');
    Route::post('/requests/{tteRequest}/setuju', [AdminPanelController::class, 'setuju'])->name('admin.request.setuju');
    Route::post('/requests/{tteRequest}/tolak', [AdminPanelController::class, 'tolak'])->name('admin.request.tolak');
    Route::post('/requests/{tteRequest}/unggah-tte', [AdminPanelController::class, 'unggahTte'])->name('admin.request.unggah_tte');
    Route::post('/requests/{tteRequest}/generate-token', [AdminPanelController::class, 'generateUlangToken'])->name('admin.request.generate_token');
    Route::post('/requests/{tteRequest}/hapus', [AdminPanelController::class, 'hapus'])->name('admin.request.hapus');
    Route::post('/logout', [AdminSessionController::class, 'logout'])->name('admin.logout');
});
