<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonfire_rooms', function (Blueprint $table): void {
            $table->string('access_gate')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('bonfire_rooms', function (Blueprint $table): void {
            $table->dropColumn('access_gate');
        });
    }
};
