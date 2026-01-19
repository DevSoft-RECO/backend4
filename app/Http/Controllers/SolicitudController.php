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
        if ($request->has('mis_asignaciones') && $request->mis_asignaciones == 'true') {
            $query->where('responsable_id', $user->id);
            // Si es 'mis asignaciones', ignoramos la restricción de agencia para que vean lo asignado incluso si es de otra agencia (casos especiales)
            // O mantenemos la lógica base. Por ahora, 'where responsable_id' es suficiente filtro.
        } else {
            // Lógica normal de permisos por rol/agencia si NO es mi bandeja
            // (Ya aplicada arriba parcialmente, pero refinamos)
            if (!in_array('informatica_jefe', $roles) && $user->cargo !== 'Jefe de Informática' && !in_array('informatica', $roles)) {
                 $query->where('agencia_id', $agencia_id);
            }
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
        ]);

        $solicitud = Solicitud::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'estado' => 'reportada',
            'creado_por_id' => $user->id,
            'creado_por_nombre' => $user->name,
            'creado_por_cargo' => $user->cargo ?? null,
            'agencia_id' => $user->idagencia ?? null,
            // Categoria se asigna despues
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
            'categoria_id' => 'required|integer|exists:solicitud_categorias,id',
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $solicitud->update([
            'estado' => 'asignada',
            'responsable_id' => $request->responsable_id,
            'responsable_nombre' => $request->responsable_nombre,
            'responsable_cargo' => $request->responsable_cargo,
            'responsable_tipo' => $request->responsable_tipo,
            'proveedor_id' => $request->proveedor_id,
            'categoria_id' => $request->categoria_id,
            'fecha_asignacion' => Carbon::now(),
        ]);

        SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $user->puesto ?? $user->cargo ?? 'Sin Cargo',
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

        $request->validate([
             'categoria_id' => 'required|integer|exists:solicitud_categorias,id'
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $solicitud->update([
            'estado' => 'asignada',
            'responsable_id' => $user->id,
            'responsable_nombre' => $user->name,
            'responsable_cargo' => $user->cargo,
            'responsable_tipo' => 'interno',
            'categoria_id' => $request->categoria_id,
            // fecha_toma_caso se marca cuando empieza a trabajar (moviendo a en_seguimiento) o aqui?
            // El usuario dijo: "Toma el caso... Estado => Asignada".
            // Pero proveedor: "Se registra Fecha toma caso... Estado => En seguimiento"
            // Dejaremos fecha_toma_caso null por ahora o Now() si se considera tomado.
            // Ajuste al requerimiento 2.2: "Estado -> Asignada"
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

        // Subida de evidencias
        $evidenciasUrls = [];
        if ($request->hasFile('evidencias')) {
            foreach ($request->file('evidencias') as $file) {
                // store in public/solicitudes
                $path = $file->store('solicitudes', 'public');
                $evidenciasUrls[] = asset('storage/' . $path);
            }
        }

        $seguimiento = SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $user->cargo,
            'comentario' => $request->comentario,
            'tipo_accion' => $request->tipo_accion,
            'evidencias' => $evidenciasUrls
        ]);

        // Estados
        // Si estaba asignada y agregamos seguimiento -> pasa a en_seguimiento
        if ($solicitud->estado == 'asignada') {
            $solicitud->update([
                'estado' => 'en_seguimiento',
                'fecha_toma_caso' => $solicitud->fecha_toma_caso ?? Carbon::now()
            ]);
        }

        // Si hay evidencias o el usuario marca "solicitar validacion" (o implicito por el flujo 3->4)
        // El usuario dijo "Estado -> Pendiente de validación" cuando se cargan evidencias?
        // "Información visible... Evidencias... Estado => Pendiente de validación"
        if (!empty($evidenciasUrls) || $request->tipo_accion === 'evidencia') {
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
