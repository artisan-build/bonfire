<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('body');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('room_id')->references('id')->on('bonfire_rooms')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('bonfire_messages')->cascadeOnDelete();
            $table->index(['room_id', 'parent_id', 'created_at']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_messages');
    }
};
