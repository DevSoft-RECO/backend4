<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Puesto;
use Illuminate\Http\JsonResponse;

class SSOController extends Controller
{
    /**
     * Endpoint JIT para obtener el perfil y sincronizar base de datos local.
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $motherUrl = config('services.app_madre.url') ?? env('APP_MADRE_URL');

        try {
            // 1. Consultar perfil en App Madre
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$motherUrl}/api/me");

            if (!$response->successful()) {
                $response = Http::withToken($token)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get("{$motherUrl}/api/user");
            }

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Fallo en la comunicación con el ecosistema central (Madre)',
                    'error' => $response->reason()
                ], 502);
            }

            $userData = $response->json();

            if (isset($userData['data'])) {
                $userData = $userData['data'];
            }

            $username = $userData['username'] ?? 'unknown';

            // 2. APLANAMIENTO Y FILTRADO POR CATEGORÍA "App_Gestiones"
            $roles = $this->flatten($userData['roles'] ?? $userData['roles_list'] ?? []);

            if (isset($userData['permissions_detailed']) && is_array($userData['permissions_detailed'])) {
                $filteredPermissions = array_filter($userData['permissions_detailed'], function ($perm) {
                    return isset($perm['category']) && $perm['category'] === 'App_Gestiones';
                });
                // array_values() evita serializar el array como un objeto asociativo JSON
                $permisos = array_values(array_map(function ($perm) {
                    return $perm['name'] ?? '';
                }, $filteredPermissions));
            } else {
                $permisos = $this->flatten($userData['permisos'] ?? $userData['permissions'] ?? $userData['permissions_list'] ?? []);
            }

            // 3. Obtener JTI (Identificador único de sesión en ecosistema)
            $jti = null;
            $tokenParts = explode('.', $token);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                $jti = $payload['jti'] ?? null;
            }

            // 4. SINCRONIZACIÓN EN CASCADA JIT

            // A) Sincronizar Agencia local
            $agenciaId = null;
            if (isset($userData['agencia'])) {
                $agData = $userData['agencia'];
                $agencia = Agencia::updateOrCreate(
                    ['agencia_madre_id' => $agData['id']],
                    [
                        'nombre' => $agData['nombre'],
                        'codigo' => $agData['codigo'] ?? null,
                        'direccion' => $agData['direccion'] ?? null,
                    ]
                );
                $agenciaId = $agencia->id;
            }

            // B) Sincronizar Puesto local
            $puestoId = null;
            if (isset($userData['puesto'])) {
                $pData = $userData['puesto'];
                if (is_array($pData)) {
                    $puesto = Puesto::updateOrCreate(
                        ['puesto_madre_id' => $pData['id']],
                        [
                            'nombre' => $pData['nombre']
                        ]
                    );
                    $puestoId = $puesto->id;
                }
            }

            // C) Sincronizar Usuario local
            $user = User::where('id', $userData['id'])
                ->orWhereRaw('LOWER(username) = ?', [strtolower($username)])
                ->first();

            $updateData = [
                'id'               => $userData['id'], // ID Madre
                'name'             => $userData['name'],
                'email'            => $userData['email'],
                'telefono'         => $userData['telefono'] ?? null,
                'puesto_id'        => $puestoId,
                'agencia_id'       => $agenciaId,
                'avatar'           => $userData['avatar'] ?? null,
                'roles_list'       => $roles,
                'permissions_list' => $permisos,
                'jti'              => $jti,
            ];

            if ($user) {
                $user->update($updateData);
            } else {
                $updateData['username'] = strtoupper($username);
                $user = User::create($updateData);
            }

            // 5. Mapear y estandarizar la respuesta para el Frontend de APP4
            $userData['id'] = $user->id;
            $userData['puesto_id'] = $puestoId;
            $userData['agencia_id'] = $agenciaId;
            $userData['puesto'] = $user->puesto;
            $userData['agencia'] = $user->agencia;
            $userData['avatar'] = $user->avatar;

            // Fallbacks de compatibilidad de Roles y Permisos para el cliente Vue
            $userData['roles'] = $roles;
            $userData['roles_list'] = $roles;
            $userData['permisos'] = $permisos;
            $userData['permissions'] = $permisos;
            $userData['_source'] = 'madre_sync_jit';

            return response()->json($userData);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno de sincronización JIT en App Gestiones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function flatten($items): array
    {
        if (!is_array($items)) return [];

        return array_map(function ($item) {
            return is_array($item) ? ($item['name'] ?? $item) : $item;
        }, $items);
    }
}
