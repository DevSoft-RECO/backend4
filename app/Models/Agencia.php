<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agencia extends Model
{
    protected $fillable = [
        'agencia_madre_id',
        'codigo',
        'nombre',
        'direccion'
    ];
}
