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
        Schema::create('tte_requests', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('tim');
            $table->string('token', 8)->unique();
            $table->enum('status', ['tunggu', 'setuju', 'tolak', 'siap'])->default('tunggu')->index();
            $table->string('file_req');
            $table->string('file_tte')->nullable();
            $table->text('cat_admin')->nullable();
            $table->timestamp('tgl_setuju')->nullable();
            $table->timestamp('tgl_tolak')->nullable();
            $table->timestamp('kedaluwarsa')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tte_requests');
    }
};
