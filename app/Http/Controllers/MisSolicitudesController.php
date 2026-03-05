<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MisSolicitudesController extends Controller
{
    /**
     * Bandeja de solicitudes exclusivas creadas por el usuario autenticado
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];

        $query = Solicitud::query();

        // Filtramos para obtener SOLO las creadas por este usuario, a menos que sea Super Admin
        if (!in_array('Super Admin', $roles)) {
            $query->where('creado_por_id', $user->id);
        }

        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('categoria_general_id')) {
            $query->where('categoria_general_id', $request->categoria_general_id);
        }

        $solicitudes = $query->with(['seguimientos', 'creadoPor', 'responsable', 'agencia'])
                             ->orderBy('id', 'desc')
                             ->paginate(10);

        return response()->json($solicitudes);
    }
}
