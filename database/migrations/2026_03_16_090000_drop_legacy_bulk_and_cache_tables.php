<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bulk_uploads');
        Schema::dropIfExists('bulk_downloads');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }

    public function down(): void
    {
        if (! Schema::hasTable('bulk_uploads')) {
            Schema::create('bulk_uploads', function (Blueprint $table) {
                $table->id();
                $table->string('status', 20)->default('queued');
                $table->unsignedInteger('total_file')->default(0);
                $table->unsignedInteger('processed')->default(0);
                $table->unsignedInteger('success')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->json('error_log_json')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bulk_downloads')) {
            Schema::create('bulk_downloads', function (Blueprint $table) {
                $table->id();
                $table->string('status', 20)->default('queued');
                $table->unsignedInteger('total_file')->default(0);
                $table->unsignedInteger('processed')->default(0);
                $table->unsignedInteger('success')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->string('zip_path')->nullable();
                $table->json('error_log_json')->nullable();
                $table->json('result_log_json')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }
};

