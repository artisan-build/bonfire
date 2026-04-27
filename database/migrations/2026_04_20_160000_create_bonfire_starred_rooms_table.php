<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_starred_rooms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('room_id');
            $table->timestamp('created_at')->nullable();

            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
            $table->foreign('room_id')->references('id')->on('bonfire_rooms')->cascadeOnDelete();
            $table->unique(['member_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_starred_rooms');
    }
};
