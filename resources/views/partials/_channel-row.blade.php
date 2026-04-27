@php
    /**
     * @var \ArtisanBuild\Bonfire\Models\Room $room
     * @var int|null $currentRoomId
     */
    $isActive = $room->id === $currentRoomId;
    $hasUnread = (bool) ($room->has_unread ?? false);
    $channelUrl = route('bonfire.room.show', $room);
    $sections = $this->channelSections;
    $currentSectionId = $this->roomSectionMap[$room->id] ?? null;
@endphp
<li wire:key="channel-{{ $room->id }}-{{ $currentSectionId ?? 0 }}"
    data-channel-id="{{ $room->id }}"
    class="group relative">
    <a href="{{ $channelUrl }}"
       class="flex items-center gap-2 rounded px-2 py-1 pr-14
              {{ $isActive
                  ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100'
                  : 'text-zinc-700 hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' }}">
        @if ($room->isAnnouncements())
            <flux:icon name="megaphone" class="size-4 flex-shrink-0 text-amber-500" />
        @else
            <span class="w-4 flex-shrink-0 text-center text-zinc-400 group-hover:text-zinc-500">#</span>
        @endif
        <span class="truncate {{ $hasUnread ? 'font-semibold text-zinc-900 dark:text-zinc-100' : '' }}">
            {{ $room->name }}
        </span>
        @if (in_array($room->id, $this->activeMeetingRoomIds, true))
            <span title="Meeting in progress"
                  class="ml-auto inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600
                         dark:text-emerald-400">
                <flux:icon name="video-camera" class="size-3 animate-pulse" />
            </span>
        @elseif ($hasUnread && ! $isActive)
            <span class="ml-auto size-1.5 flex-shrink-0 rounded-full bg-sky-500"></span>
        @endif
    </a>

    <div x-data="{
            notifyKey: 'bonfire.notify.{{ $room->id }}',
            notify: localStorage.getItem('bonfire.notify.{{ $room->id }}') ?? 'all',
            setNotify(v) {
                this.notify = v;
                localStorage.setItem(this.notifyKey, v);
            },
            copyLink() {
                navigator.clipboard?.writeText('{{ $channelUrl }}');
            },
        }"
         class="pointer-events-none absolute right-1 top-1/2 flex -translate-y-1/2 items-center
                opacity-0 transition-opacity
                group-hover:pointer-events-auto group-hover:opacity-100
                focus-within:pointer-events-auto focus-within:opacity-100">
        <flux:dropdown align="end">
            <button type="button"
                    title="More actions"
                    @click.stop.prevent
                    class="rounded p-1 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900
                           dark:hover:bg-zinc-700 dark:hover:text-zinc-100">
                <flux:icon name="ellipsis-horizontal" class="size-3.5" />
            </button>

            <flux:menu>
                <flux:menu.item icon="information-circle"
                                @click="
                                    if ({{ $room->id }} === {{ $currentRoomId ?? 'null' }}) {
                                        $dispatch('modal-show', { name: 'channel-details' });
                                    } else {
                                        localStorage.setItem('bonfire.open-details', '{{ $room->id }}');
                                        window.location.href = '{{ $channelUrl }}';
                                    }
                                ">
                    Open channel details
                </flux:menu.item>
                <flux:menu.item icon="star"
                                wire:click="toggleStar({{ $room->id }})">
                    {{ $room->is_starred ? 'Unstar channel' : 'Star channel' }}
                </flux:menu.item>
                <flux:menu.separator />
                <div class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                    Notifications
                </div>
                <button type="button" @click="setNotify('all')"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm
                               hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon name="speaker-wave" class="size-4 text-emerald-600" />
                    <span class="flex-1">All messages</span>
                    <flux:icon name="check" class="size-4 text-sky-600" ::class="notify === 'all' ? '' : 'invisible'" />
                </button>
                <button type="button" @click="setNotify('mentions')"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm
                               hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon name="at-symbol" class="size-4 text-amber-500" />
                    <span class="flex-1">Only @mentions</span>
                    <flux:icon name="check" class="size-4 text-sky-600" ::class="notify === 'mentions' ? '' : 'invisible'" />
                </button>
                <button type="button" @click="setNotify('off')"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm
                               hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon name="moon" class="size-4 text-zinc-400" />
                    <span class="flex-1">Off</span>
                    <flux:icon name="check" class="size-4 text-sky-600" ::class="notify === 'off' ? '' : 'invisible'" />
                </button>
                <flux:menu.separator />
                <div class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                    Move to section
                </div>
                <flux:menu.item icon="inbox"
                                wire:click="assignRoomToSection({{ $room->id }}, null)"
                                :disabled="$currentSectionId === null">
                    Default (Channels) @if ($currentSectionId === null) ✓ @endif
                </flux:menu.item>
                @foreach ($sections as $sect)
                    <flux:menu.item icon="folder"
                                    wire:click="assignRoomToSection({{ $room->id }}, {{ $sect->id }})">
                        {{ $sect->name }} @if ($currentSectionId === $sect->id) ✓ @endif
                    </flux:menu.item>
                @endforeach
                <flux:menu.separator />
                <flux:menu.item icon="clipboard-document" @click="copyLink()">
                    Copy channel link
                </flux:menu.item>
                @if ($this->isAdmin)
                    <flux:menu.separator />
                    <flux:menu.item icon="trash"
                                    variant="danger"
                                    @click="$dispatch('bonfire-ask-delete-channel', { id: {{ $room->id }}, name: {{ Illuminate\Support\Js::from($room->name) }} })">
                        Delete channel
                    </flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>
    </div>
</li>
