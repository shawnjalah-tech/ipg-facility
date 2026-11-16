<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_attention_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('threshold', 12);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['booking_id', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_attention_reminders');
        Schema::dropIfExists('notifications');
    }
};
