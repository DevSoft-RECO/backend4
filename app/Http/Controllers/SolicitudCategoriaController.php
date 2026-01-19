<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCategoria;
use Illuminate\Http\Request;

class SolicitudCategoriaController extends Controller
{
    /**
     * Listar categorias (Activas por defecto, o todas si se pide)
     */
    public function index(Request $request)
    {
        // Middleware 'sso' ya valida al usuario
        $query = SolicitudCategoria::query();

        if ($request->boolean('solo_activas', true)) {
            $query->where('activa', true);
        }

        return response()->json($query->get());
    }

    /**
     * Store (Admin/Jefe only - check permissions in real app)
     */
    public function store(Request $request)
    {
        // TODO: Validate strict permissions if needed (e.g. 'categorias.crear')

        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string'
        ]);

        $categoria = SolicitudCategoria::create($request->all());
        return response()->json($categoria, 201);
    }

    /**
     * Update
     */
    public function update(Request $request, $id)
    {
        $categoria = SolicitudCategoria::findOrFail($id);
        $categoria->update($request->all());

        return response()->json($categoria);
    }

    /**
     * Delete (Soft or Hard)
     */
    public function destroy(Request $request, $id)
    {
        $categoria = SolicitudCategoria::findOrFail($id);

        // Check usage before delete? For now simple delete.
        $categoria->delete();

        return response()->json(['message' => 'Eliminada']);
    }
}
