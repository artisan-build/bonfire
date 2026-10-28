<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use Closure;

final class MemberBadgeRegistry
{
    /** @var (Closure(Member): mixed)|null */
    private ?Closure $resolver = null;

    /**
     * @param  callable(Member): mixed  $resolver
     */
    public function register(callable $resolver): void
    {
        $this->resolver = Closure::fromCallable($resolver);
    }

    /**
     * @return (Closure(Member): mixed)|null
     */
    public function resolver(): ?Closure
    {
        return $this->resolver;
    }
}
