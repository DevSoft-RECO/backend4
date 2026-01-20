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
        Schema::table('solicitudes', function (Blueprint $table) {
            // Email del responsable asignado (para notificaciones de asignación)
            $table->string('responsable_email')->nullable()->after('responsable_cargo');

            // Email del solicitante/creador (para notificaciones de validación)
            $table->string('creado_por_email')->nullable()->after('creado_por_cargo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropColumn(['responsable_email', 'creado_por_email']);
        });
    }
};
