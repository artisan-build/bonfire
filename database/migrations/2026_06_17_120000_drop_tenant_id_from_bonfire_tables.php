<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bonfire is now single-tenant: one deployment per community. The multi-tenant
 * `tenant_id` apparatus is removed. This migration drops the columns and the
 * tenant-aware indexes/constraints from any instance that already ran the
 * original create migrations.
 *
 * It is defensive (guards on column existence) so it is a no-op on fresh
 * installs where the create migrations were already updated to omit tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bonfire_members', 'tenant_id')) {
            Schema::table('bonfire_members', function (Blueprint $table): void {
                // Drop the tenant-aware unique and recreate a single-tenant one.
                $table->dropUnique('bonfire_members_memberable_tenant_unique');
            });
            Schema::table('bonfire_members', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
                $table->unique(['memberable_type', 'memberable_id'], 'bonfire_members_memberable_unique');
            });
        }

        if (Schema::hasColumn('bonfire_rooms', 'tenant_id')) {
            Schema::table('bonfire_rooms', function (Blueprint $table): void {
                $table->dropUnique(['tenant_id', 'slug']);
            });
            Schema::table('bonfire_rooms', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
                $table->unique('slug');
            });
        }

        if (Schema::hasColumn('bonfire_messages', 'tenant_id')) {
            Schema::table('bonfire_messages', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasColumn('bonfire_call_sessions', 'tenant_id')) {
            Schema::table('bonfire_call_sessions', function (Blueprint $table): void {
                $table->dropColumn('tenant_id');
            });
        }
    }

    public function down(): void
    {
        // Single-tenant is a one-way architectural decision; the multi-tenant
        // columns are intentionally not restored.
    }
};
