<?php


use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/prueba-whatsapp', function () {
//     return view('prueba-whatsapp');
// });


// Route::post('/enviar-mensaje-manual', function (Request $request) {

//     // 1. Preparar el teléfono (Le pegamos el 502 si el usuario no lo puso)
//     $telefono = $request->input('telefono');
//     // Limpieza básica por si puso espacios
//     $telefono = preg_replace('/[^0-9]/', '', $telefono);
//     if (!str_starts_with($telefono, '502')) {
//         $telefono = '502' . $telefono;
//     }

//     // 2. Configuración API
//     $token   = env('WHATSAPP_TOKEN');
//     $phoneId = env('WHATSAPP_PHONE_ID');
//     $url     = "https://graph.facebook.com/v21.0/{$phoneId}/messages";

//     // 3. ENVIAR LA PETICIÓN
//     $response = Http::withToken($token)->post($url, [
//         'messaging_product' => 'whatsapp',
//         'to'       => $telefono,
//         'type'     => 'template',
//         'template' => [
//             'name'     => 'alerta_solicitud_v2', // <--- TU NOMBRE EXACTO
//             'language' => ['code' => 'es'],      // <--- Español
//             'components' => [
//                 [
//                     'type' => 'body',
//                     'parameters' => [
//                         // OJO AL ORDEN: Tiene que ser igual a tu texto de Meta

//                         // {{1}} Nombre
//                         [ 'type' => 'text', 'text' => $request->input('v1_nombre') ],

//                         // {{2}} Tipo de Servicio
//                         [ 'type' => 'text', 'text' => $request->input('v2_tipo') ],

//                         // {{3}} Fecha Vencimiento
//                         [ 'type' => 'text', 'text' => $request->input('v3_fecha') ],

//                         // {{4}} ID Solicitud
//                         [ 'type' => 'text', 'text' => $request->input('v4_id') ],
//                     ]
//                 ]
//             ]
//         ]
//     ]);

//     // 4. Resultado
//     if ($response->successful()) {
//         return back()->with('status', '✅ ¡Mensaje Enviado! Revisa tu WhatsApp.');
//     } else {
//         // Mostramos el error completo para debug
//         return back()->with('error', '❌ Error: ' . $response->body());
//     }
// });
