<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BandejaAdminController extends Controller
{
    /**
     * Bandeja de solicitudes administrativas general
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];
        $permisos = $user->permisos ?? [];

        // Protección: Solo Super Admins o usuarios con permiso de asignar solicitudes administrativas
        if (!in_array('Super Admin', $roles) &&
            !in_array('asignar_solicitudes-administrativas', $permisos)) {

            return response()->json([
                'message' => 'Acceso denegado. No tiene permisos para visualizar la bandeja administrativa.'
            ], 403);
        }

        $query = Solicitud::query();

        // Forzar siempre a que sea categoría 2 (Administrativa) por seguridad del backend
        $query->where('categoria_general_id', 2);

        // Filtro por estado
        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        $solicitudes = $query->with(['seguimientos', 'creadoPor', 'responsable', 'agencia'])
                             ->orderBy('id', 'desc')
                             ->paginate(20);

        return response()->json($solicitudes);
    }

    /**
     * Bandeja de solicitudes administrativas asignadas al técnico logueado
     */
    public function misAsignaciones(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];

        $query = Solicitud::query()
            ->where('categoria_general_id', 2);

        // Si es Super Admin, ve TODAS las que ya están asignadas a alguien.
        // Si no es Super Admin, ve SOLAMENTE las que le asignaron a él.
        if (in_array('Super Admin', $roles)) {
            $query->whereNotNull('responsable_id');
        } else {
            $query->where('responsable_id', $user->id);
        }

        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        $solicitudes = $query->with(['seguimientos', 'creadoPor', 'responsable', 'agencia'])
                             ->orderBy('id', 'desc')
                             ->paginate(20);

        return response()->json($solicitudes);
    }
}
