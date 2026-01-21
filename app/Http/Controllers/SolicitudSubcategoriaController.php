<?php

namespace App\Http\Controllers;

use App\Models\SolicitudSubcategoria;
use Illuminate\Http\Request;

class SolicitudSubcategoriaController extends Controller
{
    /**
     * Listar subcategorias (Activas por defecto, o todas si se pide)
     */
    public function index(Request $request)
    {
        // Middleware 'sso' ya valida al usuario
        $query = SolicitudSubcategoria::with('categoriaGeneral');

        if ($request->boolean('solo_activas', true)) {
            $query->where('activa', true);
        }

        if ($request->has('categoria_general_id')) {
            $query->where('categoria_general_id', $request->categoria_general_id);
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
            'descripcion' => 'nullable|string',
            'categoria_general_id' => 'required|exists:solicitud_categorias_generales,id'
        ]);

        $categoria = SolicitudSubcategoria::create($request->all());
        return response()->json($categoria, 201);
    }

    /**
     * Update
     */
    public function update(Request $request, $id)
    {
        $categoria = SolicitudSubcategoria::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria_general_id' => 'sometimes|exists:solicitud_categorias_generales,id'
        ]);

        $categoria->update($request->all());

        return response()->json($categoria);
    }

    /**
     * Delete (Soft or Hard)
     */
    public function destroy(Request $request, $id)
    {
        $categoria = SolicitudSubcategoria::findOrFail($id);

        // Check usage before delete? For now simple delete.
        $categoria->delete();

        return response()->json(['message' => 'Eliminada']);
    }
}
