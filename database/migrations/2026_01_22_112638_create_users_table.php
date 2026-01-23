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
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // ID viene de la App Madre
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedBigInteger('puesto_id')->nullable();
            $table->unsignedBigInteger('agencia_id')->nullable();
            $table->timestamps();

            $table->foreign('puesto_id')->references('id')->on('puestos')->nullOnDelete();
            $table->foreign('agencia_id')->references('id')->on('agencias')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
