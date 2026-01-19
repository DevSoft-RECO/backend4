<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PuestoController extends Controller
{
    /**
     * Obtener lista de puestos desde la App Madre.
     */
    public function index(Request $request)
    {
        $motherUrl = config('services.app_madre.url');
        $token = $request->bearerToken();

        if (!$motherUrl || !$token) {
            return response()->json(['message' => 'Error de configuración o autenticación'], 500);
        }

        // Cachear lista de puestos por 1 hora (menos volátil que usuarios)
        $puestos = Cache::remember('puestos_list_proxy', 3600, function () use ($motherUrl, $token) {
            $response = Http::withToken($token)->get("{$motherUrl}/api/puestos");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        });

        if (!$puestos) {
             $response = Http::withToken($token)->get("{$motherUrl}/api/puestos");
             if ($response->failed()) {
                 return response()->json(['message' => 'Error consultando puestos externos'], $response->status());
             }
             return response()->json($response->json());
        }

        return response()->json($puestos);
    }
}
