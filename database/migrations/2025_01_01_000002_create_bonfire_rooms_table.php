<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('type')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique('slug');
            $table->foreign('created_by')->references('id')->on('bonfire_members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_rooms');
    }
};
