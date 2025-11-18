<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });


        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'deleted_at')) {
                $table->softDeletes();
            }
        });


        Schema::table('event_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('event_registrations', 'deleted_at')) {
                $table->softDeletes();
            }
        });


        Schema::table('certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('certificates', 'deleted_at')) {
                $table->softDeletes();
            }
        });


    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('event_registrations', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('certificates', function (Blueprint $table) {
            if (Schema::hasColumn('certificates', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
