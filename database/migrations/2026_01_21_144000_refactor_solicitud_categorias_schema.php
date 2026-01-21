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
        // 1. Rename existing table
        if (Schema::hasTable('solicitud_categorias')) {
            Schema::rename('solicitud_categorias', 'solicitud_subcategorias');
        }

        // 2. Create General Categories table
        Schema::create('solicitud_categorias_generales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Update Subcategories table
        Schema::table('solicitud_subcategorias', function (Blueprint $table) {
            // Add FK to general category
            $table->foreignId('categoria_general_id')
                  ->nullable() // Nullable initially to avoid breaking existing data
                  ->constrained('solicitud_categorias_generales')
                  ->onDelete('set null');
        });

        // 4. Update Solicitudes table
        Schema::table('solicitudes', function (Blueprint $table) {
            // Rename FK column
            if (Schema::hasColumn('solicitudes', 'categoria_id')) {
                $table->renameColumn('categoria_id', 'subcategoria_id');
            }

            // Add FK to general category (direct access)
            $table->foreignId('categoria_general_id')
                  ->nullable()
                  ->after('agencia_id') // Try to place it reasonably
                  ->constrained('solicitud_categorias_generales')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse 4
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropForeign(['categoria_general_id']);
            $table->dropColumn('categoria_general_id');
            $table->renameColumn('subcategoria_id', 'categoria_id');
        });

        // Reverse 3
        Schema::table('solicitud_subcategorias', function (Blueprint $table) {
            $table->dropForeign(['categoria_general_id']);
            $table->dropColumn('categoria_general_id');
        });

        // Reverse 2
        Schema::dropIfExists('solicitud_categorias_generales');

        // Reverse 1
        if (Schema::hasTable('solicitud_subcategorias')) {
            Schema::rename('solicitud_subcategorias', 'solicitud_categorias');
        }
    }
};
