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
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('instance_identifier')->unique();
            $table->string('host')->nullable();
            $table->integer('port')->default(5432);
            $table->string('database_name');
            $table->string('username');
            $table->text('password');
            $table->string('status')->default('pending');
            $table->string('rds_instance_id')->nullable();
            $table->string('engine')->default('postgres');
            $table->string('engine_version')->default('14.6');
            $table->string('instance_class')->default('db.t3.micro');
            $table->string('storage_type')->default('gp2');
            $table->integer('allocated_storage')->default(20);
            $table->boolean('encrypted')->default(true);
            $table->string('provisioning_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'database_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
