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
        } else {
            $this->statusEmoji = '';
            $this->statusText = '';
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
            'profileAvatar' => ['nullable', 'image', 'max:4096'],
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

        if ($emoji === '' && $text === '') {
            $member->status_emoji = null;
            $member->status_text = null;
            $member->status_expires_at = null;
        } else {
            $member->status_emoji = $emoji !== '' ? $emoji : null;
            $member->status_text = $text !== '' ? $text : null;
            $member->status_expires_at = $expiresAt;
        }

        $member->save();

        unset($this->member);

        $this->dispatch('modal-close', name: 'set-status');
    }

    public function clearStatus(): void
    {
        $member = $this->member();

        if ($member === null) {
            return;
        }

        $member->status_emoji = null;
        $member->status_text = null;
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
            return false;
        }

        return true;
    }
};
