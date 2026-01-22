<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    /**
     * Obtener lista de usuarios desde la Base de Datos Local.
     * Solo devuelve usuarios que ya han sido sincronizados (han hecho login al menos una vez).
     */
    public function index(Request $request)
    {
        // Obtener usuarios locales con sus relaciones
        $users = \App\Models\User::with(['puesto', 'agencia'])->get();

        return response()->json($users);
    }
    /**
     * Obtener el usuario autenticado actual (Sincronizado JIT).
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // Construir respuesta manual para incluir propiedades dinámicas y relaciones
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'telefono' => $user->telefono,
            'puesto_id' => $user->puesto_id,
            'agencia_id' => $user->agencia_id,
            'puesto' => $user->puesto,
            'agencia' => $user->agencia,
            'avatar' => $user->avatar, // Propiedad inyectada en Middleware
            'roles' => $user->roles,   // Propiedad inyectada en Middleware
            'permissions' => $user->permisos, // Frontend espera 'permissions'
            'permisos' => $user->permisos,
        ]);
    }
}
