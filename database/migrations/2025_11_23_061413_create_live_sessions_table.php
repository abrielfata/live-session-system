<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Host
            $table->dateTime('scheduled_at'); // Jadwal live
            $table->string('google_calendar_event_id')->nullable();
            $table->decimal('host_reported_gmv', 15, 2)->nullable(); // GMV dari OCR
            $table->string('screenshot_path')->nullable(); // Path gambar
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};