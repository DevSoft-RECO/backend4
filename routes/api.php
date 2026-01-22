<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\SolicitudCategoriaController;
use App\Http\Controllers\Sincronizacion\AgenciaController;
use App\Http\Controllers\Sincronizacion\AgencySyncController;
use App\Http\Controllers\Sincronizacion\PuestoController;
use App\Http\Controllers\Sincronizacion\PuestoSyncController;

Route::middleware('sso')->prefix('solicitudes')->group(function () {
    // Categorias Generales
    Route::get('/categorias-generales', [App\Http\Controllers\SolicitudCategoriaGeneralController::class, 'index']);
    Route::post('/categorias-generales', [App\Http\Controllers\SolicitudCategoriaGeneralController::class, 'store']);
    Route::put('/categorias-generales/{id}', [App\Http\Controllers\SolicitudCategoriaGeneralController::class, 'update']);
    Route::delete('/categorias-generales/{id}', [App\Http\Controllers\SolicitudCategoriaGeneralController::class, 'destroy']);

    // Subcategorias (Antes Categorias)
    Route::get('/subcategorias', [App\Http\Controllers\SolicitudSubcategoriaController::class, 'index']);
    Route::post('/subcategorias', [App\Http\Controllers\SolicitudSubcategoriaController::class, 'store']);
    Route::put('/subcategorias/{id}', [App\Http\Controllers\SolicitudSubcategoriaController::class, 'update']);
    Route::delete('/subcategorias/{id}', [App\Http\Controllers\SolicitudSubcategoriaController::class, 'destroy']);

    // Alias legacy or remove? Removing as per plan.

    Route::get('/', [SolicitudController::class, 'index']);
    Route::post('/', [SolicitudController::class, 'store']);
    Route::get('/{id}', [SolicitudController::class, 'show']);
    Route::put('/{id}/asignar', [SolicitudController::class, 'assign']);
    Route::put('/{id}/tomar', [SolicitudController::class, 'take']);
    Route::post('/{id}/seguimiento', [SolicitudController::class, 'addSeguimiento']);
    Route::post('/{id}/validar', [SolicitudController::class, 'validateValidation']);
    Route::get('/{id}/file-url', [SolicitudController::class, 'getFileUrl']);

    // Gestión de Evidencias (Subir/Eliminar sueltas)
    Route::post('/{id}/evidence', [SolicitudController::class, 'addEvidence']);
    Route::delete('/{id}/evidence', [SolicitudController::class, 'deleteEvidence']);

});

// Usuarios (Proxy a App Madre) - Fuera del prefijo 'solicitudes' pero protegido por SSO
Route::middleware('sso')->get('/usuarios', [\App\Http\Controllers\UsuarioController::class, 'index']);
Route::middleware('sso')->get('/usuarios', [\App\Http\Controllers\UsuarioController::class, 'index']);
Route::middleware('sso')->get('/puestos', [PuestoController::class, 'index']);

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
Route::middleware('sso')->group(function () {
    // Agencias
    Route::post('/sincronizar-agencias', [AgencySyncController::class, 'sync']);
    Route::get('/agencias', [AgenciaController::class, 'index']);

    // Puestos
    Route::post('/sincronizar-puestos', [PuestoSyncController::class, 'sync']);
    // Route::get('/puestos', ... ) ya está definido arriba globalmente

});
