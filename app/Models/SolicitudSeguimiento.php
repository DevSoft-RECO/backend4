<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudSeguimiento extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudSeguimientoFactory> */
    use HasFactory;

    protected $table = 'solicitud_seguimientos';

    protected $fillable = [
        'solicitud_id',
        'seguimiento_por_id',
        'seguimiento_por_nombre',
        'seguimiento_por_cargo',
        'comentario',
        'evidencias',
        'tipo_accion', // visita, comentario, evidencia, validacion, reapertura
    ];

    protected $casts = [
        'evidencias' => 'array',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }
}
