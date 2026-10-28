<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reshape primary key to (message_id, member_id, emoji) so a member can
        // react to the same message with multiple different emoji. SQLite can't
        // change a composite PK in-place, so recreate the table.
        Schema::create('bonfire_reactions_new', function (Blueprint $table): void {
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('member_id');
            $table->string('emoji', 16);
            $table->timestamp('created_at')->nullable();

            $table->primary(['message_id', 'member_id', 'emoji']);
            $table->foreign('message_id')->references('id')->on('bonfire_messages')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
            $table->index(['message_id', 'emoji']);
        });

        if (Schema::hasTable('bonfire_reactions')) {
            DB::table('bonfire_reactions')->orderBy('message_id')->chunk(500, function ($rows): void {
                $insert = $rows->map(fn ($r) => [
                    'message_id' => $r->message_id,
                    'member_id' => $r->member_id,
                    'emoji' => '👍',
                    'created_at' => $r->created_at ?? now(),
                ])->all();
                if ($insert !== []) {
                    DB::table('bonfire_reactions_new')->insert($insert);
                }
            });

            Schema::drop('bonfire_reactions');
        }

        Schema::rename('bonfire_reactions_new', 'bonfire_reactions');
    }

    public function down(): void
    {
        Schema::create('bonfire_reactions_old', function (Blueprint $table): void {
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('member_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['message_id', 'member_id']);
            $table->foreign('message_id')->references('id')->on('bonfire_messages')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('bonfire_members')->cascadeOnDelete();
        });

        if (Schema::hasTable('bonfire_reactions')) {
            DB::table('bonfire_reactions')
                ->select('message_id', 'member_id')
                ->distinct()
                ->orderBy('message_id')
                ->chunk(500, function ($rows): void {
                    $insert = $rows->map(fn ($r) => [
                        'message_id' => $r->message_id,
                        'member_id' => $r->member_id,
                        'created_at' => now(),
                    ])->all();
                    if ($insert !== []) {
                        DB::table('bonfire_reactions_old')->insert($insert);
                    }
                });

            Schema::drop('bonfire_reactions');
        }

        Schema::rename('bonfire_reactions_old', 'bonfire_reactions');
    }
};
