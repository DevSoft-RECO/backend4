<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\SolicitudCategoriaController;

Route::middleware('sso')->prefix('solicitudes')->group(function () {
    // Categorias (Must be before /{id})
    Route::get('/categorias', [SolicitudCategoriaController::class, 'index']);
    Route::post('/categorias', [SolicitudCategoriaController::class, 'store']);
    Route::put('/categorias/{id}', [SolicitudCategoriaController::class, 'update']);
    Route::delete('/categorias/{id}', [SolicitudCategoriaController::class, 'destroy']);

    Route::get('/', [SolicitudController::class, 'index']);
    Route::post('/', [SolicitudController::class, 'store']);
    Route::get('/{id}', [SolicitudController::class, 'show']);
    Route::put('/{id}/asignar', [SolicitudController::class, 'assign']);
    Route::put('/{id}/tomar', [SolicitudController::class, 'take']);
    Route::post('/{id}/seguimiento', [SolicitudController::class, 'addSeguimiento']);
    Route::post('/{id}/validar', [SolicitudController::class, 'validateValidation']);
});
