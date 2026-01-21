<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCategoriaGeneral;
use Illuminate\Http\Request;

class SolicitudCategoriaGeneralController extends Controller
{
    /**
     * Listar categorias generales
     */
    public function index(Request $request)
    {
        $query = SolicitudCategoriaGeneral::query();

        if ($request->boolean('solo_activas', true)) {
            $query->where('activo', true);
        }

        return response()->json($query->get());
    }

    /**
     * Store
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string'
        ]);

        $categoria = SolicitudCategoriaGeneral::create($request->all());
        return response()->json($categoria, 201);
    }

    /**
     * Update
     */
    public function update(Request $request, $id)
    {
        $categoria = SolicitudCategoriaGeneral::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string'
        ]);

        $categoria->update($request->all());

        return response()->json($categoria);
    }

    /**
     * Destroy
     */
    public function destroy($id)
    {
        $categoria = SolicitudCategoriaGeneral::findOrFail($id);
        // Validar si tiene subcategorias asociadas?
        if ($categoria->subcategorias()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar porque tiene subcategorías asociadas'], 409);
        }

        $categoria->delete();
        return response()->json(['message' => 'Eliminada correctamente']);
    }
}
