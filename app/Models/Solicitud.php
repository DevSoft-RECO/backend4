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
        'area',
        'agencia_id',
        'subcategoria_id',
        'categoria_general_id',
        'responsable_id',
        'responsable_tipo', // interno, externo
        'proveedor_id',
        'fecha_asignacion',
        'fecha_toma_caso',
        'evidencias_inicial',
        'evidencias_final',
        'tipo_solucion',
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

    public function subcategoria()
    {
        return $this->belongsTo(SolicitudSubcategoria::class, 'subcategoria_id');
    }

    public function categoriaGeneral()
    {
        return $this->belongsTo(SolicitudCategoriaGeneral::class, 'categoria_general_id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }
}
