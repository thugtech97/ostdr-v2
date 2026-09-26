<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mirrors the legacy PMC-OSTR schema. The tables already exist when running
     * against a copy of the legacy database, so each one is only created if missing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 100)->nullable();
                $table->string('username', 100)->nullable();
                $table->string('password', 200)->nullable();
                $table->string('role', 100)->nullable();
                $table->string('dept', 100)->nullable();
                $table->integer('isActive')->nullable();
                $table->string('remember_token', 200)->nullable();
                $table->timestamps();
                $table->bigInteger('role_id')->nullable();
                $table->text('email')->nullable();
                $table->boolean('isLoggedIn')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
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
