<?php

namespace App\Http\Controllers\Sincronizacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agencia;

class AgenciaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retornamos todas las agencias locales
        return response()->json(Agencia::all());
    }
}
