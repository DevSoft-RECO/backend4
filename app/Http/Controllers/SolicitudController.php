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
    private function getPuestoNombre($user)
    {
        $puesto = $user->puesto ?? $user->cargo ?? 'Sin Puesto';

        if (is_array($puesto)) {
            return $puesto['nombre'] ?? $puesto['name'] ?? 'Sin Puesto';
        }

        if (is_object($puesto)) {
             return $puesto->nombre ?? $puesto->name ?? 'Sin Puesto';
        }

        return $puesto;
    }

    /**
     * Bandeja de solicitudes
     */
    public function index(Request $request)
    {
        $user = Auth::user(); // GenericUser from ValidateSSO
        $roles = $user->roles ?? [];
        $puestoNombre = $this->getPuestoNombre($user);
        $agencia_id = $user->idagencia ?? null;

        $query = Solicitud::query();

        // 3. Solicitante normal / Jefe Agencia / Super Admin
        if ($request->has('mis_asignaciones') && $request->mis_asignaciones == 'true') {
            $query->where('responsable_id', $user->id);
        } else {
            // Si NO es Super Admin NI Informática (Soporte) -> Aplicar filtro de Agencia/Creador
            if (!in_array('Super Admin', $roles) && !in_array('informatica', $roles)) {
                 $query->where(function($q) use ($agencia_id, $user) {
                     if ($agencia_id) {
                        $q->where('agencia_id', $agencia_id);
                     }
                     $q->orWhere('creado_por_id', $user->id);
                 });
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

        if (!in_array('Super Admin', $roles) && !in_array('Jefes de Agencia', $roles) && !in_array('crear_gestiones', $permisos)) {
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
            'creado_por_cargo' => $this->getPuestoNombre($user),
            'agencia_id' => $user->idagencia ?? null,
            'agencia_id' => $user->idagencia ?? null,
            // Categoria se asigna despues
        ]);

        // Procesar evidencias iniciales
        $evidenciasUrls = [];
        if ($request->hasFile('evidencias')) {
            foreach ($request->file('evidencias') as $file) {
                // store in public/solicitudes/inicial
                $path = $file->store('solicitudes/inicial', 'public');
                $evidenciasUrls[] = asset('storage/' . $path);
            }
            $solicitud->update(['evidencias_inicial' => $evidenciasUrls]);
        }

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

        // if (!in_array('Super Admin', $roles) && !in_array('solicitudes.asignar', $permisos)) {
        //      return response()->json(['message' => 'Sin permiso para asignar'], 403);
        // }

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
            'seguimiento_por_cargo' => $this->getPuestoNombre($user),
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

        // if (!in_array('Super Admin', $roles) && !in_array('solicitudes.tomar', $permisos)) {
        //     return response()->json(['message' => 'No tiene permiso para tomar casos'], 403);
        // }

        $request->validate([
             'categoria_id' => 'required|integer|exists:solicitud_categorias,id'
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $puestoNombre = $request->responsable_cargo ?? $this->getPuestoNombre($user);

        $solicitud->update([
            'estado' => 'asignada',
            'responsable_id' => $user->id,
            'responsable_nombre' => $user->name,
            'responsable_cargo' => $puestoNombre,
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
            'seguimiento_por_cargo' => $puestoNombre,
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

        // if (!in_array('Super Admin', $roles) && !in_array('solicitudes.seguimiento', $permisos)) {
        //      return response()->json(['message' => 'No tiene permiso para dar seguimiento'], 403);
        // }

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
            'seguimiento_por_cargo' => $this->getPuestoNombre($user),
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

        // if (!in_array('Super Admin', $roles) && !in_array('solicitudes.validar', $permisos)) {
        //      return response()->json(['message' => 'No tiene permiso para validar'], 403);
        // }

        $request->validate([
            'accion' => 'required|in:cerrar,reabrir',
            'comentario' => 'required_if:accion,reabrir|nullable|string',
            'tipo_solucion' => 'nullable|in:total,parcial'
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $nuevoEstado = $request->accion === 'cerrar' ? 'cerrada' : 'reabierta';
        $tipoAccion = $request->accion === 'cerrar' ? 'validacion' : 'reapertura';

        $solicitud->update([
            'estado' => $nuevoEstado,
            'tipo_solucion' => ($request->accion === 'cerrar') ? ($request->tipo_solucion ?? 'total') : null
        ]);

        $textoComentario = $request->comentario ?? 'Sin comentario adicional.';
        if ($request->accion === 'cerrar') {
             $solucion = ucfirst($request->tipo_solucion ?? 'total');
             $textoComentario = "Solución {$solucion}. " . ($request->comentario ? "Comentario: {$request->comentario}" : "");
        } else {
             $textoComentario = "Rechazado/Reabierto. Motivo: {$request->comentario}";
        }

        SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $this->getPuestoNombre($user),
            'comentario' => $textoComentario,
            'tipo_accion' => $tipoAccion
        ]);

        return response()->json($solicitud);
    }
}
