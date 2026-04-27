<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    public Room $room;

    public int $parentId;

    public function mount(Room $room, int $parentId): void
    {
        $this->room = $room;
        $this->parentId = $parentId;

        $member = Bonfire::memberFor(auth()->user());
        abort_unless(
            ! $this->room->isPrivate() || ($member !== null && $this->room->isAccessibleBy($member)),
            403,
        );
    }

    #[Computed]
    public function parent(): Message
    {
        return Message::withTrashed()->with('member')->findOrFail($this->parentId);
    }

    /**
     * Reply messages under the parent thread.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function replies(): Collection
    {
        return Message::query()
            ->with('member')
            ->where('parent_id', $this->parentId)->oldest()
            ->get();
    }

    public function close(): void
    {
        $this->dispatch('thread-close');
    }

    #[On('bonfire:member-updated')]
    public function onMemberUpdated(): void
    {
        unset($this->parent, $this->replies);
    }
};
