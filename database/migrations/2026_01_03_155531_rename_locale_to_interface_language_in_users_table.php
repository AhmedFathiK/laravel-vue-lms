<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('locale', 'interface_language');
            });
        } else {
            // specific for MariaDB/MySQL to avoid DBAL quoting issues
            DB::statement("ALTER TABLE users CHANGE locale interface_language VARCHAR(255) NOT NULL DEFAULT 'en'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('interface_language', 'locale');
            });
        } else {
            DB::statement("ALTER TABLE users CHANGE interface_language locale VARCHAR(255) NOT NULL DEFAULT 'en'");
        }
    }
};
