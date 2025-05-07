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
        Schema::create('rds_instances', function (Blueprint $table) {
            $table->id();
            $table->string('client_id');
            $table->string('instance_identifier')->unique();
            $table->string('status');
            $table->string('endpoint')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rds_instances');
    }
};
