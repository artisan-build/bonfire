<?php

declare(strict_types=1);

/**
 * Monorepo test setup for the bonfire package.
 *
 * This file is auto-discovered by the root tests/Pest.php when running
 * tests in monorepo context. It creates fixture tables that the package
 * TestCase normally creates in standalone mode via Orchestra Testbench.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses()->beforeEach(function (): void {
    if (! Schema::hasTable('test_users')) {
        Schema::create('test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('test_bots')) {
        Schema::create('test_bots', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
})->in('Feature', 'Unit');
