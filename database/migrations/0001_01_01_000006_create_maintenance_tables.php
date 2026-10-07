<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy tables behind Application Maintenance (scheduled shutdowns) and Application Error
 * Logs. The shared database already has them; this only creates them on a fresh database.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('scheduled_date')->nullable();
                $table->time('scheduled_time')->nullable();
                $table->string('reason', 191)->nullable();
                $table->date('posted_date')->nullable();
                $table->time('posted_time')->nullable();
                $table->timestamps();
                $table->dateTime('deleted_at')->nullable();
                $table->string('server_name', 100)->nullable();
            });
        }

        if (! Schema::hasTable('log')) {
            Schema::create('log', function (Blueprint $table) {
                $table->increments('id');
                $table->text('message')->nullable();
                $table->string('channel', 191)->nullable();
                $table->integer('level')->default(0);
                $table->string('level_name', 20)->nullable();
                $table->integer('unix_time')->nullable();
                $table->string('datetime', 191)->nullable();
                $table->text('context')->nullable();
                $table->text('extra')->nullable();
                $table->timestamps();
                $table->integer('userid')->nullable();
                $table->string('username', 50)->nullable();
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
