<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'username',
        'name',
        'email',
        'telefono',
        'puesto_id',
        'agencia_id',
        'updated_at' // Permitir actualización explícita si es necesario
    ];

    public function puesto()
    {
        return $this->belongsTo(Puesto::class);
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class);
    }
}
