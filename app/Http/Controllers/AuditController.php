<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuditController extends Controller
{
    protected $disk = 'gcs';

    /**
     * Obtener solicitudes para el dashboard de auditoría
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];
        $permissions = $user->permissions ?? $user->permisos ?? [];

        // Solo Super Admin o usuarios con permiso 'auditoria' pueden acceder
        if (!in_array('Super Admin', $roles) && !in_array('auditoria', $permissions)) {
            // Also check standard Laravel method if available
            if (!method_exists($user, 'hasPermissionTo') || !$user->hasPermissionTo('auditoria')) {
                return response()->json(['message' => 'No tiene permisos para acceder a esta información'], 403);
            }
        }

        $query = Solicitud::query();

        // Filtro por tipo (Categoria General) - 1: Tecnologico, 2: Administrativo
        if ($request->has('tipo') && $request->tipo) {
            $query->where('categoria_general_id', $request->tipo);
        } else {
             // By default, let's say we don't force a type, but if we must, we can handle it in the frontend
        }

        // Filtro por estado
        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        // Filtro por agencia
        if ($request->has('agencia_id') && $request->agencia_id) {
            $query->where('agencia_id', $request->agencia_id);
        }

        $solicitudes = $query->with(['creadoPor', 'responsable', 'agencia', 'categoriaGeneral'])
                             ->orderBy('id', 'desc')
                             ->paginate(10);

        return response()->json($solicitudes);
    }

    /**
     * Ver detalles completos de una solicitud para auditoría, incluyendo URLs firmadas de archivos
     */
    public function show($id)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];
        $permissions = $user->permissions ?? $user->permisos ?? [];

        if (!in_array('Super Admin', $roles) && !in_array('auditoria', $permissions)) {
            if (!method_exists($user, 'hasPermissionTo') || !$user->hasPermissionTo('auditoria')) {
                return response()->json(['message' => 'No tiene permisos para ver esta solicitud'], 403);
            }
        }

        $solicitud = Solicitud::with(['seguimientos', 'creadoPor.puesto', 'responsable', 'agencia'])->findOrFail($id);

        // Generar URLs firmadas para GCS
        $disk = Storage::disk($this->disk);
        $ttl = now()->addMinutes(20);

        // 1. Evidencias Iniciales
        if ($solicitud->evidencias_inicial && is_array($solicitud->evidencias_inicial)) {
            $solicitud->evidencias_inicial_urls = array_map(function($path) use ($disk, $ttl) {
                try {
                    return $disk->temporaryUrl($path, $ttl);
                } catch (\Exception $e) {
                    return null;
                }
            }, $solicitud->evidencias_inicial);
        }

        // 1.5 Evidencias Finales
        if ($solicitud->evidencias_final && is_array($solicitud->evidencias_final)) {
            $solicitud->evidencias_final_urls = array_map(function($path) use ($disk, $ttl) {
                 try {
                     return $disk->temporaryUrl($path, $ttl);
                 } catch (\Exception $e) {
                     return null;
                 }
            }, $solicitud->evidencias_final);
        }

        // 2. Evidencias en Seguimientos
        $solicitud->seguimientos->transform(function($seg) use ($disk, $ttl) {
            if (!empty($seg->evidencias) && is_array($seg->evidencias)) {
                $urls = array_map(function($path) use ($disk, $ttl) {
                    try {
                        return $disk->temporaryUrl($path, $ttl);
                    } catch (\Exception $e) {
                         return null;
                    }
                }, $seg->evidencias);
                $seg->evidencias = $urls;
            }
            return $seg;
        });

        return response()->json($solicitud);
    }
}
