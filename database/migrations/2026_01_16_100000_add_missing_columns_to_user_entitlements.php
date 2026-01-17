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
        Schema::table('user_entitlements', function (Blueprint $table) {
            if (!Schema::hasColumn('user_entitlements', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('ends_at');
            }
            if (!Schema::hasColumn('user_entitlements', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable()->after('auto_renew');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_entitlements', function (Blueprint $table) {
            if (Schema::hasColumn('user_entitlements', 'auto_renew')) {
                $table->dropColumn('auto_renew');
            }
            if (Schema::hasColumn('user_entitlements', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
        });
    }
};
