<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Auth\GenericUser;
use Exception;

class ValidateSSO
{
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el token del encabezado Authorization: Bearer ...
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }

        try {
            // 1. Validar existencia de la Llave Pública
            $publicKeyPath = storage_path('oauth-public.key');

            if (!file_exists($publicKeyPath)) {
                throw new Exception("Error de servidor: Falta llave pública de validación.");
            }

            $publicKey = file_get_contents($publicKeyPath);
            JWT::$leeway = 60; // Margen de 60s por si los relojes de los servidores no están sincronizados

            // 2. Decodificar y Validar firma del Token (RS256)
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // 3. Obtener URL de la App Madre
            // NOTA: Usamos config() porque en producción env() devuelve null si la caché está activa.
            $motherUrl = config('services.app_madre.url');

            if (empty($motherUrl)) {
                throw new Exception("Configuración incompleta: URL Madre no definida.");
            }

            // 4. Intentar obtener datos frescos (Roles/Permisos) desde la Madre
            $response = Http::withToken($token)->get("{$motherUrl}/api/me");

            if ($response->successful()) {
                // ÉXITO: Tenemos conexión. Usamos los datos completos del usuario (Roles actualizados).
                $userData = $response->json();
                $userData['id'] = $decoded->sub; // Aseguramos que el ID venga del token
                $user = new GenericUser($userData);
            } else {
                // FALLBACK: Si la Madre está caída o lenta, no bloqueamos al usuario.
                // Usamos los datos básicos que vienen incrustados en el token JWT.
                $userData = (array) $decoded;
                $userData['id'] = $decoded->sub;
                $user = new GenericUser($userData);
            }

            // Establecer el usuario en la sesión actual de la solicitud
            Auth::setUser($user);

        } catch (Exception $e) {
            // Si el token es inválido, expirado o manipulado, devolvemos 401
            return response()->json(['message' => 'Acceso Denegado: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
