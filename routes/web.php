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

// ==========================================
// PRUEBA TIGO BUSINESS B2B
// ==========================================





// 1. Ruta para mostrar el formulario
Route::get('/prueba-tigo', function () {
    return view('prueba-tigo');
});



Route::post('/enviar-mensaje-tigo', function (Request $request) {
    // 1. Validaciones
    $request->validate([
        'telefono' => 'required|numeric|digits:8',
        'mensaje'  => 'required|string|max:160',
    ]);

    // 2. Preparar variables
    $telefono = '502' . $request->input('telefono');

    $tigoUser    = env('TIGO_USER');
    $tigoPass    = env('TIGO_PASSWORD');
    $apiKey      = env('TIGO_API_KEY');
    $apiSecret   = env('TIGO_API_SECRET');
    $orgId       = env('TIGO_ORG_ID');

    // 3. Obtener Token
    $tokenResponse = Http::asForm()
        ->withBasicAuth($tigoUser, $tigoPass)
        ->post('https://prod.api.tigo.com/oauth/client_credential/accesstoken?grant_type=client_credentials');

    if (!$tokenResponse->successful()) {
        return back()->with('error', '❌ Error Token: ' . $tokenResponse->body());
    }

    $accessToken = $tokenResponse->json('access_token');

    // 4. Enviar SMS
    $smsResponse = Http::withToken($accessToken)
        ->withHeaders([
            'APIKey'       => $apiKey,
            'APISecret'    => $apiSecret,
            'Content-Type' => 'application/json'
        ])
        ->post("https://prod.api.tigo.com/v1/tigo/b2b/gt/comcorp/messages/organizations/{$orgId}", [
            'protocol'      => 'sms',
            'msisdn'        => $telefono,
            'body'          => $request->input('mensaje'),
            'priority'      => 0,

            // --- CAMBIO IMPORTANTE ---
            // Comentamos estas líneas para que Tigo use tu remitente por defecto.
            // 'shortcodeId'   => env('TIGO_SHORTCODE', 'TIGO'),
            // 'shortcodeType' => 'pretty_code',
        ]);

    if ($smsResponse->successful()) {
        return back()->with('status', '✅ Enviado correctamente a ' . $telefono);
    } else {
        return back()->with('error', '❌ Falló el envío: ' . $smsResponse->body());
    }
});
