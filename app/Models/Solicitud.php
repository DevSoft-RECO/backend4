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
        'creado_por_cargo',
        'agencia_id',
        'categoria_id',
        'responsable_id',
        'responsable_nombre',
        'responsable_cargo',
        'responsable_tipo', // interno, externo
        'proveedor_id',
        'fecha_asignacion',
        'fecha_toma_caso',
    ];

    protected $casts = [
        'fecha_asignacion' => 'datetime',
        'fecha_toma_caso' => 'datetime',
    ];

    public function seguimientos()
    {
        return $this->hasMany(SolicitudSeguimiento::class, 'solicitud_id');
    }
}
