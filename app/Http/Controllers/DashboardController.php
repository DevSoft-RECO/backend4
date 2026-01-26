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

        // 1. Determine Scope based on Permissions
        // Priority: General > Agencia > None (403)
        $canViewGeneral = in_array('Super Admin', $roles) || in_array('ver-dashboard-general', $permisos);
        $canViewAgencia = in_array('ver-dashboard-agencia', $permisos) || in_array('dashboard-solo-lectura', $permisos);

        // Scope Defaults
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
        if (!$scopeAgenciaId) {
            $agencyQuery = clone $query;
            $agencyDistro = $agencyQuery->join('agencias', 'solicitudes.agencia_id', '=', 'agencias.id')
                ->select('agencias.nombre', DB::raw('count(*) as count'))
                ->groupBy('agencias.nombre')
                ->orderByDesc('count')
                ->limit(10)
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

        // 4. Return Data
        return response()->json([
            'kpi' => [
                'total' => $stats->total,
                'open' => $stats->abiertas,
                'validation' => $stats->validacion,
                'closed' => $stats->cerradas
            ],
            'charts' => [
                'status' => $statusDistro,
                'subcategories' => $subcatDistro,
                'agencies' => $agencyDistro
            ],
            'leaderboard' => $leaderboard,
            'permissions' => [
                'can_drill_down' => !in_array('dashboard-solo-lectura', $permisos),
                'scope' => $scopeAgenciaId ? 'agency' : 'general'
            ]
        ]);
    }
}
