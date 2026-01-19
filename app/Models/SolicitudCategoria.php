<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudCategoria extends Model
{
    use HasFactory;

    protected $table = 'solicitud_categorias';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activa'
    ];

    protected $casts = [
        'activa' => 'boolean'
    ];
}
