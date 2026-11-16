<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite implements enum columns as VARCHAR with a CHECK constraint.
        // change() rebuilds the table while preserving booking records/IDs.
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'expired'])
                ->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Never silently rewrite expired history during a rollback.
        if (DB::table('bookings')->where('status', 'expired')->exists()) {
            throw new \RuntimeException('Cannot remove expired booking status while expired bookings exist.');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')->change();
        });
    }
};
