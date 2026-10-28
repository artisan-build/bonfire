<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use Closure;
use InvalidArgumentException;

final class AccessGateRegistry
{
    /** @var array<string, Closure(Member): bool> */
    private array $resolvers = [];

    /**
     * @param  callable(Member): bool  $resolver
     */
    public function register(string $name, callable $resolver): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('An access gate name cannot be empty.');
        }

        $this->resolvers[$name] = Closure::fromCallable($resolver);
    }

    /**
     * @return (Closure(Member): bool)|null
     */
    public function resolver(string $name): ?Closure
    {
        return $this->resolvers[$name] ?? null;
    }
}
