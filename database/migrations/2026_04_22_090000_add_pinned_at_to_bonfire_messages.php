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
            $table->timestamp('pinned_at')->nullable();
            $table->unsignedBigInteger('pinned_by_member_id')->nullable();

            $table->index(['room_id', 'pinned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bonfire_messages', function (Blueprint $table): void {
            $table->dropIndex(['room_id', 'pinned_at']);
            $table->dropColumn(['pinned_at', 'pinned_by_member_id']);
        });
    }
};
