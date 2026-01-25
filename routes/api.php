<?php

use App\Http\Controllers\AcaraController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::prefix('client')->group(function () {
    // Public access untuk client photobooth
    Route::get('/acara/active', [AcaraController::class, 'getActive']);
    Route::get('/acara/{uid}', [AcaraController::class, 'show']);
    Route::get('/frame/by-acara/{acara_uid}', [FrameController::class, 'getByAcara']);

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
});

// Route khusus untuk sudah terautentikasi
Route::middleware('auth:sanctum')->group(function () {
    // Acara
    Route::post('/acara/create', [AcaraController::class, 'create']);
    Route::delete('/acara/delete/{uid}', [AcaraController::class, 'delete']);
    Route::post('/acara/update/{uid}', [AcaraController::class, 'update']);
    Route::get('/acara/index', [AcaraController::class, 'index']);
    Route::get('/acara/{uid}', [AcaraController::class, 'show']);

    // Frame
    Route::post('/frame/create', [FrameController::class, 'create']);
    Route::get('/frame/index', [FrameController::class, 'index']);
    Route::get('/frame/{uid}', [FrameController::class, 'show']);
    Route::delete('/frame/delete/{uid}', [FrameController::class, 'delete']);
    Route::post('/frame/update/{uid}', [FrameController::class, 'update']);
});
