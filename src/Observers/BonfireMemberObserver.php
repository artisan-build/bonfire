<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Observers;

use ArtisanBuild\Bonfire\Facades\Bonfire;
use Illuminate\Database\Eloquent\Model;

class BonfireMemberObserver
{
    public function created(Model $model): void
    {
        $this->sync($model);
    }

    public function updated(Model $model): void
    {
        $this->sync($model);
    }

    protected function sync(Model $model): void
    {
        if (! method_exists($model, 'bonfireDisplayName')) {
            return;
        }

        Bonfire::ensureMember(
            memberable: $model,
            displayName: $model->bonfireDisplayName(),
            avatarUrl: method_exists($model, 'bonfireAvatarUrl') ? $model->bonfireAvatarUrl() : null,
        );
    }
}
