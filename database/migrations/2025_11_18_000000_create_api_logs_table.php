<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();

            $table->string('direction', 10);

            $table->string('service', 50)->nullable();

            $table->string('method', 10);
            $table->string('path', 255);

            $table->unsignedSmallInteger('status_code')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 45)->nullable();

            $table->json('request_body')->nullable();

            $table->json('response_body')->nullable();

            $table->float('duration_ms')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
