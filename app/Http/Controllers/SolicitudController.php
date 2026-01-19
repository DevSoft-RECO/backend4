<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Models\SolicitudSeguimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SolicitudController extends Controller
{
    /**
     * Bandeja de solicitudes
     */
    public function index(Request $request)
    {
        $user = Auth::user(); // GenericUser from ValidateSSO
        $roles = $user->roles ?? [];
        $cargo = $user->cargo ?? '';
        $agencia_id = $user->idagencia ?? null;

        $query = Solicitud::query();

        // 1. Jefe de Informática ve todo
        if (in_array('informatica_jefe', $roles) || $cargo === 'Jefe de Informática') {
            // Ve todo
        }
        // 2. Informática (Soporte)
        elseif (in_array('informatica', $roles)) {
             // Asumimos ve todo por ahora
        }
        // 3. Solicitante normal
        else {
             $query->where('agencia_id', $agencia_id);
        }

        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        $solicitudes = $query->with('seguimientos')->latest()->paginate(20);

        return response()->json($solicitudes);
    }

    /**
     * Crear solicitud
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $permisos = $user->permisos ?? [];
        $roles = $user->roles ?? [];

        if (!in_array('Super Admin', $roles) && !in_array('solicitudes.crear', $permisos)) {
            return response()->json(['message' => 'No tiene permiso para crear solicitudes'], 403);
        }

        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'categoria_id' => 'nullable|integer'
        ]);

        $solicitud = Solicitud::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'estado' => 'reportada',
            'creado_por_id' => $user->id,
            'creado_por_nombre' => $user->name,
            'creado_por_cargo' => $user->cargo ?? null,
            'agencia_id' => $user->idagencia ?? null,
            'categoria_id' => $request->categoria_id,
        ]);

        return response()->json($solicitud, 201);
    }

    /**
     * Ver detalle
     */
    public function show(Request $request, $id)
    {
        $solicitud = Solicitud::with('seguimientos')->findOrFail($id);
        return response()->json($solicitud);
    }

    /**
     * Asignar responsable (Jefe Informática)
     */
    public function assign(Request $request, $id)
    {
        $user = Auth::user();
        $permisos = $user->permisos ?? [];
        $roles = $user->roles ?? [];

        if (!in_array('Super Admin', $roles) && !in_array('solicitudes.asignar', $permisos)) {
             return response()->json(['message' => 'Sin permiso para asignar'], 403);
        }

        $request->validate([
            'responsable_id' => 'required_without:proveedor_id',
            'responsable_nombre' => 'required_with:responsable_id',
            'responsable_cargo' => 'nullable',
            'responsable_tipo' => 'required|in:interno,externo',
            'proveedor_id' => 'required_if:responsable_tipo,externo',
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $solicitud->update([
            'estado' => 'asignada',
            'responsable_id' => $request->responsable_id,
            'responsable_nombre' => $request->responsable_nombre,
            'responsable_cargo' => $request->responsable_cargo,
            'responsable_tipo' => $request->responsable_tipo,
            'proveedor_id' => $request->proveedor_id,
            'fecha_asignacion' => Carbon::now(),
        ]);

        SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $user->cargo,
            'comentario' => "Solicitud asignada a {$request->responsable_nombre}",
            'tipo_accion' => 'comentario'
        ]);

        return response()->json($solicitud);
    }

    /**
     * Tomar caso (Auto-asignación)
     */
    public function take(Request $request, $id)
    {
        $user = Auth::user();
        $permisos = $user->permisos ?? [];
        $roles = $user->roles ?? [];

        if (!in_array('Super Admin', $roles) && !in_array('solicitudes.tomar', $permisos)) {
            return response()->json(['message' => 'No tiene permiso para tomar casos'], 403);
        }

        $solicitud = Solicitud::findOrFail($id);

        $solicitud->update([
            'estado' => 'en_seguimiento',
            'responsable_id' => $user->id,
            'responsable_nombre' => $user->name,
            'responsable_cargo' => $user->cargo,
            'responsable_tipo' => 'interno',
            'fecha_toma_caso' => Carbon::now(),
        ]);

        SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $user->cargo,
            'comentario' => "Caso tomado por {$user->name}",
            'tipo_accion' => 'visita'
        ]);

        return response()->json($solicitud);
    }

    /**
     * Agregar seguimiento (comentario, visita, evidencia)
     */
    public function addSeguimiento(Request $request, $id)
    {
        $user = Auth::user();
        $permisos = $user->permisos ?? [];
        $roles = $user->roles ?? [];

        if (!in_array('Super Admin', $roles) && !in_array('solicitudes.seguimiento', $permisos)) {
             return response()->json(['message' => 'No tiene permiso para dar seguimiento'], 403);
        }

        $request->validate([
            'comentario' => 'required|string',
            'tipo_accion' => 'required|in:visita,comentario,evidencia',
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $seguimiento = SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $user->cargo,
            'comentario' => $request->comentario,
            'tipo_accion' => $request->tipo_accion
        ]);

        if ($solicitud->estado == 'asignada') {
            $solicitud->update(['estado' => 'en_seguimiento']);
        }

        if ($request->boolean('solicitar_validacion')) {
            $solicitud->update(['estado' => 'pendiente_validacion']);
        }

        return response()->json($seguimiento, 201);
    }

    /**
     * Validar (Cerrar / Reabrir)
     */
    public function validateValidation(Request $request, $id)
    {
        $user = Auth::user();
        $permisos = $user->permisos ?? [];
        $roles = $user->roles ?? [];

        if (!in_array('Super Admin', $roles) && !in_array('solicitudes.validar', $permisos)) {
             return response()->json(['message' => 'No tiene permiso para validar'], 403);
        }

        $request->validate([
            'accion' => 'required|in:cerrar,reabrir',
            'comentario' => 'required|string'
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $nuevoEstado = $request->accion === 'cerrar' ? 'cerrada' : 'reabierta';
        $tipoAccion = $request->accion === 'cerrar' ? 'validacion' : 'reapertura';

        $solicitud->update([
            'estado' => $nuevoEstado
        ]);

        SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $user->cargo,
            'comentario' => $request->comentario . " (Acción: {$request->accion})",
            'tipo_accion' => $tipoAccion
        ]);

        return response()->json($solicitud);
    }
}
