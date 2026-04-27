<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonfire_members', function (Blueprint $table): void {
            $table->string('status_emoji', 32)->nullable()->after('avatar_url');
            $table->string('status_text', 100)->nullable()->after('status_emoji');
            $table->timestamp('status_expires_at')->nullable()->after('status_text');
            $table->boolean('is_away')->default(false)->after('status_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bonfire_members', function (Blueprint $table): void {
            $table->dropColumn(['status_emoji', 'status_text', 'status_expires_at', 'is_away']);
        });
    }
};
