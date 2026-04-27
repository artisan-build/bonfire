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
    <div class="mx-auto w-full max-w-5xl px-4 py-8
                sm:px-6
                lg:px-8">
    <h1 class="text-2xl font-semibold tracking-tight
               sm:text-3xl">
        Admin
    </h1>

    <div class="mt-6 flex gap-2 border-b border-zinc-200 dark:border-zinc-800">
        <button type="button"
                wire:click="$set('tab', 'rooms')"
                @class([
                    'border-b-2 px-3 py-2 text-sm font-medium',
                    'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' => $tab === 'rooms',
                    'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' => $tab !== 'rooms',
                ])>
            Rooms
        </button>
        <button type="button"
                wire:click="$set('tab', 'members')"
                @class([
                    'border-b-2 px-3 py-2 text-sm font-medium',
                    'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' => $tab === 'members',
                    'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' => $tab !== 'members',
                ])>
            Members
        </button>
    </div>

    @if ($tab === 'rooms')
        <section class="mt-6 space-y-6">
            <form wire:submit="createRoom"
                  class="rounded-md border border-zinc-200 p-4
                         dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    Create room
                </h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <input wire:model="newName"
                           type="text"
                           placeholder="Name"
                           class="rounded-md border border-zinc-300 bg-white p-2 text-sm
                                  dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    <input wire:model="newDescription"
                           type="text"
                           placeholder="Description (optional)"
                           class="rounded-md border border-zinc-300 bg-white p-2 text-sm
                                  dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                </div>
                <div class="mt-3 flex flex-wrap gap-4 text-xs text-zinc-700 dark:text-zinc-300">
                    <label class="flex items-center gap-2">
                        <input wire:model="newPrivate" type="checkbox"> Private
                    </label>
                    <label class="flex items-center gap-2">
                        <input wire:model="newArchived" type="checkbox"> Archived
                    </label>
                    <label class="flex items-center gap-2">
                        <input wire:model="newAnnouncements" type="checkbox"> Announcements
                    </label>
                </div>
                <div class="mt-3 flex justify-end">
                    <flux:button type="submit" variant="primary" size="sm">Create</flux:button>
                </div>
            </form>

            @if ($this->deletedRooms->isNotEmpty())
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                        Deleted channels
                        <span class="text-xs font-normal text-zinc-500">({{ $this->deletedRooms->count() }})</span>
                    </h2>
                    <p class="mt-1 text-xs text-zinc-500">
                        Restore a channel to bring back its messages and members. Delete forever is permanent.
                    </p>
                    <ul class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($this->deletedRooms as $room)
                            <li wire:key="deleted-room-{{ $room->id }}"
                                class="flex items-center gap-3 py-2">
                                <span class="w-4 text-center text-zinc-400">#</span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $room->name }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        Deleted {{ $room->deleted_at?->diffForHumans() }}
                                    </div>
                                </div>
                                <button type="button"
                                        wire:click="restoreRoom({{ $room->id }})"
                                        class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700
                                               hover:bg-emerald-200
                                               dark:bg-emerald-950/60 dark:text-emerald-300 dark:hover:bg-emerald-900/60">
                                    Restore
                                </button>
                                <button type="button"
                                        @click="ask('forceDeleteRoom', {{ $room->id }}, {{ Illuminate\Support\Js::from($room->name) }}, 5)"
                                        class="rounded-md border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700
                                               hover:bg-rose-50
                                               dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/40">
                                    Delete forever
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <ul role="list" class="space-y-4">
                @forelse ($this->rooms as $room)
                    <li wire:key="admin-room-{{ $room->id }}"
                        class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm
                               sm:grid-cols-[1fr_auto]
                               dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
                                <span class="text-zinc-400">#</span>
                                <span>{{ $room->slug }}</span>
                            </div>
                            <input value="{{ $room->name }}"
                                   wire:change="updateRoom({{ $room->id }}, 'name', $event.target.value)"
                                   class="w-full rounded-md border border-zinc-300 bg-white p-2 text-sm font-medium
                                          dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            <input value="{{ $room->description }}"
                                   wire:change="updateRoom({{ $room->id }}, 'description', $event.target.value)"
                                   placeholder="Description"
                                   class="w-full rounded-md border border-zinc-300 bg-white p-2 text-xs
                                          dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        </div>
                        <div class="flex flex-col gap-2 text-xs text-zinc-700 sm:border-l sm:border-zinc-200 sm:pl-4
                                    dark:text-zinc-300 sm:dark:border-zinc-700">
                            <label class="flex items-center gap-2">
                                <input type="checkbox"
                                       @checked($room->isPrivate())
                                       wire:change="updateRoom({{ $room->id }}, 'private', $event.target.checked)">
                                Private
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox"
                                       @checked($room->isArchived())
                                       wire:change="updateRoom({{ $room->id }}, 'archived', $event.target.checked)">
                                Archived
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox"
                                       @checked($room->isAnnouncements())
                                       wire:change="updateRoom({{ $room->id }}, 'announcements', $event.target.checked)">
                                Announcements
                            </label>
                            <button type="button"
                                    @click="ask('deleteRoom', {{ $room->id }}, {{ Illuminate\Support\Js::from($room->name) }}, 3)"
                                    class="mt-2 inline-flex items-center justify-center gap-1 rounded-md border border-rose-200
                                           px-2 py-1 text-xs font-medium text-rose-700
                                           hover:bg-rose-50
                                           dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/40">
                                <flux:icon name="trash" class="size-3" />
                                Delete
                            </button>
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500
                               dark:border-zinc-700">
                        No rooms yet.
                    </li>
                @endforelse
            </ul>
        </section>
    @else
        <section class="mt-6 space-y-4">
            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-600
                        dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-400">
                <strong class="text-zinc-800 dark:text-zinc-200">Deactivate</strong>
                blocks the member from opening any channel, downloading attachments, or deleting messages.
                Their messages and history stay intact, and
                <strong class="text-zinc-800 dark:text-zinc-200">Reactivate</strong>
                reverses it. The underlying user account is not deleted.
            </div>

            <ul role="list" class="divide-y divide-zinc-100 rounded-md border border-zinc-200
                                   dark:divide-zinc-800 dark:border-zinc-800">
                @forelse ($this->members as $member)
                    <li wire:key="admin-member-{{ $member->id }}"
                        class="flex flex-wrap items-center gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-zinc-900
                                        dark:text-zinc-100">
                                {{ $member->display_name }}
                            </div>
                            <div class="text-xs text-zinc-500">
                                {{ class_basename($member->memberable_type) }}#{{ $member->memberable_id }}
                            </div>
                        </div>
                        <select wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                class="rounded-md border border-zinc-300 bg-white p-1 text-xs
                                       dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            @foreach (\ArtisanBuild\Bonfire\Enums\BonfireRole::cases() as $role)
                                <option value="{{ $role->value }}" @selected($member->role === $role)>
                                    {{ ucfirst($role->value) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button"
                                wire:click="toggleActive({{ $member->id }})"
                                @class([
                                    'rounded-md px-2 py-1 text-xs font-medium',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $member->is_active,
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => !$member->is_active,
                                ])>
                            {{ $member->is_active ? 'Deactivate' : 'Reactivate' }}
                        </button>
                    </li>
                @empty
                    <li class="p-6 text-center text-sm text-zinc-500">No members yet.</li>
                @endforelse
            </ul>
        </section>
    @endif
    </div>

    <flux:modal name="admin-confirm" class="max-w-md" @close="cancel()">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" x-text="heading"></flux:heading>
                <flux:text class="mt-2" x-text="body"></flux:text>
            </div>
            <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800
                        dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                <span x-show="countdown > 0">
                    Safety hold — you can confirm in <span x-text="countdown"></span>
                    <span x-text="countdown === 1 ? 'second' : 'seconds'"></span>.
                </span>
                <span x-show="countdown === 0">Ready. Click the button to confirm.</span>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" @click="cancel()">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger"
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
