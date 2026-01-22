<?php

namespace App\Http\Controllers\Sincronizacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Agencia;

class AgencySyncController extends Controller
{
    public function sync(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token no proporcionado.'], 401);
        }

        // 1. Obtener la URL desde la configuración
        $baseUrl = config('services.app_madre.url');

        // 2. VERIFICACIÓN: Si no se configuró en el .env, detenemos todo.
        if (empty($baseUrl)) {
            return response()->json([
                'message' => 'Error de Configuración Critico',
                'error' => 'La variable APP_MADRE_URL no está definida en el archivo .env de la App Hija.'
            ], 500);
        }

        // 3. Limpieza de URL y armamos el endpoint
        $endpoint = rtrim($baseUrl, '/') . '/api/agencias';

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->retry(3, 100)
                ->acceptJson()
                ->get($endpoint);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Error al conectar con App Madre',
                    'details' => $response->body()
                ], $response->status());
            }

            $agencias = $response->json();
            $count = 0;

            foreach ($agencias as $agenciaData) {
                 // Ajustamos el mapeo de datos segun la respuesta esperada
                 Agencia::updateOrCreate(
                    ['agencia_madre_id' => $agenciaData['id']],
                    [
                        'codigo' => $agenciaData['codigo'],
                        'nombre' => $agenciaData['nombre'],
                        'direccion' => $agenciaData['direccion'] ?? null,
                    ]
                 );
                 $count++;
            }

            return response()->json([
                'status' => 'success',
                'processed' => $count,
                'message' => 'Datos actualizados correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Excepción durante la sincronización',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
