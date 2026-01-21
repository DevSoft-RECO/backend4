<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudSubcategoria extends Model
{
    use HasFactory;

    protected $table = 'solicitud_subcategorias';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activa',
        'categoria_general_id'
    ];

    protected $casts = [
        'activa' => 'boolean'
    ];

    public function categoriaGeneral()
    {
        return $this->belongsTo(SolicitudCategoriaGeneral::class, 'categoria_general_id');
    }
}
