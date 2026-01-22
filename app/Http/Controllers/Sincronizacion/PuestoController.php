<?php

namespace App\Http\Controllers\Sincronizacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Puesto;

class PuestoController extends Controller
{
    /**
     * Obtener lista de puestos locales.
     */
    public function index()
    {
        // Retornamos todos los puestos locales ordenados por nombre
        return response()->json(Puesto::orderBy('nombre')->get());
    }
}
