<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudFactory> */
    use HasFactory;

    protected $table = 'solicitudes';

    protected $fillable = [
        'titulo',
        'descripcion',
        'estado',
        'creado_por_id',
        'creado_por_nombre',
        'creado_por_email',
        'creado_por_cargo',
        'area', // Nueva columna
        'creado_por_telefono', // Nueva columna
        'agencia_id',
        'categoria_id',
        'responsable_id',
        'responsable_nombre',
        'responsable_email',
        'responsable_telefono', // Nueva columna
        'responsable_cargo',
        'responsable_tipo', // interno, externo
        'proveedor_id',
        'fecha_asignacion',
        'fecha_toma_caso',
        'evidencias_inicial',
        'evidencias_final',
        'tipo_solucion', // total, parcial
    ];

    protected $casts = [
        'fecha_asignacion' => 'datetime',
        'fecha_toma_caso' => 'datetime',
        'evidencias_inicial' => 'array',
        'evidencias_final' => 'array',
    ];

    public function seguimientos()
    {
        return $this->hasMany(SolicitudSeguimiento::class, 'solicitud_id');
    }
}
