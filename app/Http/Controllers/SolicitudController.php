<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Models\SolicitudSeguimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SolicitudAsignada;
use App\Mail\SolicitudPendienteValidacion;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ImageOptimizer;

class SolicitudController extends Controller
{
    protected $disk = 'gcs';
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
        $permisos = $user->permisos ?? [];
        $puestoNombre = $this->getPuestoNombre($user);
        $agencia_id = $user->agencia_id ?? null;

        $query = Solicitud::query();

        // Filtro para usuarios que revisan las solicitudes asignadas a ellos (Técnicos/Asesores)
        if ($request->has('mis_asignaciones') && $request->mis_asignaciones == 'true') {
            $query->where('responsable_id', $user->id);
        }

        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('categoria_general_id')) {
            $query->where('categoria_general_id', $request->categoria_general_id);
        }

        $solicitudes = $query->with(['seguimientos', 'creadoPor', 'responsable', 'agencia'])->orderBy('id', 'desc')->paginate(20);

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

        if (!in_array('Super Admin', $roles) && !in_array('crear-solicitudes-tech', $permisos) && !in_array('crear-solicitudes-admin', $permisos)) {
            return response()->json(['message' => 'No tiene permiso para crear solicitudes'], 403);
        }

        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'categoria_general_id' => 'nullable|exists:solicitud_categorias_generales,id',
        ]);

        $solicitud = Solicitud::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'estado' => 'reportada',
            'subcategoria_id' => null,
            'categoria_general_id' => $request->categoria_general_id,
            'creado_por_id' => $user->id,
            'area' => $request->area ?? $user->area ?? null,
            'agencia_id' => $user->agencia_id ?? null,
        ]);

        // [NUEVO] Lógica de Notificaciones Centralizadas Cruzadas (Tickets Tecnológicos)
        if ($solicitud->categoria_general_id) {
            $categoria = \App\Models\SolicitudCategoriaGeneral::find($solicitud->categoria_general_id);

            // Verificación flexible e insensible a mayúsculas/acentos
            if ($categoria && stripos($categoria->nombre, 'tecnolog') !== false) {
                try {
                    $motherApiUrl = config('services.mother.api_url') ?? 'http://localhost:8000';
                    $serviceToken = config('services.mother.service_token') ?? 'token_secreto_yamankutx_notificaciones';

                    $descripcionSegura = strip_tags($solicitud->descripcion);
                    if (mb_strlen($descripcionSegura) > 1000) {
                        $descripcionSegura = mb_substr($descripcionSegura, 0, 995) . '...';
                    }
                    $mensajeCompleto = "El ticket #{$solicitud->id} '{$solicitud->titulo}' requiere atención técnica. Detalle: {$descripcionSegura}";

                    // Disparo HTTP asíncrono no-bloqueante con timeout ultra corto
                    \Illuminate\Support\Facades\Http::withHeaders([
                        'X-SSO-Service-Token' => $serviceToken,
                        'Accept' => 'application/json'
                    ])->timeout(2)->post("{$motherApiUrl}/api/sso/notifications/broadcast", [
                        'target_role' => 'Super Admin',
                        'title' => '¡Nuevo Ticket Tecnológico!',
                        'message' => $mensajeCompleto,
                        'app' => 'Tickets'
                    ]);
                } catch (\Exception $e) {
                    \Log::error("Error al emitir notificación de ticket tecnológico: " . $e->getMessage());
                }
            }
        }

        // Procesar evidencias y notificaciones en segundo plano (JOB)
        $tempFiles = [];
        if ($request->hasFile('evidencias')) {
            \Log::info("STORE DEBUG: Se recibieron " . count($request->file('evidencias')) . " archivos para subir a GCS.");

            $files = is_array($request->file('evidencias')) ? $request->file('evidencias') : [$request->file('evidencias')];
            foreach ($files as $file) {
                if ($file && $file->isValid() && $file->getSize() > 0) {
                    $originalName = $file->getClientOriginalName();
                    
                    $optimization = ImageOptimizer::optimizeForUpload($file, 1920, 70);
                    $filename = uniqid() . '_' . $optimization['name'];

                    // Guardar en almacenamiento LOCAL temporal (Milésimas de segundo)
                    if ($optimization['is_optimized']) {
                        $path = Storage::disk('local')->putFileAs('gestiones/temp', new \Illuminate\Http\File($optimization['path']), $filename);
                        @unlink($optimization['path']);
                    } else {
                        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $originalName); // Sanitize filename
                        $path = $file->storeAs('gestiones/temp', $filename, 'local');
                    }

                    if ($path) {
                        $tempFiles[] = [
                            'path' => $path,
                            'name' => $optimization['is_optimized'] ? $optimization['name'] : $originalName
                        ];
                    }
                }
            }
        } else {
            \Log::info("STORE DEBUG: No se recibieron archivos 'evidencias' en el request.");
        }

        // Despachar el Job a la cola. Pasamos la solicitud y los archivos temporales (si hay)
        \App\Jobs\ProcessSolicitudBackgroundTasks::dispatch($solicitud, $tempFiles);

        return response()->json($solicitud, 201);
    }

    /**
     * Ver detalle
     */
    public function show(Request $request, $id)
    {
        $solicitud = Solicitud::with(['seguimientos', 'creadoPor.puesto', 'responsable', 'agencia'])->findOrFail($id);
        $user = Auth::user();
        $roles = $user->roles ?? [];
        $permisos = $user->permisos ?? [];

        // Verificar autorización: Solo puede ver si:
        // 1. Es el creador de la solicitud
        // 2. Es el responsable asignado
        // 3. Es Super Admin
        // 4. Tiene permisos para asignar solicitudes (ya que debe verlas para asignarlas)
        $esCreador = $solicitud->creado_por_id == $user->id;
        $esResponsable = $solicitud->responsable_id == $user->id;
        $esSuperAdmin = in_array('Super Admin', $roles);
        $esAsignador = in_array('asignar_solicitudes-administrativas', $permisos) || in_array('asignar-solicitudes-tech', $permisos);

        if (!$esCreador && !$esResponsable && !$esSuperAdmin && !$esAsignador) {
            return response()->json([
                'message' => 'No tiene permisos para ver esta solicitud'
            ], 403);
        }

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

    /**
     * Asignar responsable (Jefe Informática)
     */
    public function assign(Request $request, $id)
    {
        $user = Auth::user();
        // $permisos...

        $request->validate([
            'responsable_id' => 'required_without:proveedor_id',
            'responsable_tipo' => 'required|in:interno,externo',
            'proveedor_id' => 'required_if:responsable_tipo,externo',
            'categoria_id' => 'required|integer|exists:solicitud_subcategorias,id',
            // eliminamos validaciones de nombre/email/cargo/tel
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $solicitud->update([
            'estado' => 'asignada',
            'responsable_id' => $request->responsable_id,
            // 'responsable_nombre' removed
            // 'responsable_email' removed
            'responsable_tipo' => $request->responsable_tipo,
            'proveedor_id' => $request->proveedor_id,
            'subcategoria_id' => $request->categoria_id,
            'fecha_asignacion' => Carbon::now(),
        ]);

        // Cargar relacion para obtener nombre
        $responsableNombre = $request->responsable_nombre ?? 'Desconocido';
        $responsableEmail = null;

        if ($request->responsable_id) {
            $responsableUser = \App\Models\User::find($request->responsable_id);
            if ($responsableUser) {
                $responsableNombre = $responsableUser->name;
                $responsableEmail = $responsableUser->email;
            }
        }

        SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $this->getPuestoNombre($user),
            'comentario' => "Solicitud asignada/reasignada a {$responsableNombre}",
            'tipo_accion' => 'comentario'
        ]);

        // Despachar notificación en segundo plano (Email o SMS según responsable_tipo)
        \App\Jobs\NotifySolicitudAsignadaJob::dispatch($solicitud);

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
             'categoria_id' => 'required|integer|exists:solicitud_subcategorias,id'
        ]);

        $solicitud = Solicitud::findOrFail($id);

        $puestoNombre = $request->responsable_cargo ?? $this->getPuestoNombre($user);

        $solicitud->update([
            'estado' => 'asignada',
            'responsable_id' => $user->id,
            'responsable_tipo' => 'interno',
            'subcategoria_id' => $request->categoria_id,
            // 'responsable_nombre', 'responsable_cargo' removed
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

        // Subida de evidencias a GCS (Smart Routing)
        $evidenciasPaths = [];
        $evidenciasInicialesNuevas = $solicitud->evidencias_inicial ?? [];
        $evidenciasFinalesNuevas = $solicitud->evidencias_final ?? [];
        $changedInicial = false;
        $changedFinal = false;

        if ($request->hasFile('evidencias')) {
            foreach ($request->file('evidencias') as $file) {
                // Determinar carpeta destino
                $folder = 'gestiones/solicitudes/seguimiento'; // Destino fallback
                $target = 'seguimiento';

                // Si es Creador -> Inicial
                if ($user->id == $solicitud->creado_por_id) {
                    $folder = 'gestiones/solicitudes/inicial';
                    $target = 'inicial';
                }
                // Si es Responsable -> Final
                elseif ($user->id == $solicitud->responsable_id) {
                    $folder = 'gestiones/solicitudes/final';
                    $target = 'final';
                }

                $optimization = ImageOptimizer::optimizeForUpload($file, 1920, 70);
                $filename = uniqid() . '_' . $optimization['name'];

                if ($optimization['is_optimized']) {
                    $path = Storage::disk($this->disk)->putFileAs($folder, new \Illuminate\Http\File($optimization['path']), $filename);
                    @unlink($optimization['path']);
                } else {
                    $filename = uniqid() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs($folder, $filename, $this->disk);
                }

                if ($path) {
                    $evidenciasPaths[] = $path; // Para el seguimiento (display en chat)

                    // Agregar a columnas principales
                    if ($target === 'inicial') {
                        $evidenciasInicialesNuevas[] = $path;
                        $changedInicial = true;
                    } elseif ($target === 'final') {
                        $evidenciasFinalesNuevas[] = $path;
                        $changedFinal = true;
                    }
                }
            }
        }

        // Guardar cambios en columnas principales
        if ($changedInicial) {
            $solicitud->evidencias_inicial = $evidenciasInicialesNuevas;
            $solicitud->save();
        }
        if ($changedFinal) {
            $solicitud->evidencias_final = $evidenciasFinalesNuevas;
            $solicitud->save();
        }

        $seguimiento = SolicitudSeguimiento::create([
            'solicitud_id' => $solicitud->id,
            'seguimiento_por_id' => $user->id,
            'seguimiento_por_nombre' => $user->name,
            'seguimiento_por_cargo' => $this->getPuestoNombre($user),
            'comentario' => $request->comentario,
            'tipo_accion' => $request->tipo_accion,
            'evidencias' => $evidenciasPaths // REFERENCIA al mismo archivo
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
        if (!empty($evidenciasPaths) || $request->tipo_accion === 'evidencia') {
             // NO cambiar estado automaticamente a pendiente_validacion solo por subir un archivo en chat,
             // a menos que sea explicitamente una "evidencia" de solucion?
             // El usuario dijo: "quien le asignaron... pueda seguir cargando archivos... van a pertenecer a evidencias finales".
             // PERO NO DIJO "Cerrar el caso".
             // MANTENEMOS LOGICA ACTUAL: Solo si tipo_accion es 'evidencia' (que usabamos para cerrar?)
             // Ojo: En frontend usabamos tipo_accion='evidencia' para cerrar.
             // Para chat normal usaremos 'comentario' con attached files.

             // Si el tipo de accion es 'comentario' pero trae archivos, NO cambiamos estado a pendiente_validacion.
        }

        // --- FIX: Si es 'evidencia' (Finalizar Caso), pasar a pendiente_validacion ---
        if ($request->tipo_accion === 'evidencia' && $solicitud->estado !== 'pendiente_validacion') {
            $solicitud->update([
                'estado' => 'pendiente_validacion'
            ]);

            // [NUEVO] Notificación Real-Time al Creador del Ticket para que valide el caso
            try {
                $motherApiUrl = config('services.mother.api_url') ?? 'http://localhost:8000';
                $serviceToken = config('services.mother.service_token') ?? 'token_secreto_yamankutx_notificaciones';

                \Illuminate\Support\Facades\Http::withHeaders([
                    'X-SSO-Service-Token' => $serviceToken,
                    'Accept' => 'application/json'
                ])->timeout(2)->post("{$motherApiUrl}/api/sso/notifications/broadcast", [
                    'target_user_id' => $solicitud->creado_por_id, // Dirigido directamente al creador
                    'title' => '¡Ticket Pendiente de Validar!',
                    'message' => "Tu ticket '{$solicitud->titulo}' está resuelto y listo para ser validado.",
                    'app' => 'Tickets'
                ]);
            } catch (\Exception $e) {
                \Log::error("Error al enviar notificación real-time de validación al creador {$solicitud->creado_por_id}: " . $e->getMessage());
            }

            // Send Notification Email
            // Send Notification Email
            if ($solicitud->creadoPor && $solicitud->creadoPor->email) {
                try {
                    \Log::info("Enviando correo de validación a creador: " . $solicitud->creadoPor->email);
                    Mail::to($solicitud->creadoPor->email)->send(new SolicitudPendienteValidacion($solicitud));
                    \Log::info("Correo de validación enviado exitosamente.");
                } catch (\Exception $e) {
                    \Log::error("Error enviando correo de validación: " . $e->getMessage());
                    \Log::error($e->getTraceAsString());
                }
            } else {
                \Log::warning("No se envió correo de validación: Creador no encontrado o sin email. Solicitud ID: " . $solicitud->id);
            }
        }
        // Separamos logica correo
        if ($request->tipo_accion === 'evidencia' || ($request->tipo_accion === 'comentario' && !empty($evidenciasPaths) && $solicitud->estado === 'pendiente_validacion')) {
             // Logic validation
        }

        // Transformar evidencias a URLs
        if (!empty($seguimiento->evidencias) && is_array($seguimiento->evidencias)) {
            $disk = Storage::disk($this->disk);
            $ttl = now()->addMinutes(20);
            $urls = array_map(function($path) use ($disk, $ttl) {
                try { return $disk->temporaryUrl($path, $ttl); } catch (\Exception $e) { return null; }
            }, $seguimiento->evidencias);
            $seguimiento->evidencias = $urls;
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

        // Notificar en tiempo real al responsable del seguimiento/atención del ticket
        if ($solicitud->responsable_id) {
            try {
                $motherApiUrl = config('services.mother.api_url') ?? 'http://localhost:8000';
                $serviceToken = config('services.mother.service_token') ?? 'token_secreto_yamankutx_notificaciones';

                $tituloNotif = $request->accion === 'cerrar' ? '¡Ticket Cerrado!' : 'Ticket Reabierto';
                $msgNotif = $request->accion === 'cerrar'
                    ? "El ticket #{$solicitud->id} '{$solicitud->titulo}' fue validado y cerrado por el usuario."
                    : "El ticket #{$solicitud->id} '{$solicitud->titulo}' fue reabierto por el usuario y requiere seguimiento.";

                \Illuminate\Support\Facades\Http::withHeaders([
                    'X-SSO-Service-Token' => $serviceToken,
                    'Accept' => 'application/json'
                ])->timeout(2)->post("{$motherApiUrl}/api/sso/notifications/broadcast", [
                    'target_user_id' => $solicitud->responsable_id, // Dirigido directamente al responsable del seguimiento
                    'title' => $tituloNotif,
                    'message' => $msgNotif,
                    'app' => 'Tickets'
                ]);
            } catch (\Exception $e) {
                \Log::error("Error al notificar cierre/reapertura al responsable {$solicitud->responsable_id}: " . $e->getMessage());
            }
        }

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


    public function getFileUrl(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|string|in:evidencia_inicial,evidencia_seguimiento',
            'index' => 'required|integer|min:0'
        ]);

        $solicitud = Solicitud::findOrFail($id);
        $path = null;

        if ($request->type === 'evidencia_inicial') {
            if ($solicitud->evidencias_inicial && isset($solicitud->evidencias_inicial[$request->index])) {
                $path = $solicitud->evidencias_inicial[$request->index];
            }
        } elseif ($request->type === 'evidencia_seguimiento') {
            // Logica para buscar en seguimientos (flatten)
            $allEvidencias = SolicitudSeguimiento::where('solicitud_id', $id)
                ->whereNotNull('evidencias')
                ->get()
                ->flatMap(function($seg) { return $seg->evidencias ?? []; });

            if (isset($allEvidencias[$request->index])) {
                $path = $allEvidencias[$request->index];
            }
        }

        if (!$path) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        try {
            $url = Storage::disk($this->disk)->temporaryUrl($path, now()->addMinutes(20));
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error generando URL'], 500);
        }
    }
    /**
     * Agregar evidencia suelta
     */
    public function addEvidence(Request $request, $id)
    {
        $user = Auth::user();
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No se recibió archivo'], 400);
        }

        $solicitud = Solicitud::findOrFail($id);

        if ($solicitud->estado === 'cerrada') {
            return response()->json(['error' => 'No se pueden agregar archivos a un caso cerrado.'], 403);
        }

        // Determinar destino basado en rol
        $target = null;
        $folder = '';

        // Si soy el creador -> evidencias_inicial
        if ($user->id == $solicitud->creado_por_id) {
            $target = 'evidencias_inicial';
            $folder = 'gestiones/solicitudes/inicial';
        }
        // Si soy el responsable -> evidencias_final
        elseif ($user->id == $solicitud->responsable_id) {
            $target = 'evidencias_final';
            $folder = 'gestiones/solicitudes/final';
        }
        // Si soy super admin y no soy ninguno de los anteriores? (Opcional, por ahora restringimos)
        elseif ($user->roles && in_array('Super Admin', $user->roles)) {
            // Admin decide donde? Por defecto final?
            $target = 'evidencias_final';
            $folder = 'gestiones/solicitudes/final';
        }
        else {
            return response()->json(['error' => 'No tienes permiso para agregar evidencias a este caso'], 403);
        }

        // Subir archivo
        $file = $request->file('file');
        
        $optimization = ImageOptimizer::optimizeForUpload($file, 1920, 70);
        $filename = uniqid() . '_' . $optimization['name'];

        if ($optimization['is_optimized']) {
            $path = Storage::disk($this->disk)->putFileAs($folder, new \Illuminate\Http\File($optimization['path']), $filename);
            @unlink($optimization['path']);
        } else {
            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs($folder, $filename, $this->disk);
        }

        if (!$path) {
            return response()->json(['error' => 'Error subiendo archivo a GCS'], 500);
        }

        // Actualizar array en DB
        $currentFiles = $solicitud->$target ?? [];
        $currentFiles[] = $path;

        // Eloquent/MySQL JSON cast a veces necesita ayuda para detectar cambio si es array
        $solicitud->$target = $currentFiles;
        $solicitud->save();

        // Generar URL firmada para devolver al front
        $url = Storage::disk($this->disk)->temporaryUrl($path, now()->addMinutes(20));

        // Registrar historial interno (opcional)
        SolicitudSeguimiento::create([
             'solicitud_id' => $solicitud->id,
             'seguimiento_por_id' => $user->id,
             'seguimiento_por_nombre' => $user->name,
             'seguimiento_por_cargo' => $this->getPuestoNombre($user),
             'tipo_accion' => 'evidencia', // Icono de clip
             'comentario' => "Adjuntó archivo: " . $file->getClientOriginalName(),
             'evidencias' => [$path] // Tambien lo agregamos al history para que salga en el chat?
             // EL USUARIO PIDIO: "asi identificaremos quien subio cada archivo"
             // Si lo metemos aqui tambien, se duplicara en la galeria si la galeria lee ambos?
             // MI PLAN ERA: Galeria lee Inicial + Final + Seguimientos.
             // Si lo agrego aqui, saldra duplicado.
             // PERO si no lo agrego aqui, no saldra en el chat como "Juan subio un archivo".
             // DECISION: Lo agrego al historial SIN evidencias[], solo como texto informativo?
             // O mejor: NO lo agrego al historial, el usuario vera el archivo en la pestaña "Archivos".
             // El usuario dijo: "permitamos que el ususario... pueda seguir cargando archivos... van a pertenecer a evidencias finales".
        ]);

        return response()->json([
            'message' => 'Archivo agregado',
            'path' => $path,
            'url' => $url,
            'target' => $target
        ]);
    }

    /**
     * Eliminar evidencia suelta
     */
    public function deleteEvidence(Request $request, $id)
    {
        $user = Auth::user();
        $request->validate([
            'path' => 'required|string'
        ]);

        $pathToDelete = $request->path;
        $solicitud = Solicitud::findOrFail($id);

        if ($solicitud->estado === 'cerrada') {
            return response()->json(['error' => 'No se pueden eliminar archivos de un caso cerrado.'], 403);
        }

        // Buscar en evidencias_inicial
        $found = false;
        $target = '';

        $iniciales = $solicitud->evidencias_inicial ?? [];
        if (in_array($pathToDelete, $iniciales)) {
            // Verificar permiso: Creador o Admin
            if ($user->id != $solicitud->creado_por_id && !in_array('Super Admin', $user->roles ?? [])) {
                return response()->json(['error' => 'No tienes permiso para eliminar este archivo'], 403);
            }
            $target = 'evidencias_inicial';
            $found = true;
        }

        // Buscar en evidencias_final
        $finales = $solicitud->evidencias_final ?? [];
        if (!$found && in_array($pathToDelete, $finales)) {
             // Verificar permiso: Responsable o Admin
             if ($user->id != $solicitud->responsable_id && !in_array('Super Admin', $user->roles ?? [])) {
                return response()->json(['error' => 'No tienes permiso para eliminar este archivo'], 403);
            }
            $target = 'evidencias_final';
            $found = true;
        }

        if (!$found) {
            // Podria ser de un Seguimiento antiguo?
            // "al igual que quien le asignaron o tomo el caso que puede adjuntar archivos"
            // Por ahora solo manejo Inicial y Final como pide el usuario.
            return response()->json(['error' => 'Archivo no encontrado en las listas principales'], 404);
        }

        // Eliminar de GCS
        try {
            Storage::disk($this->disk)->delete($pathToDelete);
        } catch (\Exception $e) {
            \Log::error("Error eliminando de GCS: " . $e->getMessage());
            // Continuamos para limpiar la DB aunque falle GCS
        }

        // Eliminar de DB
        $array = $solicitud->$target;
        $array = array_values(array_diff($array, [$pathToDelete])); // Re-indexar
        $solicitud->$target = $array;
        $solicitud->save();

        // --- SYNC: Eliminar también del historial (SolicitudSeguimiento) ---
        // Para evitar "imágenes rotas" en el chat.
        \Log::info("SYNC DELETE: Iniciando sync para path: $pathToDelete");
        $seguimientos = SolicitudSeguimiento::where('solicitud_id', $id)->get();
        foreach ($seguimientos as $seg) {
            if (!empty($seg->evidencias) && is_array($seg->evidencias)) {
                \Log::info("SYNC DELETE: Revisando seguimiento {$seg->id} con evidencias: " . json_encode($seg->evidencias));
                if (in_array($pathToDelete, $seg->evidencias)) {
                    \Log::info("SYNC DELETE: Encontrado en seguimiento {$seg->id}. Eliminando...");
                    $newEvidencias = array_values(array_diff($seg->evidencias, [$pathToDelete]));
                    $seg->evidencias = $newEvidencias;
                    $seg->save();
                    \Log::info("SYNC DELETE: Guardado seguimiento {$seg->id}. Nuevas evidencias: " . json_encode($newEvidencias));
                }
            }
        }

        return response()->json(['message' => 'Archivo eliminado correctamente']);
    }
    /**
     * Eliminar solicitud (DB y GCS)
     */
    public function destroy($id)
    {
        $user = Auth::user();

        // Reglas de seguridad:
        // Solo Super Admin puede eliminar CUALQUIER solicitud.
        // Opcional: El Creador podria eliminar si aun esta en estado "reportada" (pendiente).
        // Por safety, vamos a permitir solo a Super Admin por ahora, o al creador si estado=reportada.

        $solicitud = Solicitud::findOrFail($id);

        $esSuperAdmin = in_array('Super Admin', $user->roles ?? []);
        $esCreador = $solicitud->creado_por_id == $user->id;
        $esReportada = $solicitud->estado == 'reportada';

        if (!$esSuperAdmin) {
            if (!($esCreador && $esReportada)) {
                return response()->json(['message' => 'No tiene permiso para eliminar esta solicitud.'], 403);
            }
        }

        // 1. Recolectar todos los archivos para eliminar de GCS
        $filesToDelete = [];

        // Inicial
        if (!empty($solicitud->evidencias_inicial)) {
            $filesToDelete = array_merge($filesToDelete, $solicitud->evidencias_inicial);
        }
        // Final
        if (!empty($solicitud->evidencias_final)) {
            $filesToDelete = array_merge($filesToDelete, $solicitud->evidencias_final);
        }
        // Seguimientos
        $seguimientos = SolicitudSeguimiento::where('solicitud_id', $id)->get();
        foreach ($seguimientos as $seg) {
            if (!empty($seg->evidencias) && is_array($seg->evidencias)) {
                $filesToDelete = array_merge($filesToDelete, $seg->evidencias);
            }
        }

        // 2. Eliminar de GCS
        if (count($filesToDelete) > 0) {
            try {
                Storage::disk($this->disk)->delete($filesToDelete);
                \Log::info("Solicitud Delete: Se eliminaron " . count($filesToDelete) . " archivos de GCS.");
            } catch (\Exception $e) {
                \Log::error("Solicitud Delete Error GCS: " . $e->getMessage());
                // Continuamos para eliminar el registro de DB de todas formas
            }
        }

        // 3. Eliminar Seguimientos
        $solicitud->seguimientos()->delete();

        // 4. Eliminar Solicitud
        $solicitud->delete();

        return response()->json(['message' => 'Solicitud eliminada correctamente, incluidos sus archivos.'], 200);
    }
    /**
     * Actualizar Agencia (Solo si reportada)
     */
    public function updateAgencia(Request $request, $id)
    {
        $user = Auth::user();
        $solicitud = Solicitud::findOrFail($id);

        if ($solicitud->estado !== 'reportada') {
            return response()->json(['message' => 'Solo se puede cambiar la agencia en estado reportada.'], 403);
        }

        $request->validate([
            'agencia_id' => 'required|exists:agencias,id'
        ]);

        $oldAgenciaId = $solicitud->agencia_id;
        $solicitud->agencia_id = $request->agencia_id;
        $solicitud->save();

        return response()->json($solicitud->load('agencia'));
    }

    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        $query = Solicitud::query();

        // Aplicar los mismos filtros que en el index
        if ($request->has('mis_asignaciones') && $request->mis_asignaciones == 'true') {
            $query->where('responsable_id', $user->id);
        }

        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('categoria_general_id')) {
            $query->where('categoria_general_id', $request->categoria_general_id);
        }

        $solicitudes = $query->with(['creadoPor', 'responsable', 'agencia', 'subcategoria', 'categoriaGeneral', 'seguimientos'])->orderBy('id', 'desc')->get();

        $callback = function() use ($solicitudes) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8 (Excel friendly)
            
            // Encabezados
            fputcsv($file, [
                'ID',
                'Fecha Creacion',
                'Fecha Finalizacion',
                'Tiempo Transcurrido',
                'Titulo',
                'Estado',
                'Agencia',
                'Area/Ubicacion',
                'Creador/Solicitante',
                'Responsable Asignado',
                'Participantes de Seguimiento',
                'Categoria General',
                'Subcategoria',
                'Descripcion'
            ]);

            foreach ($solicitudes as $sol) {
                $fechaTermino = null;
                $tiempoTranscurrido = 'En proceso';
                
                if ($sol->estado === 'cerrada') {
                    $ultimoSeg = $sol->seguimientos
                        ->whereIn('tipo_accion', ['validacion', 'cierre'])
                        ->sortByDesc('created_at')
                        ->first();
                        
                    $fechaTermino = $ultimoSeg ? $ultimoSeg->created_at : $sol->updated_at;
                    
                    if ($fechaTermino) {
                        $diff = $sol->created_at->diff($fechaTermino);
                        $partes = [];
                        if ($diff->d > 0) {
                            $partes[] = $diff->d . 'd';
                        }
                        if ($diff->h > 0 || $diff->d > 0) {
                            $partes[] = $diff->h . 'h';
                        }
                        $partes[] = $diff->i . 'm';
                        $tiempoTranscurrido = implode(' ', $partes);
                    }
                }

                $participantes = $sol->seguimientos
                    ->pluck('seguimiento_por_nombre')
                    ->filter()
                    ->unique()
                    ->implode(', ');

                fputcsv($file, [
                    $sol->id,
                    $sol->created_at ? $sol->created_at->format('d/m/Y H:i') : 'N/A',
                    $fechaTermino ? $fechaTermino->format('d/m/Y H:i') : 'N/A',
                    $tiempoTranscurrido,
                    $sol->titulo,
                    ucwords(str_replace('_', ' ', $sol->estado)),
                    $sol->agencia->nombre ?? 'N/A',
                    $sol->area ?? 'N/A',
                    $sol->creadoPor->name ?? 'N/A',
                    $sol->responsable->name ?? 'N/A',
                    $participantes ?: 'Ninguno',
                    $sol->categoriaGeneral->nombre ?? 'N/A',
                    $sol->subcategoria->nombre ?? 'N/A',
                    $sol->descripcion
                ]);
            }
            fclose($file);
        };

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_solicitudes_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream($callback, 200, $headers);
    }

    public function exportGeneralCsv(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];
        
        if (!in_array('Super Admin', $roles)) {
            return response()->json(['message' => 'No tiene permiso para exportar el reporte general'], 403);
        }

        $query = Solicitud::query();

        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('categoria_general_id') && $request->categoria_general_id) {
            $query->where('categoria_general_id', $request->categoria_general_id);
        }

        if ($request->has('fecha_inicio') && $request->fecha_inicio) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->has('fecha_fin') && $request->fecha_fin) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $solicitudes = $query->with(['creadoPor', 'responsable', 'agencia', 'subcategoria', 'categoriaGeneral', 'seguimientos'])->orderBy('id', 'desc')->get();

        $callback = function() use ($solicitudes) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8 (Excel friendly)
            
            fputcsv($file, [
                'ID',
                'Fecha Creacion',
                'Fecha Finalizacion',
                'Tiempo Transcurrido',
                'Titulo',
                'Estado',
                'Agencia',
                'Area/Ubicacion',
                'Creador/Solicitante',
                'Responsable Asignado',
                'Participantes de Seguimiento',
                'Categoria General',
                'Subcategoria',
                'Descripcion'
            ]);

            foreach ($solicitudes as $sol) {
                $fechaTermino = null;
                $tiempoTranscurrido = 'En proceso';
                
                if ($sol->estado === 'cerrada') {
                    $ultimoSeg = $sol->seguimientos
                        ->whereIn('tipo_accion', ['validacion', 'cierre'])
                        ->sortByDesc('created_at')
                        ->first();
                        
                    $fechaTermino = $ultimoSeg ? $ultimoSeg->created_at : $sol->updated_at;
                    
                    if ($fechaTermino) {
                        $diff = $sol->created_at->diff($fechaTermino);
                        $partes = [];
                        if ($diff->d > 0) {
                            $partes[] = $diff->d . 'd';
                        }
                        if ($diff->h > 0 || $diff->d > 0) {
                            $partes[] = $diff->h . 'h';
                        }
                        $partes[] = $diff->i . 'm';
                        $tiempoTranscurrido = implode(' ', $partes);
                    }
                }

                $participantes = $sol->seguimientos
                    ->pluck('seguimiento_por_nombre')
                    ->filter()
                    ->unique()
                    ->implode(', ');

                fputcsv($file, [
                    $sol->id,
                    $sol->created_at ? $sol->created_at->format('d/m/Y H:i') : 'N/A',
                    $fechaTermino ? $fechaTermino->format('d/m/Y H:i') : 'N/A',
                    $tiempoTranscurrido,
                    $sol->titulo,
                    ucwords(str_replace('_', ' ', $sol->estado)),
                    $sol->agencia->nombre ?? 'N/A',
                    $sol->area ?? 'N/A',
                    $sol->creadoPor->name ?? 'N/A',
                    $sol->responsable->name ?? 'N/A',
                    $participantes ?: 'Ninguno',
                    $sol->categoriaGeneral->nombre ?? 'N/A',
                    $sol->subcategoria->nombre ?? 'N/A',
                    $sol->descripcion
                ]);
            }
            fclose($file);
        };

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_general_solicitudes_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream($callback, 200, $headers);
    }
}


