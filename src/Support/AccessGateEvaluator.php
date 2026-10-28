<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Http\Request;
use Throwable;

final class AccessGateEvaluator
{
    private const string REQUEST_CACHE_KEY = 'bonfire_access_gate_results';

    /** @var array<string, bool> */
    private array $results = [];

    public function __construct(private readonly AccessGateRegistry $registry) {}

    public function allows(string $name, Member $member): bool
    {
        $key = $name.'|'.$member::class.'|'.($member->getKey() ?? 'new:'.spl_object_id($member));
        $request = app()->bound('request') ? app('request') : null;
        $results = $request instanceof Request
            ? $request->attributes->get(self::REQUEST_CACHE_KEY, [])
            : $this->results;

        if (is_array($results) && array_key_exists($key, $results)) {
            return (bool) $results[$key];
        }

        $resolver = $this->registry->resolver($name);

        if ($resolver === null) {
            return $this->remember($request, $key, false, $results);
        }

        try {
            $allowed = $resolver($member) === true;
        } catch (Throwable) {
            $allowed = false;
        }

        return $this->remember($request, $key, $allowed, $results);
    }

    /**
     * @param  array<string, bool>|mixed  $results
     */
    private function remember(mixed $request, string $key, bool $allowed, mixed $results): bool
    {
        if ($request instanceof Request) {
            $results = is_array($results) ? $results : [];
            $results[$key] = $allowed;
            $request->attributes->set(self::REQUEST_CACHE_KEY, $results);

            return $allowed;
        }

        $this->results[$key] = $allowed;

        return $allowed;
    }
}
