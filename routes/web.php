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
