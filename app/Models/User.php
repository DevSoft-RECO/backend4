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
        'roles_list',
        'permissions_list',
        'jti',
        'avatar',
        'updated_at' // Permitir actualización explícita si es necesario
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'roles_list' => 'array',
            'permissions_list' => 'array',
        ];
    }

    // --- Helpers de Autorización Rápidos ---

    public function hasRole($role) {
        if (!is_array($this->roles_list)) return false;
        return in_array($role, $this->roles_list);
    }

    public function hasPermissionTo($permission) {
        if ($this->hasRole('Super Admin')) return true;
        if (!is_array($this->permissions_list)) return false;
        return in_array($permission, $this->permissions_list);
    }

    public function puesto()
    {
        return $this->belongsTo(Puesto::class);
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class);
    }

    // --- Compatibilidad con Laravel Auth ---

    public function tokenCan($ability)
    {
        return $this->hasPermissionTo($ability);
    }

    public function currentAccessToken()
    {
        return null;
    }
}
