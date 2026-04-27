<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_link_previews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('url', 2048);
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('site_name')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->boolean('failed')->default(false);

            $table->foreign('message_id')->references('id')->on('bonfire_messages')->cascadeOnDelete();
            $table->unique('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_link_previews');
    }
};
