<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    /**
     * Enviar un mensaje SMS utilizando la API de Tigo Business B2B.
     * 
     * @param string $telefono Número de teléfono (8 dígitos o con 502)
     * @param string $mensaje Contenido del mensaje (máx 160 caracteres, ideal < 100)
     * @return bool
     */
    public function sendSms($telefono, $mensaje)
    {
        Log::debug("SmsController: Recibido teléfono: " . $telefono);
        // 1. Preparar el teléfono (Asegurar formato 502XXXXXXXX)
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        if (strlen($telefono) == 8) {
            $telefono = '502' . $telefono;
        }

        // 2. Configuración API desde variables de entorno
        $tigoUser    = env('TIGO_USER');
        $tigoPass    = env('TIGO_PASSWORD');
        $apiKey      = env('TIGO_API_KEY');
        $apiSecret   = env('TIGO_API_SECRET');
        $orgId       = env('TIGO_ORG_ID');

        // Validar que las credenciales existan
        $v = [
            'TIGO_USER' => $tigoUser,
            'TIGO_PASSWORD' => $tigoPass,
            'TIGO_API_KEY' => $apiKey,
            'TIGO_API_SECRET' => $apiSecret,
            'TIGO_ORG_ID' => $orgId
        ];

        foreach ($v as $key => $value) {
            if (!$value) {
                Log::error("SmsController: La variable {$key} no está configurada o es nula.");
            }
        }

        if (!$tigoUser || !$tigoPass || !$apiKey || !$apiSecret || !$orgId) {
            return false;
        }

        try {
            // 3. Obtener Token de Acceso
            $tokenResponse = Http::asForm()
                ->withBasicAuth($tigoUser, $tigoPass)
                ->post('https://prod.api.tigo.com/oauth/client_credential/accesstoken?grant_type=client_credentials');

            if (!$tokenResponse->successful()) {
                Log::error("SmsController Error Token: " . $tokenResponse->body());
                return false;
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
                    'body'          => $mensaje,
                    'priority'      => 0,
                    // 'shortcodeId'   => env('TIGO_SHORTCODE', 'TIGO'),
                    // 'shortcodeType' => 'pretty_code',
                ]);

            if ($smsResponse->successful()) {
                Log::info("SMS Enviado correctamente a " . $telefono . ": " . $mensaje);
                return true;
            } else {
                Log::error("SmsController Falló el envío a {$telefono}: " . $smsResponse->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("SmsController Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método para pruebas desde una ruta
     */
    public function testSend(Request $request)
    {
        $request->validate([
            'telefono' => 'required|numeric|digits:8',
            'mensaje'  => 'required|string|max:160',
        ]);

        $success = $this->sendSms($request->telefono, $request->mensaje);

        if ($success) {
            return response()->json(['status' => 'success', 'message' => 'Enviado correctamente']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Falló el envío'], 500);
        }
    }
}
