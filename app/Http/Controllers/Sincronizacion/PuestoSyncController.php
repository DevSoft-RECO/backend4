<?php

namespace App\Http\Controllers\Sincronizacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Puesto;

class PuestoSyncController extends Controller
{
    public function sync(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token no proporcionado.'], 401);
        }

        $baseUrl = config('services.app_madre.url');

        if (empty($baseUrl)) {
            return response()->json([
                'message' => 'Error de Configuración Critico',
                'error' => 'La variable APP_MADRE_URL no está definida en el archivo .env de la App Hija.'
            ], 500);
        }

        $endpoint = rtrim($baseUrl, '/') . '/api/puestos';

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

            $puestos = $response->json();
            $count = 0;

            foreach ($puestos as $puestoData) {
                 Puesto::updateOrCreate(
                    ['puesto_madre_id' => $puestoData['id']],
                    [
                        'nombre' => $puestoData['nombre']
                    ]
                 );
                 $count++;
            }

            return response()->json([
                'status' => 'success',
                'processed' => $count,
                'message' => 'Puestos actualizados correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Excepción durante la sincronización',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
