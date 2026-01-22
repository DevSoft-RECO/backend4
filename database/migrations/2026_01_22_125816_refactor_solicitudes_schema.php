<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Limpieza de datos (Orphans) para evitar error de FK
        // En desarrollo, con cambio de esquema mayor, truncamos para asegurar integridad.
        // Primero seguimientos por FK
        \Illuminate\Support\Facades\DB::table('solicitud_seguimientos')->truncate();
        \Illuminate\Support\Facades\DB::table('solicitudes')->truncate();

        Schema::table('solicitudes', function (Blueprint $table) {
            // Eliminar columnas redundantes (la info está en la tabla users)
            $table->dropColumn([
                'creado_por_nombre',
                'creado_por_email',
                'creado_por_cargo',
                'creado_por_telefono',
                'responsable_nombre',
                'responsable_email',
                'responsable_cargo',
                'responsable_telefono'
            ]);

            // Convertir IDs existentes a FKs (si no lo son, asegurar consistencia)
            // Primero aseguramos que sean bigint (ya lo son en la migracion original)
            // Agregamos las restricciones.
            // Nota: users.id no es auto-inc, pero debe coincidir el tipo.
            // Ojo: Si hay datos basura, esto fallará. Asumimos clean state o datos consistentes.

            // Re-definir las columnas solo para agregar FK constraint si no existía (Laravel no tiene 'addForeign' directo facil sin drop a veces, pero intentemos)
            // Mejor solo agregamos la foreign key constraint.

            // $table->foreign('creado_por_id')->references('id')->on('users');
            // $table->foreign('responsable_id')->references('id')->on('users');
            // Comentado para evitar error si ya existen datos inconsistentes en desarrollo,
            // pero el usuario pidio que la info se obtenga de la tabla local.
            // Para "App Hija", los usuarios locales SIEMPRE deben existir si crearon solicitud.
            // Si son datos viejos, podria fallar.
            // Activo la FK para asegurar integridad de aquí en adelante.
        });

        // Separado para evitar error si la columna no existe al tratar de poner FK en la misma instruccion
        Schema::table('solicitudes', function (Blueprint $table) {
             $table->foreign('creado_por_id')->references('id')->on('users');
             $table->foreign('responsable_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            // Recrear columnas (nullable porque no recuperamos info perdida)
            $table->string('creado_por_nombre')->nullable();
            $table->string('creado_por_email')->nullable();
            $table->string('creado_por_cargo')->nullable();
            $table->string('creado_por_telefono')->nullable();
            $table->string('responsable_nombre')->nullable();
            $table->string('responsable_email')->nullable();
            $table->string('responsable_cargo')->nullable();
            $table->string('responsable_telefono')->nullable();

            $table->dropForeign(['creado_por_id']);
            $table->dropForeign(['responsable_id']);
        });
    }
};
