<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Traits;

trait HasBonfireProfile
{
    public function bonfireDisplayName(): string
    {
        return (string) ($this->name ?? '');
    }

    public function bonfireAvatarUrl(): ?string
    {
        return null;
    }
}
