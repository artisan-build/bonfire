<div class="h-full w-full overflow-y-auto"
     x-data="{
         pending: { action: null, id: null, name: '' },
         countdown: 0,
         timer: null,
         ask(action, id, name, seconds) {
             this.pending = { action, id, name };
             this.countdown = seconds;
             clearInterval(this.timer);
             this.timer = setInterval(() => {
                 this.countdown = Math.max(0, this.countdown - 1);
                 if (this.countdown === 0) {
                     clearInterval(this.timer);
                     this.timer = null;
                 }
             }, 1000);
             this.$dispatch('modal-show', { name: 'admin-confirm' });
         },
         cancel() {
             clearInterval(this.timer);
             this.timer = null;
             this.countdown = 0;
             this.pending = { action: null, id: null, name: '' };
         },
         confirm() {
             if (this.countdown > 0 || this.pending.id === null) return;
             this.$wire.call(this.pending.action, this.pending.id);
             this.$dispatch('modal-close', { name: 'admin-confirm' });
             this.cancel();
         },
         get heading() {
             if (this.pending.action === 'forceDeleteRoom') return 'Permanently delete channel?';
             if (this.pending.action === 'deleteRoom') return 'Delete channel?';
             return 'Confirm';
         },
         get body() {
             if (this.pending.action === 'forceDeleteRoom') {
                 return '#' + this.pending.name + ' and all its messages will be wiped. This cannot be undone.';
             }
             if (this.pending.action === 'deleteRoom') {
                 return '#' + this.pending.name + ' will move to the Deleted channels list. You can restore it from there.';
             }
             return '';
         },
     }">
    <div class="mx-auto w-full max-w-5xl px-4 py-10
                sm:px-6
                lg:px-8">
    <flux:separator />

    <div class="mt-6 flex flex-wrap items-center gap-x-8 gap-y-2 border-b border-zinc-200 dark:border-zinc-800">
        <button type="button"
                wire:click="$set('tab', 'rooms')"
                @class([
                    'relative -mb-px flex items-center gap-2 px-1 py-3 text-sm transition',
                    'border-b-2 border-zinc-900 font-semibold text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' => $tab === 'rooms',
                    'border-b-2 border-transparent font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== 'rooms',
                ])>
            <flux:icon name="hashtag" class="size-4" />
            Channels
        </button>
        <button type="button"
                wire:click="$set('tab', 'members')"
                @class([
                    'relative -mb-px flex items-center gap-2 px-1 py-3 text-sm transition',
                    'border-b-2 border-zinc-900 font-semibold text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' => $tab === 'members',
                    'border-b-2 border-transparent font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== 'members',
                ])>
            <flux:icon name="users" class="size-4" />
            Members
        </button>
    </div>

    @if ($tab === 'rooms')
        <section class="mt-8 space-y-10">
            <form wire:submit="createRoom"
                  class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm
                         dark:border-zinc-800 dark:bg-zinc-900">
                <header class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                        <flux:icon name="plus-circle" class="size-5 text-zinc-500" />
                        Create a channel
                    </h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Channels organize conversations. You can change visibility later.
                    </p>
                </header>
                <div class="space-y-5 px-6 py-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                Name
                            </label>
                            <input wire:model="newName"
                                   type="text"
                                   placeholder="e.g. plan-budget"
                                   class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2.5 text-sm
                                          focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                          dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                Description
                                <span class="font-normal normal-case text-zinc-400">(optional)</span>
                            </label>
                            <input wire:model="newDescription"
                                   type="text"
                                   placeholder="What's this channel about?"
                                   class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2.5 text-sm
                                          focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                          dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        </div>
                    </div>
                    <flux:separator variant="subtle" />
                    <div class="flex flex-wrap gap-x-8 gap-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                        <label class="flex items-center gap-2.5">
                            <input wire:model="newPrivate" type="checkbox"
                                   class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                            <span class="font-medium">Private</span>
                        </label>
                        <label class="flex items-center gap-2.5">
                            <input wire:model="newArchived" type="checkbox"
                                   class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                            <span class="font-medium">Archived</span>
                        </label>
                        <label class="flex items-center gap-2.5">
                            <input wire:model="newAnnouncements" type="checkbox"
                                   class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                            <span class="font-medium">Announcements</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end border-t border-zinc-200 bg-zinc-50 px-6 py-3
                            dark:border-zinc-800 dark:bg-zinc-900/60">
                    <flux:button type="submit" variant="primary" icon="plus">Create channel</flux:button>
                </div>
            </form>

            @if ($this->deletedRooms->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 shadow-sm
                            dark:border-zinc-800 dark:bg-zinc-900/60">
                    <header class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                            <flux:icon name="archive-box" class="size-5 text-zinc-500" />
                            Deleted channels
                            <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-700
                                         dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $this->deletedRooms->count() }}
                            </span>
                        </h2>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Restore a channel to bring back its messages and members. Delete forever is permanent.
                        </p>
                    </header>
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($this->deletedRooms as $room)
                            <li wire:key="deleted-room-{{ $room->id }}"
                                class="flex items-center gap-4 px-6 py-3">
                                <flux:icon name="hashtag" class="size-4 text-zinc-400" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $room->name }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        Deleted {{ $room->deleted_at?->diffForHumans() }}
                                    </div>
                                </div>
                                <button type="button"
                                        wire:click="restoreRoom({{ $room->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white
                                               shadow-sm transition hover:bg-emerald-700
                                               dark:bg-emerald-700 dark:hover:bg-emerald-600">
                                    <flux:icon name="arrow-uturn-left" variant="solid" class="size-3.5" />
                                    Restore
                                </button>
                                <button type="button"
                                        @click="ask('forceDeleteRoom', {{ $room->id }}, {{ Illuminate\Support\Js::from($room->name) }}, 5)"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700
                                               transition hover:bg-rose-50
                                               dark:border-rose-900/70 dark:text-rose-300 dark:hover:bg-rose-950/50">
                                    <flux:icon name="trash" variant="solid" class="size-3.5" />
                                    Delete forever
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-3">
                <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                    <flux:icon name="rectangle-stack" class="size-5 text-zinc-500" />
                    Channels
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700
                                 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $this->rooms->count() }}
                    </span>
                </h2>

                <ul role="list" class="space-y-5">
                    @forelse ($this->rooms as $room)
                        <li wire:key="admin-room-{{ $room->id }}"
                            class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm
                                   dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="grid gap-6 p-6 sm:grid-cols-[1fr_auto]">
                                <div class="space-y-3">
                                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                        <flux:icon name="hashtag" class="size-3.5 text-zinc-400" />
                                        <span>{{ $room->slug }}</span>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                            Name
                                        </label>
                                        <input value="{{ $room->name }}"
                                               wire:change="updateRoom({{ $room->id }}, 'name', $event.target.value)"
                                               class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-medium
                                                      focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                                      dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                            Description
                                        </label>
                                        <input value="{{ $room->description }}"
                                               wire:change="updateRoom({{ $room->id }}, 'description', $event.target.value)"
                                               placeholder="Add a description"
                                               class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-xs
                                                      focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                                      dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                    </div>
                                    <div class="pt-1">
                                        <button type="button"
                                                wire:click="toggleRoomMembers({{ $room->id }})"
                                                @class([
                                                    'inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-semibold transition',
                                                    'border-sky-400 bg-sky-50 text-sky-700 shadow-sm dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-300' => $expandedRoomId === $room->id,
                                                    'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800' => $expandedRoomId !== $room->id,
                                                ])>
                                            <flux:icon name="users" variant="solid" class="size-3.5" />
                                            Members
                                            <span class="rounded bg-zinc-200 px-1.5 py-0.5 text-xs font-bold text-zinc-700
                                                         dark:bg-zinc-700 dark:text-zinc-200">
                                                {{ $room->members->count() }}
                                            </span>
                                            <flux:icon :name="$expandedRoomId === $room->id ? 'chevron-up' : 'chevron-down'"
                                                       variant="solid"
                                                       class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-4 text-sm text-zinc-700
                                            sm:border-l sm:border-zinc-200 sm:pl-6
                                            dark:text-zinc-300 sm:dark:border-zinc-800">
                                    <div class="space-y-2.5">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                            Visibility
                                        </p>
                                        <label class="flex items-center gap-2.5 text-xs">
                                            <input type="checkbox"
                                                   class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                                                   @checked($room->isPrivate())
                                                   wire:change="updateRoom({{ $room->id }}, 'private', $event.target.checked)">
                                            <span class="font-medium">Private</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 text-xs">
                                            <input type="checkbox"
                                                   class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                                                   @checked($room->isArchived())
                                                   wire:change="updateRoom({{ $room->id }}, 'archived', $event.target.checked)">
                                            <span class="font-medium">Archived</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 text-xs">
                                            <input type="checkbox"
                                                   class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                                                   @checked($room->isAnnouncements())
                                                   wire:change="updateRoom({{ $room->id }}, 'announcements', $event.target.checked)">
                                            <span class="font-medium">Announcements</span>
                                        </label>
                                    </div>
                                    <flux:separator variant="subtle" />
                                    <button type="button"
                                            @click="ask('deleteRoom', {{ $room->id }}, {{ Illuminate\Support\Js::from($room->name) }}, 3)"
                                            class="inline-flex items-center justify-center gap-1.5 rounded-md border border-rose-300
                                                   bg-white px-3 py-2 text-xs font-semibold text-rose-700 shadow-sm transition
                                                   hover:bg-rose-50
                                                   dark:border-rose-900/70 dark:bg-zinc-900 dark:text-rose-300 dark:hover:bg-rose-950/40">
                                        <flux:icon name="trash" variant="solid" class="size-3.5" />
                                        Delete channel
                                    </button>
                                </div>
                            </div>

                            @if ($expandedRoomId === $room->id)
                                <div class="border-t-2 border-zinc-200 bg-zinc-50/80 px-6 py-5
                                            dark:border-zinc-800 dark:bg-zinc-900/60">
                                    <div class="grid gap-6 sm:grid-cols-2 sm:divide-x sm:divide-zinc-200 sm:dark:divide-zinc-800">
                                        <div class="sm:pr-6">
                                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                                <flux:icon name="user-group" variant="solid" class="size-4" />
                                                In channel
                                                <span class="rounded-full bg-zinc-200 px-1.5 py-0.5 text-xs font-bold text-zinc-700
                                                             dark:bg-zinc-800 dark:text-zinc-300">
                                                    {{ $room->members->count() }}
                                                </span>
                                            </h3>
                                            @if ($room->members->isEmpty())
                                                <p class="mt-3 rounded-md border border-dashed border-zinc-300 p-4 text-xs text-zinc-500
                                                          dark:border-zinc-700">
                                                    No members yet. Add some on the right.
                                                </p>
                                            @else
                                                <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                                                    @foreach ($room->members as $assigned)
                                                        <li wire:key="room-{{ $room->id }}-member-{{ $assigned->id }}"
                                                            class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2
                                                                   shadow-sm transition hover:border-zinc-300
                                                                   dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                                                            <img src="{{ $assigned->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($assigned->display_name) }}"
                                                                 alt=""
                                                                 class="size-8 flex-shrink-0 rounded-md bg-zinc-200 dark:bg-zinc-700">
                                                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                                                {{ $assigned->display_name }}
                                                            </span>
                                                            <button type="button"
                                                                    wire:click="removeMemberFromRoom({{ $room->id }}, {{ $assigned->id }})"
                                                                    title="Remove from {{ $room->name }}"
                                                                    aria-label="Remove {{ $assigned->display_name }} from {{ $room->name }}"
                                                                    class="inline-flex size-7 items-center justify-center rounded-md border border-zinc-200
                                                                           bg-white text-zinc-500 shadow-sm transition
                                                                           hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700
                                                                           dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400
                                                                           dark:hover:border-rose-800 dark:hover:bg-rose-950/40 dark:hover:text-rose-400">
                                                                <flux:icon name="x-mark" variant="solid" class="size-4" />
                                                            </button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                        <div class="sm:pl-6">
                                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                                <flux:icon name="user-plus" variant="solid" class="size-4" />
                                                Add member
                                            </h3>
                                            <div class="mt-3 overflow-hidden rounded-lg border border-zinc-300 bg-white
                                                        dark:border-zinc-700 dark:bg-zinc-900">
                                                <div class="border-b border-zinc-200 p-2 dark:border-zinc-700">
                                                    <div class="flex items-center gap-2 rounded-md bg-zinc-100 px-2 py-1.5
                                                                dark:bg-zinc-800">
                                                        <flux:icon name="magnifying-glass" class="size-3.5 text-zinc-400" />
                                                        <input type="search"
                                                               wire:model.live.debounce.200ms="memberSearch"
                                                               placeholder="Search people"
                                                               class="h-6 w-full border-0 bg-transparent text-xs
                                                                      focus:outline-none focus:ring-0
                                                                      dark:text-zinc-100">
                                                    </div>
                                                </div>
                                                <ul class="max-h-64 space-y-1 overflow-y-auto p-2">
                                                    @forelse ($this->availableMembers as $candidate)
                                                        <li wire:key="room-{{ $room->id }}-candidate-{{ $candidate->id }}">
                                                            <button type="button"
                                                                    wire:click="addMemberToRoom({{ $room->id }}, {{ $candidate->id }})"
                                                                    class="group flex w-full items-center gap-3 rounded-md px-2 py-2 text-left text-sm
                                                                           transition hover:bg-sky-50 dark:hover:bg-sky-950/30">
                                                                <img src="{{ $candidate->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($candidate->display_name) }}"
                                                                     alt=""
                                                                     class="size-7 flex-shrink-0 rounded-md bg-zinc-200 dark:bg-zinc-700">
                                                                <span class="min-w-0 flex-1 truncate font-medium text-zinc-800 dark:text-zinc-200">
                                                                    {{ $candidate->display_name }}
                                                                </span>
                                                                <span class="inline-flex size-6 items-center justify-center rounded-md border border-zinc-200
                                                                             bg-white text-zinc-500 transition
                                                                             group-hover:border-sky-400 group-hover:bg-sky-100 group-hover:text-sky-700
                                                                             dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400
                                                                             dark:group-hover:border-sky-700 dark:group-hover:bg-sky-950/50 dark:group-hover:text-sky-300">
                                                                    <flux:icon name="plus" variant="solid" class="size-3.5" />
                                                                </span>
                                                            </button>
                                                        </li>
                                                    @empty
                                                        <li class="px-2 py-3 text-center text-xs text-zinc-500">
                                                            @if (trim($memberSearch) !== '')
                                                                No matches.
                                                            @else
                                                                Everyone has been added.
                                                            @endif
                                                        </li>
                                                    @endforelse
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-xl border-2 border-dashed border-zinc-300 p-10 text-center text-sm text-zinc-500
                                   dark:border-zinc-700">
                            No channels yet. Use the form above to create one.
                        </li>
                    @endforelse
                </ul>
            </div>
        </section>
    @else
        <section class="mt-8 space-y-6">
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900
                        dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                <flux:icon name="information-circle" variant="solid" class="mt-0.5 size-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                <p class="leading-relaxed">
                    <strong class="font-semibold">Deactivate</strong>
                    blocks the member from opening any channel, downloading attachments, or deleting messages.
                    Their messages and history stay intact, and
                    <strong class="font-semibold">Reactivate</strong>
                    reverses it. The underlying user account is not deleted.
                </p>
            </div>

            <div class="space-y-3">
                <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                    <flux:icon name="users" class="size-5 text-zinc-500" />
                    All members
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700
                                 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $this->members->count() }}
                    </span>
                </h2>

                <ul role="list" class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm
                                       divide-y divide-zinc-200
                                       dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                    @forelse ($this->members as $member)
                        <li wire:key="admin-member-{{ $member->id }}"
                            class="flex flex-wrap items-center gap-4 px-6 py-4">
                            <img src="{{ $member->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($member->display_name) }}"
                                 alt=""
                                 class="size-10 flex-shrink-0 rounded-md bg-zinc-200 dark:bg-zinc-700">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $member->display_name }}
                                    </span>
                                    @if (! $member->is_active)
                                        <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-zinc-700
                                                     dark:bg-zinc-800 dark:text-zinc-300">
                                            Inactive
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-xs text-zinc-500">
                                    {{ class_basename($member->memberable_type) }}#{{ $member->memberable_id }}
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                    Role
                                </label>
                                <select wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                        class="rounded-md border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-medium
                                               focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                               dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                    @foreach (\ArtisanBuild\Bonfire\Enums\BonfireRole::cases() as $role)
                                        <option value="{{ $role->value }}" @selected($member->role === $role)>
                                            {{ ucfirst($role->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button"
                                    wire:click="toggleActive({{ $member->id }})"
                                    @class([
                                        'inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-semibold shadow-sm transition',
                                        'border-rose-300 bg-white text-rose-700 hover:bg-rose-50 dark:border-rose-900/70 dark:bg-zinc-900 dark:text-rose-300 dark:hover:bg-rose-950/40' => $member->is_active,
                                        'border-emerald-300 bg-white text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900/70 dark:bg-zinc-900 dark:text-emerald-300 dark:hover:bg-emerald-950/40' => !$member->is_active,
                                    ])>
                                @if ($member->is_active)
                                    <flux:icon name="no-symbol" variant="solid" class="size-3.5" />
                                    Deactivate
                                @else
                                    <flux:icon name="check-circle" variant="solid" class="size-3.5" />
                                    Reactivate
                                @endif
                            </button>
                        </li>
                    @empty
                        <li class="p-10 text-center text-sm text-zinc-500">No members yet.</li>
                    @endforelse
                </ul>
            </div>
        </section>
    @endif
    </div>

    <flux:modal name="admin-confirm" class="max-w-md" @close="cancel()">
        <div class="space-y-5">
            <div class="space-y-1.5">
                <flux:heading size="lg" x-text="heading"></flux:heading>
                <flux:text x-text="body"></flux:text>
            </div>
            <flux:separator variant="subtle" />
            <div class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900
                        dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                <flux:icon name="exclamation-triangle" variant="solid" class="mt-0.5 size-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                <div>
                    <span x-show="countdown > 0">
                        Safety hold — you can confirm in <span class="font-bold" x-text="countdown"></span>
                        <span x-text="countdown === 1 ? 'second' : 'seconds'"></span>.
                    </span>
                    <span x-show="countdown === 0">Ready. Click the button to confirm.</span>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" @click="cancel()">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger"
                             icon="trash"
                             ::disabled="countdown > 0"
                             @click="confirm()">
                    <span x-show="countdown > 0">
                        <span x-text="pending.action === 'forceDeleteRoom' ? 'Wiping in ' : 'Deleting in '"></span>
                        <span x-text="countdown"></span>
                    </span>
                    <span x-show="countdown === 0"
                          x-text="pending.action === 'forceDeleteRoom' ? 'Delete forever' : 'Delete channel'"></span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
