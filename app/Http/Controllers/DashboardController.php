<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get Dashboard Metrics
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];
        $permisos = $user->permisos ?? [];

        // 1. Determine Scope & Interaction based on Permissions
        // Permissions:
        // - 'ver-dashboard-general': View All, Can Interact (unless restricted otherwise, but usually yes)
        // - 'ver-dashboard-agencia': View Own Agency, Can Interact
        // - 'dashboard-solo-lectura': View Own Agency, NO Interaction

        $isSuperAdmin = in_array('Super Admin', $roles);
        $hasGeneral  = in_array('ver-dashboard-general', $permisos);
        $hasAgencia  = in_array('ver-dashboard-agencia', $permisos);
        $hasSoloLectura = in_array('dashboard-solo-lectura', $permisos);

        // Access Level Logic
        $canViewGeneral = $isSuperAdmin || $hasGeneral;
        $canViewAgencia = $hasAgencia || $hasSoloLectura; // "Solo lectura" implies viewing agency data

        // Interaction Logic
        // Interaction is allowed if Super Admin, General, or Agency.
        // It is NOT allowed if the user ONLY has dashboard-solo-lectura (and none of the others).
        // However, if a user has BOTH (e.g. legacy or mess up), usually the more permissive wins?
        // User request says: "1: dashboard-solo-lectura ... botones se desactivan"
        // "2: ver-dashboard-agencia ... interaccion disponible"
        // So, if you have 'ver-dashboard-agencia', you CAN interact.
        $canInteract = $isSuperAdmin || $hasGeneral || $hasAgencia;

        // Scope Defaults
        $scopeAgenciaId = null;

        if ($canViewGeneral) {
            // Admin can filter by any agency, or see all (null)
            if ($request->has('agencia_id') && $request->agencia_id != 'null') {
                $scopeAgenciaId = $request->agencia_id;
            }
        } elseif ($canViewAgencia) {
            // Agency or Read-Only User is FORCED to their agency
            $scopeAgenciaId = $user->agencia_id;

            // Security check: User must have an agency_id to view agency dashboard
            if (!$scopeAgenciaId) {
                return response()->json(['message' => 'Usuario no tiene agencia asignada.'], 403);
            }
        } else {
            // No permissions at all
            return response()->json(['message' => 'No tiene permiso para ver el dashboard.'], 403);
        }

        // 2. Base Query Construction
        $query = Solicitud::query();

        // Filter by Agency Data Scope
        if ($scopeAgenciaId) {
            $query->where('solicitudes.agencia_id', $scopeAgenciaId);
        }

        // Filter by General Category (Tech vs Admin)
        // Default: All if not specified, but typically frontend sends one
        if ($request->has('category_id') && $request->category_id != 'null') {
            $query->where('solicitudes.categoria_general_id', $request->category_id);
        }

        // Filter by Date Range (Optional, future proofing)
        if ($request->has('date_start') && $request->has('date_end')) {
            $query->whereBetween('solicitudes.created_at', [$request->date_start, $request->date_end]);
        }

        // 3. Aggregate Metrics
        // We clone the query for different aggregations to respect the filters

        // KPI Counts
        $kpiQuery = clone $query;
        $stats = $kpiQuery->selectRaw("
            count(*) as total,
            sum(case when estado in ('reportada', 'asignada', 'en_seguimiento', 'reabierta') then 1 else 0 end) as abiertas,
            sum(case when estado = 'pendiente_validacion' then 1 else 0 end) as validacion,
            sum(case when estado = 'cerrada' then 1 else 0 end) as cerradas
        ")->first();

        // Chart: Status Distro
        $statusQuery = clone $query;
        $statusDistro = $statusQuery->select('estado', DB::raw('count(*) as count'))
            ->groupBy('estado')
            ->get();

        // Chart: Top Subcategories (Limit 5)
        $subcatQuery = clone $query;
        $subcatDistro = $subcatQuery->join('solicitud_subcategorias', 'solicitudes.subcategoria_id', '=', 'solicitud_subcategorias.id')
            ->select('solicitud_subcategorias.nombre', DB::raw('count(*) as count'))
            ->whereNotNull('subcategoria_id')
            ->groupBy('solicitud_subcategorias.nombre')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Chart: Top Agencies (Only if viewing All Agencies)
        $agencyDistro = [];
        $allAgencyDistro = []; // New full list
        if (!$scopeAgenciaId) {
            $agencyQuery = clone $query;
            $agencyDistro = $agencyQuery->join('agencias', 'solicitudes.agencia_id', '=', 'agencias.id')
                ->select('agencias.nombre', DB::raw('count(*) as count'))
                ->groupBy('agencias.nombre')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            // Full List for Bottom Chart
            $allAgencyQuery = clone $query;
            $allAgencyDistro = $allAgencyQuery->join('agencias', 'solicitudes.agencia_id', '=', 'agencias.id')
                ->select('agencias.nombre', DB::raw('count(*) as count'))
                ->groupBy('agencias.nombre')
                ->orderByDesc('count')
                // No Limit
                ->get();
        }

        // Agent Leaderboard (Most Closed Tickets)
        $agentQuery = clone $query;
        $leaderboard = $agentQuery->where('estado', 'cerrada')
            ->join('users', 'solicitudes.responsable_id', '=', 'users.id') // Ensure we join user info
            ->select('users.name', DB::raw('count(*) as closed_count'))
            ->groupBy('users.name')
            ->orderByDesc('closed_count')
            ->limit(5)
            ->get();

        // Resolution Stats
        $resolutionQuery = clone $query;
        $resolutionStats = $resolutionQuery->whereIn('tipo_solucion', ['total', 'parcial'])
             ->select('tipo_solucion', DB::raw('count(*) as count'))
             ->groupBy('tipo_solucion')
             ->get()
             ->pluck('count', 'tipo_solucion');

        // 4. Return Data
        return response()->json([
            'kpi' => [
                'total' => $stats->total,
                'open' => $stats->abiertas,
                'validation' => $stats->validacion,
                'closed' => $stats->cerradas
            ],
            'resolution' => [
                'total' => $resolutionStats['total'] ?? 0,
                'parcial' => $resolutionStats['parcial'] ?? 0
            ],
            'charts' => [
                'status' => $statusDistro,
                'subcategories' => $subcatDistro,
                'agencies' => $agencyDistro,
                'all_agencies' => $allAgencyDistro // New
            ],
            'leaderboard' => $leaderboard,
            'permissions' => [
                'can_interact' => $canInteract,
                'can_drill_down' => $canInteract, // Drill down is a form of interaction
                'scope' => $scopeAgenciaId ? 'agency' : 'general'
            ]
        ]);
    }

    /**
     * Get Detailed List of Resolved Requests
     */
    public function getResolutionDetails(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];
        $permisos = $user->permisos ?? [];

        // 1. Determine Scope based on Permissions
        // Priority: General > Agencia > None (403)
        $canViewGeneral = in_array('Super Admin', $roles) || in_array('ver-dashboard-general', $permisos);
        $canViewAgencia = in_array('ver-dashboard-agencia', $permisos) || in_array('dashboard-solo-lectura', $permisos);

        $scopeAgenciaId = null;

        if ($canViewGeneral) {
            // Admin can filter by any agency, or see all (null)
            if ($request->has('agencia_id') && $request->agencia_id != 'null') {
                $scopeAgenciaId = $request->agencia_id;
            }
        } elseif ($canViewAgencia) {
            // Agency User is FORCED to their agency
            $scopeAgenciaId = $user->agencia_id;

            // Security check: User must have an agency_id to view agency dashboard
            if (!$scopeAgenciaId) {
                return response()->json(['message' => 'Usuario no tiene agencia asignada.'], 403);
            }
        } else {
            return response()->json(['message' => 'No tiene permiso para ver el dashboard.'], 403);
        }

        // 2. Base Query Construction
        $query = Solicitud::query();

        // Filter by Agency Data Scope
        if ($scopeAgenciaId) {
            $query->where('solicitudes.agencia_id', $scopeAgenciaId);
        }

        // Filter by General Category (Tech vs Admin)
        if ($request->has('category_id') && $request->category_id != 'null') {
            $query->where('solicitudes.categoria_general_id', $request->category_id);
        }

        // Filter by Date Range
        if ($request->has('date_start') && $request->has('date_end')) {
            $query->whereBetween('solicitudes.created_at', [$request->date_start, $request->date_end]);
        }

        // 3. Handle Types
        $type = $request->input('type');

        if ($type === 'abiertas') {
            // Abiertas: reportada, asignada, en_seguimiento, reabierta
            $query->whereIn('estado', ['reportada', 'asignada', 'en_seguimiento', 'reabierta']);
            // Optimized Select
            return response()->json($query->select('id', 'titulo', 'created_at')->orderByDesc('created_at')->get());

        } elseif ($type === 'validacion') {
            // Por Validar check
            $query->where('estado', 'pendiente_validacion');
             // Optimized Select
            return response()->json($query->select('id', 'titulo', 'created_at')->orderByDesc('created_at')->get());

        } elseif (in_array($type, ['total', 'parcial'])) {
             // Existing Resolution Logic (Full Details)
            $query->where('tipo_solucion', $type);
            $requests = $query->with(['responsable:id,name', 'agencia:id,nombre', 'categoriaGeneral:id,nombre', 'subcategoria:id,nombre'])
                              ->orderByDesc('created_at')
                              ->get();
            return response()->json($requests);

        } else {
            // Default or if no valid type provided (maybe just resolutions?)
             $query->whereIn('tipo_solucion', ['total', 'parcial']);
             $requests = $query->with(['responsable:id,name', 'agencia:id,nombre', 'categoriaGeneral:id,nombre', 'subcategoria:id,nombre'])
                              ->orderByDesc('created_at')
                              ->get();
             return response()->json($requests);
        }
    }
}
