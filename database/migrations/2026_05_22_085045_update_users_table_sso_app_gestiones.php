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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'roles_list')) {
                $table->text('roles_list')->nullable()->after('agencia_id');
            }
            if (!Schema::hasColumn('users', 'permissions_list')) {
                $table->text('permissions_list')->nullable()->after('roles_list');
            }
            if (!Schema::hasColumn('users', 'jti')) {
                $table->string('jti')->nullable()->after('permissions_list');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('jti');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['roles_list', 'permissions_list', 'jti', 'avatar']);
        });
    }
};
