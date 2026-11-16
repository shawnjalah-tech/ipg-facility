<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recurring_booking_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('occurrence_count');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('recurring_series_id')->nullable()
                ->constrained('recurring_booking_series')->restrictOnDelete();
            $table->index(['recurring_series_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['recurring_series_id']);
            $table->dropIndex(['recurring_series_id', 'start_date']);
            $table->dropColumn('recurring_series_id');
        });
        Schema::dropIfExists('recurring_booking_series');
    }
};
