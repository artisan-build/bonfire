<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_call_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('caller_member_id');
            $table->unsignedBigInteger('callee_member_id');
            $table->string('status', 20)->default('ringing'); // ringing, active, ended, declined, missed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index(['callee_member_id', 'status']);
            $table->index(['caller_member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_call_sessions');
    }
};
