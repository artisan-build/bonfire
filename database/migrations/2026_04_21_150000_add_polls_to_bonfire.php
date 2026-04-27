<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonfire_messages', function (Blueprint $table): void {
            $table->json('poll')->nullable()->after('body');
        });

        Schema::create('bonfire_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedSmallInteger('option_index');
            $table->timestamp('created_at')->nullable();

            $table->foreign('message_id')->references('id')->on('bonfire_messages')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
            $table->unique(['message_id', 'member_id'], 'bonfire_poll_votes_unique');
            $table->index(['message_id', 'option_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_poll_votes');
        Schema::table('bonfire_messages', function (Blueprint $table): void {
            $table->dropColumn('poll');
        });
    }
};
