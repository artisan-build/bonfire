<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_mentions', function (Blueprint $table): void {
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('member_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['message_id', 'member_id']);
            $table->foreign('message_id')->references('id')->on('bonfire_messages')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_mentions');
    }
};
