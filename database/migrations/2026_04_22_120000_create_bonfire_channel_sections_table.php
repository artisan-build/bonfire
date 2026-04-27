<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_channel_sections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->string('name', 80);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
            $table->index(['member_id', 'position']);
        });

        Schema::table('bonfire_member_room', function (Blueprint $table): void {
            $table->unsignedBigInteger('section_id')->nullable()->after('last_read_at');
            $table->foreign('section_id')
                ->references('id')->on('bonfire_channel_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bonfire_member_room', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });
        Schema::dropIfExists('bonfire_channel_sections');
    }
};
