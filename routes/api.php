<?php

use App\Http\Controllers\AcaraController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Route;

// Group API routes related to authentication
Route::post('/login', [AuthController::class, 'login']);

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

    //Session
    Route::post('/session/create', [SessionController::class, 'create']);
    Route::get('/session/index', [SessionController::class, 'index']);
    Route::get('/session/{uid}/check', [SessionController::class, 'checkActive']);
    Route::delete('/session/delete/{uid}', [SessionController::class, 'delete']);

    // Photo
    Route::post('/photo/create', [PhotoController::class, 'create']);
    Route::get('/photo/index', [PhotoController::class, 'index']);
    Route::get('/photo/{uid}', [PhotoController::class, 'show']);
    Route::post('/photo/{uid}/retake', [PhotoController::class, 'retake']);
    Route::delete('/photo/delete/{uid}', [PhotoController::class, 'destroy']);
    Route::delete('/photo/session/clear', [PhotoController::class, 'destroyBySession']);
    Route::get('/photo/{uid}/download', [PhotoController::class, 'download']);
    Route::get('/photo/session/{session_uid}/download-all', [PhotoController::class, 'downloadAllBySession']);

});
