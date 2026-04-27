<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Tests\Fixtures;

use ArtisanBuild\Bonfire\Traits\HasBonfireProfile;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model implements Authenticatable
{
    use HasBonfireProfile;

    protected $table = 'test_users';

    protected $guarded = [];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
