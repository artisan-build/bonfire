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
            $table->timestamp('scheduled_for')->nullable()->after('body');
            $table->index(['room_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::table('bonfire_messages', function (Blueprint $table): void {
            $table->dropIndex(['room_id', 'scheduled_for']);
            $table->dropColumn('scheduled_for');
        });
    }
};
