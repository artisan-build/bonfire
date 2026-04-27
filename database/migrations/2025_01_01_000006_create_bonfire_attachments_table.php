<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at')->nullable();

            $table->foreign('message_id')->references('id')->on('bonfire_messages')->cascadeOnDelete();
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_attachments');
    }
};
