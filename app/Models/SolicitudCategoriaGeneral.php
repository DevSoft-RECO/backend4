<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudCategoriaGeneral extends Model
{
    use HasFactory;

    protected $table = 'solicitud_categorias_generales';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function subcategorias()
    {
        return $this->hasMany(SolicitudSubcategoria::class, 'categoria_general_id');
    }
}
