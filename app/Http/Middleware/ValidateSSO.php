<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ValidateSSO
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }

        try {
            // Cargar la llave pública almacenada localmente
            $publicKeyPath = storage_path('oauth-public.key');

            if (!file_exists($publicKeyPath)) {
                throw new \Exception("Falta la llave pública oauth-public.key en el servidor hijo");
            }

            $publicKey = file_get_contents($publicKeyPath);
            JWT::$leeway = 60; // Mitigar desincronizaciones de reloj entre servidores

            // 1. Decodificar el Token en memoria de forma local (RS256)
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // 2. Carga rápida pasiva desde la base de datos local
            $dbUser = User::with(['puesto', 'agencia'])->where('id', $decoded->sub)->first();

            if ($dbUser) {
                // Loguear usuario real de la base de datos
                Auth::setUser($dbUser);
            } else {
                // 3. Fallback de Red de Seguridad (Usuario no sincronizado en DB local aún)
                // Creamos un modelo virtual no persistido con sus roles/permisos del JWT
                $user = new User([
                    'id' => $decoded->sub,
                    'roles_list' => $decoded->roles ?? [],
                    'permissions_list' => $decoded->permissions ?? [],
                ]);
                Auth::setUser($user);
            }

        } catch (\Exception $e) {
            return response()->json(['message' => 'Acceso Denegado (SSO): ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
