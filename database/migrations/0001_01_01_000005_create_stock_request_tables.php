<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mirrors the legacy PMC-OSTR stock request schema. The tables already exist in
     * a copy of the legacy database, so each one is only created if missing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('stock_requests')) {
            Schema::create('stock_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('date_filed')->nullable();
                $table->time('time_filed')->nullable();
                $table->date('date_needed')->nullable();
                $table->string('dept', 255)->nullable();
                $table->string('cost_code', 100)->nullable();
                $table->string('remarks', 255)->nullable();
                $table->string('requested_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('created_by', 50)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('updated_by', 50)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->string('deleted_by', 50)->nullable();
                $table->string('status', 50)->nullable();
                $table->string('WFS_connection', 50)->nullable();
                $table->boolean('isSaved')->nullable()->default(false);
                $table->boolean('active')->nullable()->default(true);
                $table->string('transaction_no', 50)->nullable();
                $table->boolean('isReceived')->nullable();
                $table->string('received_by', 50)->nullable();
                $table->dateTime('received_at')->nullable();
                $table->string('origin', 255)->nullable();
                $table->string('requestor', 255)->nullable();
                $table->string('approved_by', 100)->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('batchno', 50)->nullable();
            });
        }

        if (! Schema::hasTable('requested_items')) {
            Schema::create('requested_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('stock_code', 50)->nullable();
                $table->string('description', 255)->nullable();
                $table->string('uom', 10)->nullable();
                $table->integer('available_qty')->nullable()->default(0);
                $table->integer('requested_qty')->nullable();
                $table->string('transaction_no', 50)->nullable();
                $table->string('requested_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('created_by', 50)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('updated_by', 50)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->string('deleted_by', 50)->nullable();
                $table->string('remarks', 255)->nullable();
                $table->integer('isClosed')->nullable();
                $table->string('isClosed_remarks', 255)->nullable();
                $table->string('batchno', 50)->nullable();
            });
        }

        if (! Schema::hasTable('issued_items')) {
            Schema::create('issued_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('item_id')->default(0);
                $table->string('item_code', 255)->nullable();
                $table->integer('issuance_qty')->default(0);
                $table->integer('balance')->nullable();
                $table->string('received_by', 100)->nullable();
                $table->string('issued_by', 100)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('batches')) {
            Schema::create('batches', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('batchno', 50)->nullable();
                $table->text('description')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->string('created_by', 50)->nullable();
                $table->string('updated_by', 50)->nullable();
                $table->string('deleted_by', 50)->nullable();
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
