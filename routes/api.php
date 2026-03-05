<?php

use App\Http\Controllers\AcaraController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/acara/{uid}/reprint', [AcaraController::class, 'reprintLastSession']);
Route::get('/acara/{uid}/reprint/session', [AcaraController::class, 'reprintLastSessionByUidSession']);
Route::get('/acara/{uid}/download-photos', [AcaraController::class, 'downloadPhotosByAcara']);


// Reset Password
Route::post('/otp/send', [OtpController::class, 'sendOtp']);
Route::post('/otp/verify', [OtpController::class, 'verifyOtp']);
Route::post('/otp/reset-password', [OtpController::class, 'resetPassword']);

Route::prefix('client')->group(function () {
    // Public access untuk client photobooth
    Route::get('/acara/active', [AcaraController::class, 'getActive']);
    Route::get('/acara/{uid}', [AcaraController::class, 'show']);
    Route::get('/frame/by-acara/{acara_uid}', [FrameController::class, 'getByAcara']);
    Route::post('/photo/send', [PhotoController::class, 'sendPhoto']);

    // Acara
    Route::post('acara/{uid}/check', [AcaraController::class, 'checkStatusAcara']);

    // Session
    Route::post('/session/create', [SessionController::class, 'create']);
    Route::get('/session/{uid}', [SessionController::class, 'show']);
    Route::put('/session/{uid}/email', [SessionController::class, 'updateEmail']);
    Route::get('/session/{uid}/check', [SessionController::class, 'checkActive']);

    //Photo
    Route::post('/photo/upload-original', [PhotoController::class, 'uploadOriginal']);
    Route::post('/photo/upload-framed', [PhotoController::class, 'uploadFramed']);
    Route::get('/photo/session/{session_uid}', [PhotoController::class, 'getBySession']);
    Route::get('/photo/session/{session_uid}/type/{type}', [PhotoController::class, 'getByType']);
    Route::post('/photo/{uid}/retake', [PhotoController::class, 'retake']);
    Route::delete('/photo/{uid}', [PhotoController::class, 'delete']);
    Route::get('/photo/{uid}/download', [PhotoController::class, 'download']);
    Route::get('/photo/session/{session_uid}/download-all', [PhotoController::class, 'downloadAll']);
    Route::post('/photo/upload-with-email', [PhotoController::class, 'uploadWithEmail']);
});

// Route khusus untuk sudah terautentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/acara/create', [AcaraController::class, 'create']);
    Route::delete('/acara/delete/{uid}', [AcaraController::class, 'delete']);
    Route::post('/acara/update/{uid}', [AcaraController::class, 'update']);
    Route::get('/acara/index', [AcaraController::class, 'index']);
    Route::get('/acara/{uid}', [AcaraController::class, 'show']);
    Route::post('/acara/photos', [AcaraController::class, 'getSessionPhoto']);
    Route::post('/acara/reset/{uid}', [AcaraController::class, 'resetSession']);
    Route::post('/acara/status/update/{uid}', [AcaraController::class, 'setStatusEvent']);

    // User
    Route::get('/user/profile', [UserController::class, 'index']);

    // Frame
    Route::post('/frame/create', [FrameController::class, 'create']);
    Route::get('/frame/index', [FrameController::class, 'index']);
    Route::get('/frame/{uid}', [FrameController::class, 'show']);
    Route::delete('/frame/delete/{uid}', [FrameController::class, 'delete']);
    Route::post('/frame/update/{uid}', [FrameController::class, 'update']);

    Route::get('/acara/{acara_uid}/sessions', [SessionController::class, 'getByAcaraUid']);
    Route::delete('/session/{session_uid}', [SessionController::class, 'destroy']);
    Route::get('acara/{acara_uid}/sessions/export', [SessionController::class, 'exportToExcel']);
});
