<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UsuarioController extends Controller
{
    /**
     * Obtener lista de usuarios desde la App Madre.
     * Actúa como un proxy para evitar CORS y manejar autenticación unificada.
     */
    public function index(Request $request)
    {
        $motherUrl = config('services.app_madre.url');
        $token = $request->bearerToken();

        if (!$motherUrl || !$token) {
            return response()->json(['message' => 'Error de configuración o autenticación'], 500);
        }

        // Cachear la respuesta por 5 minutos para performance
        // Usamos el token como parte de la key si la respuesta depende de permisos,
        // o una key global 'users_list' si todos ven lo mismo.
        // Dado que es una lista general, usaremos una cache global pero corta.

        $users = Cache::remember('users_list_proxy', 300, function () use ($motherUrl, $token) {
            $response = Http::withToken($token)->get("{$motherUrl}/api/users");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        });

        if (!$users) {
            // Si falló la caché (o la primera petición), intentamos directo para obtener el error real
             $response = Http::withToken($token)->get("{$motherUrl}/api/users");
             if ($response->failed()) {
                 return response()->json(['message' => 'Error consultando usuarios externos'], $response->status());
             }
             return response()->json($response->json());
        }

        return response()->json($users);
    }
}
