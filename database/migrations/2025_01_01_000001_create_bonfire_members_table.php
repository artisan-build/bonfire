<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonfire_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('memberable_type');
            $table->unsignedBigInteger('memberable_id');
            $table->string('display_name');
            $table->string('avatar_url', 2048)->nullable();
            $table->string('role', 20)->default('member');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['memberable_type', 'memberable_id', 'tenant_id'], 'bonfire_members_memberable_tenant_unique');
            $table->index(['memberable_type', 'memberable_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonfire_members');
    }
};
