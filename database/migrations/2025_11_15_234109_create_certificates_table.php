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

        if (!Schema::hasTable('certificates')) {
            Schema::create('certificates', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('user_id');
                $table->string('user_name', 255);
                $table->string('user_cpf', 20)->nullable();

                $table->unsignedBigInteger('event_id');
                $table->string('event_title', 255);
                $table->dateTime('event_start_at');

                $table->char('code', 36)->unique();
                $table->dateTime('issued_at');
                $table->string('pdf_url', 512);

                $table->timestamps();

                $table->index('user_id');
                $table->index('event_id');
            });
            return;
        }


        Schema::table('certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('certificates', 'user_name')) {
                $table->string('user_name', 255)->after('user_id');
            }
            if (!Schema::hasColumn('certificates', 'user_cpf')) {
                $table->string('user_cpf', 20)->nullable()->after('user_name');
            }
            if (!Schema::hasColumn('certificates', 'event_title')) {
                $table->string('event_title', 255)->after('event_id');
            }
            if (!Schema::hasColumn('certificates', 'event_start_at')) {
                $table->dateTime('event_start_at')->after('event_title');
            }
            if (!Schema::hasColumn('certificates', 'code')) {
                $table->char('code', 36)->unique()->after('event_start_at');
            }
            if (!Schema::hasColumn('certificates', 'issued_at')) {
                $table->dateTime('issued_at')->after('code');
            }
            if (!Schema::hasColumn('certificates', 'pdf_url')) {
                $table->string('pdf_url', 512)->after('issued_at');
            }
            // created_at / updated_at
            if (!Schema::hasColumn('certificates', 'created_at')) {
                $table->timestamps();
            }
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};

