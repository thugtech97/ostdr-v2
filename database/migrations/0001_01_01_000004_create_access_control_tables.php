<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mirrors the legacy PMC-OSTR roles/permissions/satellites schema. The tables
     * already exist in a copy of the legacy database, so each one is only created if missing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 191);
                $table->timestamps();
                $table->boolean('active')->default(true);
                $table->string('description', 191)->default('');
            });
        }

        if (! Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->increments('id');
                $table->string('description', 50)->nullable();
                $table->bigInteger('app_module_id')->nullable();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('description', 191);
                $table->string('module_type', 191);
                $table->timestamps();
                $table->boolean('active')->default(true);
            });
        }

        if (! Schema::hasTable('roles_permissions')) {
            Schema::create('roles_permissions', function (Blueprint $table) {
                $table->bigInteger('role_id');
                $table->bigInteger('permission_id');
                $table->bigInteger('module_id')->nullable()->default(0);
                $table->string('action', 10)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users_permissions')) {
            Schema::create('users_permissions', function (Blueprint $table) {
                $table->bigInteger('user_id');
                $table->bigInteger('permission_id');
                $table->bigInteger('module_id')->nullable()->default(0);
                $table->string('action', 10)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('satellites')) {
            Schema::create('satellites', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 191);
                $table->timestamps();
                $table->boolean('active')->default(true);
                $table->string('description', 191)->default('');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty: these tables hold legacy data and must never be dropped by a rollback.
    }
};
