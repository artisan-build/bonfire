<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestBot extends Model
{
    protected $table = 'test_bots';

    protected $guarded = [];
}
