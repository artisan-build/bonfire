<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_room_reads', function (Blueprint $table): void {
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('room_id');
            $table->timestamp('last_read_at')->nullable();

            $table->unique(['member_id', 'room_id']);
            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
            $table->foreign('room_id')->references('id')->on('bonfire_rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_room_reads');
    }
};
