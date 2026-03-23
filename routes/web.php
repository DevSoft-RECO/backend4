<?php


use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



// 1. Ruta para mostrar el formulario
Route::get('/prueba-tigo', function () {
    return view('prueba-tigo');
});



Route::post('/enviar-mensaje-tigo', [\App\Http\Controllers\SmsController::class, 'testSend']);

// Ruta Anti-JSON / Rescate de Sesión Expirada
Route::get('/login', function () {
    // Si falla el JWT y Laravel intenta redirigir al "login", lo mandamos de vuelta al portal Madre
    $frontendUrl = env('APP_URL_FRONTEND', 'http://localhost:5173');
    return redirect($frontendUrl . '/login?session_expired=true');
})->name('login');
