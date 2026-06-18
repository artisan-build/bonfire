<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

return new class extends Component
{
    use WithFileUploads;

    public string $statusEmoji = '';

    public string $statusText = '';

    public string $statusGame = '';

    public string $statusGameImage = '';

    /**
     * @var list<array{key: string, name: string, image: string}>
     */
    public const array GAMES = [
        ['key' => 'valorant', 'name' => 'Valorant', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/9b1c46f99c08b9f76dfcd49ec85a4b97.png'],
        ['key' => 'cs2', 'name' => 'CS2', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/2a14f31d29d1e0a1d4ef13a0775ecaf3.png'],
        ['key' => 'apex', 'name' => 'Apex Legends', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/c0560792e4a3c79e62f76cbf9fb277dd.png'],
        ['key' => 'lol', 'name' => 'League of Legends', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/c81e728d9d4c2f636f067f89cc14862c.png'],
        ['key' => 'fortnite', 'name' => 'Fortnite', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/eccbc87e4b5ce2fe28308fd9f2a7baf3.png'],
        ['key' => 'rocketleague', 'name' => 'Rocket League', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/a87ff679a2f3e71d9181a67b7542122c.png'],
        ['key' => 'minecraft', 'name' => 'Minecraft', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/e4da3b7fbbce2345d7772b0674a318d5.png'],
        ['key' => 'overwatch', 'name' => 'Overwatch 2', 'image' => 'https://cdn2.steamgriddb.com/icon_thumb/1679091c5a880faf6fb5e6087eb1b2dc.png'],
    ];

    public string $statusClearIn = '';

    public string $profileDisplayName = '';

    public string $profilePhone = '';

    public string $profileTimezone = '';

    public mixed $profileAvatar = null;

    public function mount(): void
    {
        $member = $this->member();

        if ($member !== null) {
            $this->statusEmoji = (string) ($member->status_emoji ?? '');
            $this->statusText = (string) ($member->status_text ?? '');
        }
    }

    #[Computed]
    public function member(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function timezones(): array
    {
        $options = [];
        foreach (DateTimeZone::listIdentifiers() as $tz) {
            if (str_starts_with($tz, 'Etc/') || $tz === 'UTC') {
                continue;
            }
            $options[] = [
                'value' => $tz,
                'label' => str_replace('_', ' ', $tz),
            ];
        }
        array_unshift($options, ['value' => 'UTC', 'label' => 'UTC']);

        return $options;
    }

    public function openStatusModal(): void
    {
        $member = $this->member();

        if ($member !== null) {
            $this->statusEmoji = (string) ($member->status_emoji ?? '');
            $this->statusText = (string) ($member->status_text ?? '');
            $this->statusGame = (string) ($member->status_game ?? '');
            $this->statusGameImage = (string) ($member->status_game_image ?? '');
        } else {
            $this->statusEmoji = '';
            $this->statusText = '';
            $this->statusGame = '';
            $this->statusGameImage = '';
        }

        $this->statusClearIn = '';

        $this->dispatch('modal-show', name: 'set-status');
    }

    public function openProfileModal(): void
    {
        $member = $this->member();

        if ($member !== null) {
            $this->profileDisplayName = (string) ($member->display_name ?? auth()->user()->name ?? '');
            $this->profilePhone = (string) ($member->phone ?? '');
            $this->profileTimezone = (string) ($member->timezone ?? config('app.timezone', 'UTC'));
        }

        $this->profileAvatar = null;

        $this->dispatch('modal-show', name: 'user-profile');
    }

    public function saveProfile(): void
    {
        $member = $this->member();

        if ($member === null) {
            return;
        }

        $this->validate([
            'profileDisplayName' => ['required', 'string', 'max:80'],
            'profilePhone' => ['nullable', 'string', 'max:40'],
            'profileTimezone' => ['nullable', 'string', 'max:64', 'timezone'],
            // Explicit raster-image allowlist. Laravel's `image` rule accepts
            // SVG (image/svg+xml), an XSS vector, so an explicit `mimetypes:`
            // list is used instead. The stored path remains the hashed file.
            'profileAvatar' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:4096'],
        ]);

        if ($this->profileAvatar !== null) {
            $path = $this->profileAvatar->store('avatars', 'public');
            $member->avatar_url = '/storage/'.$path;
        }

        $member->display_name = trim($this->profileDisplayName) ?: $member->display_name;
        $member->phone = trim($this->profilePhone) ?: null;
        $member->timezone = $this->profileTimezone ?: null;
        $member->save();

        $this->profileAvatar = null;
        $this->profileDisplayName = (string) $member->display_name;
        $this->profilePhone = (string) ($member->phone ?? '');
        $this->profileTimezone = (string) ($member->timezone ?? '');

        unset($this->member);

        $this->dispatch('modal-close', name: 'user-profile');
        $this->dispatch('bonfire:member-updated', memberId: $member->id);
    }

    #[On('bonfire:member-updated')]
    public function onMemberUpdated(): void
    {
        unset($this->member);
    }

    public function toggleAway(): void
    {
        $member = $this->member();

        if ($member === null) {
            return;
        }

        $member->is_away = ! $member->is_away;
        $member->save();

        unset($this->member);
    }

    public function saveStatus(): void
    {
        $member = $this->member();

        if ($member === null) {
            return;
        }

        $emoji = trim($this->statusEmoji);
        $text = trim($this->statusText);
        $expiresAt = null;

        if ($this->statusClearIn !== '' && $this->statusClearIn !== 'never') {
            $expiresAt = match ($this->statusClearIn) {
                'one_hour' => Carbon::now()->addHour(),
                'four_hours' => Carbon::now()->addHours(4),
                'today' => Carbon::now()->endOfDay(),
                'this_week' => Carbon::now()->endOfWeek(),
                default => null,
            };
        }

        if ($emoji === '' && $text === '' && $this->statusGame === '') {
            $member->status_emoji = null;
            $member->status_text = null;
            $member->status_game = null;
            $member->status_game_image = null;
            $member->status_expires_at = null;
        } else {
            $member->status_emoji = $emoji !== '' ? $emoji : null;
            $member->status_text = $text !== '' ? $text : null;
            $member->status_game = $this->statusGame !== '' ? $this->statusGame : null;
            $member->status_game_image = $this->statusGameImage !== '' ? $this->statusGameImage : null;
            $member->status_expires_at = $expiresAt;
        }

        $member->save();

        unset($this->member);

        $this->dispatch('modal-close', name: 'set-status');
    }

    /**
     * @return list<array{key: string, name: string, image: string}>
     */
    public function availableGames(): array
    {
        return self::GAMES;
    }

    public function pickGame(string $key): void
    {
        if ($key === '') {
            $this->statusGame = '';
            $this->statusGameImage = '';

            return;
        }

        foreach (self::GAMES as $g) {
            if ($g['key'] === $key) {
                $this->statusGame = $g['name'];
                $this->statusGameImage = $g['image'];
                if ($this->statusEmoji === '') {
                    $this->statusEmoji = '🎮';
                }
                if ($this->statusText === '') {
                    $this->statusText = 'Playing '.$g['name'];
                }

                return;
            }
        }
    }

    public function clearStatus(): void
    {
        $member = $this->member();

        if ($member === null) {
            return;
        }

        $member->status_emoji = null;
        $member->status_text = null;
        $member->status_game = null;
        $member->status_game_image = null;
        $member->status_expires_at = null;
        $member->save();

        $this->statusEmoji = '';
        $this->statusText = '';
        $this->statusClearIn = '';

        unset($this->member);

        $this->dispatch('modal-close', name: 'set-status');
    }

    /**
     * Whether the member's status is currently active (not past its expiry).
     * If the status has expired, clear it from the DB so it doesn't reappear
     * on the next render.
     */
    public function statusVisible(): bool
    {
        $member = $this->member();

        if ($member === null) {
            return false;
        }

        if ($member->status_emoji === null && $member->status_text === null) {
            return false;
        }

        if ($member->status_expires_at instanceof Carbon && $member->status_expires_at->isPast()) {
            $member->status_emoji = null;
            $member->status_text = null;
            $member->status_expires_at = null;
            $member->save();

            $this->statusEmoji = '';
            $this->statusText = '';
            $this->statusClearIn = '';

            unset($this->member);

            return false;
        }

        return true;
    }
};
