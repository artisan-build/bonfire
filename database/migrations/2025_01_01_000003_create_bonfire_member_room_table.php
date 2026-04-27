<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_member_room', function (Blueprint $table): void {
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->primary(['member_id', 'room_id']);
            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
            $table->foreign('room_id')->references('id')->on('bonfire_rooms')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('bonfire_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_member_room');
    }
};
