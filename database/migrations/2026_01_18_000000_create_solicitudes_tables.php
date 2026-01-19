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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion');
            // Estados permitidos: reportada, asignada, en_seguimiento, pendiente_validacion, cerrada, reabierta
            $table->string('estado')->default('reportada');

            // Snapshot del creador (tomado del token)
            $table->unsignedBigInteger('creado_por_id');
            $table->string('creado_por_nombre');
            $table->string('creado_por_cargo')->nullable();

            // Contexto
            $table->unsignedBigInteger('agencia_id');
            $table->unsignedBigInteger('categoria_id')->nullable();

            // Responsable (Asignado o Auto-asignado)
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->string('responsable_nombre')->nullable();
            $table->string('responsable_cargo')->nullable();
            $table->enum('responsable_tipo', ['interno', 'externo'])->nullable();

            // En caso de ser externo
            $table->unsignedBigInteger('proveedor_id')->nullable();

            // Fechas de gestión
            $table->timestamp('fecha_asignacion')->nullable();
            $table->timestamp('fecha_toma_caso')->nullable();

            $table->timestamps();
        });

        Schema::create('solicitud_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->onDelete('cascade');

            // Snapshot del autor del seguimiento
            $table->unsignedBigInteger('seguimiento_por_id');
            $table->string('seguimiento_por_nombre');
            $table->string('seguimiento_por_cargo')->nullable();

            $table->text('comentario')->nullable();
            // Tipo de acción: visita, comentario, evidencia, validacion, reapertura
            $table->string('tipo_accion');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_seguimientos');
        Schema::dropIfExists('solicitudes');
    }
};
