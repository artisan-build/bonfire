@php
    $currentRoom = request()->route('room');
    $currentRoomId = $currentRoom instanceof \ArtisanBuild\Bonfire\Models\Room ? $currentRoom->id : null;
    $all = collect($this->visibleRooms)->reject(fn ($room) => str_starts_with($room->slug, 'dm-'));
    $starred = $all->where('is_starred', true);
    $unstarred = $all->where('is_starred', false);
    [$activeRooms, $archivedRooms] = $unstarred->partition(fn ($room) => ! $room->isArchived());
    $dms = $this->directMessageMembers;

    $userSections = $this->channelSections;
    $roomSectionMap = $this->roomSectionMap;
    $roomsBySection = $activeRooms->groupBy(fn ($room) => $roomSectionMap[$room->id] ?? 0);
    $unsectionedRooms = $roomsBySection->get(0, collect());
@endphp

<div x-data="{
         sections: JSON.parse(localStorage.getItem('bonfire.sidebar') ?? '{}'),
         toggle(key) {
             this.sections[key] = ! this.isOpen(key);
             localStorage.setItem('bonfire.sidebar', JSON.stringify(this.sections));
         },
         isOpen(key) {
             return this.sections[key] !== false;
         },
         deleteTarget: { id: null, name: '' },
         deleteCountdown: 0,
         deleteTimer: null,
         askDelete(detail) {
             this.deleteTarget = { id: detail.id, name: detail.name };
             this.deleteCountdown = 3;
             clearInterval(this.deleteTimer);
             this.deleteTimer = setInterval(() => {
                 this.deleteCountdown = Math.max(0, this.deleteCountdown - 1);
                 if (this.deleteCountdown === 0) {
                     clearInterval(this.deleteTimer);
                     this.deleteTimer = null;
                 }
             }, 1000);
             this.$dispatch('modal-show', { name: 'delete-channel' });
         },
         confirmDelete() {
             if (this.deleteCountdown > 0 || this.deleteTarget.id === null) return;
             const id = this.deleteTarget.id;
             const currentRoomEl = document.querySelector('[data-bonfire-room-id]');
             const currentRoomId = currentRoomEl ? Number(currentRoomEl.dataset.bonfireRoomId) : null;
             this.$dispatch('modal-close', { name: 'delete-channel' });
             this.cancelDelete();
             try {
                 this.$wire.deleteChannel(id, currentRoomId);
             } catch (err) {
                 console.error('Delete failed', err);
             }
         },
         cancelDelete() {
             clearInterval(this.deleteTimer);
             this.deleteTimer = null;
             this.deleteCountdown = 0;
             this.deleteTarget = { id: null, name: '' };
         },
     }"
     @bonfire-ask-delete-channel.window="askDelete($event.detail)"
     class="flex min-h-0 flex-1 flex-col text-sm">

    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto pt-2">

        @if ($starred->isNotEmpty())
            <section class="px-2">
                <button type="button"
                        @click="toggle('starred')"
                        class="group flex w-full items-center gap-1 rounded px-2 py-1 text-xs font-semibold uppercase
                               tracking-wider text-zinc-500 hover:bg-zinc-200/60 hover:text-zinc-900
                               dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="chevron-down"
                               class="size-3 transition"
                               ::class="{ '-rotate-90': ! isOpen('starred') }" />
                    <flux:icon name="star" class="size-3.5" />
                    <span class="ml-0.5 normal-case tracking-normal">Starred</span>
                </button>
                <div x-show="isOpen('starred')">
                    <ul class="flex flex-col">
                        @foreach ($starred as $room)
                            <li wire:key="starred-{{ $room->id }}">
                                <a href="{{ route('bonfire.room.show', $room) }}"
                                   class="group flex items-center gap-2 rounded px-2 py-1
                                          {{ $room->id === $currentRoomId
                                              ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100'
                                              : 'text-zinc-700 hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' }}">
                                    @if ($room->isAnnouncements())
                                        <flux:icon name="megaphone" class="size-4 flex-shrink-0 text-amber-500" />
                                    @else
                                        <span class="w-4 flex-shrink-0 text-center text-zinc-400">#</span>
                                    @endif
                                    <span class="truncate {{ $room->has_unread ? 'font-semibold text-zinc-900 dark:text-zinc-100' : '' }}">
                                        {{ $room->name }}
                                    </span>
                                    <button type="button"
                                            wire:click.stop.prevent="toggleStar({{ $room->id }})"
                                            class="ml-auto text-amber-500 hover:text-amber-600">
                                        <flux:icon name="star" variant="solid" class="size-3.5" />
                                    </button>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <div class="mx-3 my-3 h-px bg-zinc-200 dark:bg-zinc-800"></div>
        @endif

        <div class="px-2 pb-1"
             x-data="{ creating: false, newName: '' }">
            <button x-show="! creating" type="button"
                    @click="creating = true; $nextTick(() => $refs.sectionInput?.focus())"
                    class="flex w-full items-center gap-1 rounded px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-500
                           hover:bg-zinc-200/60 hover:text-zinc-900
                           dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                <flux:icon name="plus" class="size-3" />
                <span>New section</span>
            </button>
            <form x-show="creating" x-cloak
                  @submit.prevent="if (newName.trim()) { $wire.createSection(newName); newName = ''; creating = false; }"
                  class="flex items-center gap-1 px-1">
                <input x-ref="sectionInput"
                       x-model="newName"
                       @keydown.escape="creating = false; newName = ''"
                       placeholder="Section name"
                       class="flex-1 rounded border border-zinc-300 bg-white px-1.5 py-0.5 text-xs
                              focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500
                              dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                <button type="submit"
                        class="rounded bg-sky-600 px-1.5 py-0.5 text-[10px] font-semibold text-white hover:bg-sky-700">
                    Add
                </button>
            </form>
        </div>

        @foreach ($userSections as $section)
            @php
                $sectionRooms = $roomsBySection->get($section->id, collect());
                $sectionKey = 'section-'.$section->id;
            @endphp
            <section class="group/section px-2 pb-2"
                     x-data="{ editing: false, editName: @js($section->name) }">
                <div class="flex items-center gap-1">
                    <button type="button"
                            @click="toggle('{{ $sectionKey }}')"
                            class="group flex flex-1 items-center gap-1 rounded px-2 py-1 text-xs font-semibold uppercase
                                   tracking-wider text-zinc-500 hover:bg-zinc-200/60 hover:text-zinc-900
                                   dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                        <flux:icon name="chevron-down"
                                   class="size-3 transition"
                                   ::class="{ '-rotate-90': ! isOpen('{{ $sectionKey }}') }" />
                        <span x-show="! editing" class="ml-1 truncate normal-case tracking-normal"
                              x-text="editName">{{ $section->name }}</span>
                        <span x-show="! editing" class="text-zinc-400">{{ $sectionRooms->count() }}</span>
                        <input x-show="editing" x-cloak
                               x-model="editName"
                               @click.stop
                               @keydown.enter.prevent="editing = false; $wire.renameSection({{ $section->id }}, editName)"
                               @keydown.escape.prevent="editing = false; editName = @js($section->name)"
                               @blur="editing = false; $wire.renameSection({{ $section->id }}, editName)"
                               class="w-full rounded border border-sky-400 bg-white px-1 py-0 text-xs normal-case tracking-normal
                                      focus:outline-none focus:ring-1 focus:ring-sky-500
                                      dark:border-sky-600 dark:bg-zinc-900 dark:text-zinc-100">
                    </button>
                    <div class="pointer-events-none opacity-0 transition-opacity
                                group-hover/section:pointer-events-auto group-hover/section:opacity-100">
                        <flux:dropdown align="end">
                            <button type="button"
                                    title="Section actions"
                                    class="rounded p-1 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900
                                           dark:hover:bg-zinc-700 dark:hover:text-zinc-100">
                                <flux:icon name="ellipsis-horizontal" class="size-3.5" />
                            </button>
                            <flux:menu>
                                <flux:menu.item icon="pencil-square"
                                                @click="editing = true; $nextTick(() => $root.querySelector('input')?.focus())">
                                    Rename section
                                </flux:menu.item>
                                <flux:menu.item icon="trash"
                                                variant="danger"
                                                wire:click="deleteSection({{ $section->id }})"
                                                wire:confirm="Delete this section? Channels will move back to Channels.">
                                    Delete section
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
                <div x-show="isOpen('{{ $sectionKey }}')">
                    <ul class="flex flex-col">
                        @forelse ($sectionRooms as $room)
                            @include('bonfire::partials._channel-row', ['room' => $room, 'currentRoomId' => $currentRoomId])
                        @empty
                            <li class="px-2 py-1 text-xs text-zinc-400">Empty — drop channels here via the "Move to section" menu.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        @endforeach

        <section class="px-2">
            <button type="button"
                    @click="toggle('channels')"
                    class="group flex w-full items-center gap-1 rounded px-2 py-1 text-xs font-semibold uppercase
                           tracking-wider text-zinc-500 hover:bg-zinc-200/60 hover:text-zinc-900
                           dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                <flux:icon name="chevron-down"
                           class="size-3 transition"
                           ::class="{ '-rotate-90': ! isOpen('channels') }" />
                <span class="ml-1 normal-case tracking-normal">Channels</span>
            </button>
            <div x-show="isOpen('channels')">
                <ul x-data="{
                        order: JSON.parse(localStorage.getItem('bonfire.channel.order') || '[]'),
                        draggingId: null,
                        observer: null,
                        init() {
                            this.applyOrder();
                            this.observer = new MutationObserver(() => {
                                this.observer?.disconnect();
                                this.applyOrder();
                                queueMicrotask(() => this.observer?.observe(this.$el, { childList: true }));
                            });
                            this.observer.observe(this.$el, { childList: true });
                        },
                        destroy() { this.observer?.disconnect(); },
                        applyOrder() {
                            if (this.order.length === 0) return;
                            const ul = this.$el;
                            const items = Array.from(ul.querySelectorAll('[data-channel-id]'));
                            if (items.length === 0) return;
                            const rank = new Map(this.order.map((id, i) => [String(id), i]));
                            const currentOrder = items.map(el => el.dataset.channelId);
                            const sorted = [...items].sort((a, b) => {
                                const ai = rank.has(a.dataset.channelId) ? rank.get(a.dataset.channelId) : 9999;
                                const bi = rank.has(b.dataset.channelId) ? rank.get(b.dataset.channelId) : 9999;
                                return ai - bi;
                            });
                            const sortedOrder = sorted.map(el => el.dataset.channelId);
                            if (currentOrder.every((id, i) => id === sortedOrder[i])) return;
                            const addBtn = ul.querySelector('[data-channel-add]');
                            sorted.forEach(item => ul.insertBefore(item, addBtn || null));
                        },
                        onDragStart(e, id) {
                            this.draggingId = id;
                            e.dataTransfer.effectAllowed = 'move';
                            e.currentTarget.classList.add('opacity-40');
                        },
                        onDragOver(e, id) {
                            e.preventDefault();
                            if (this.draggingId === null || String(id) === String(this.draggingId)) return;
                            const ul = this.$el;
                            const from = ul.querySelector('[data-channel-id=\'' + this.draggingId + '\']');
                            const to = ul.querySelector('[data-channel-id=\'' + id + '\']');
                            if (!from || !to) return;
                            const rect = to.getBoundingClientRect();
                            const middle = rect.top + rect.height / 2;
                            if (e.clientY < middle) {
                                if (from.nextSibling !== to) ul.insertBefore(from, to);
                            } else {
                                if (to.nextSibling !== from) ul.insertBefore(from, to.nextSibling);
                            }
                        },
                        onDragEnd(e) {
                            e.currentTarget.classList.remove('opacity-40');
                            this.draggingId = null;
                            const ul = this.$el;
                            const ids = Array.from(ul.querySelectorAll('[data-channel-id]')).map(el => el.dataset.channelId);
                            this.order = ids;
                            localStorage.setItem('bonfire.channel.order', JSON.stringify(ids));
                        },
                    }"
                    class="flex flex-col">
                    @forelse ($unsectionedRooms as $room)
                        @php
                            $isActive = $room->id === $currentRoomId;
                            $hasUnread = (bool) ($room->has_unread ?? false);
                            $channelUrl = route('bonfire.room.show', $room);
                            $currentSectionId = $roomSectionMap[$room->id] ?? null;
                            $sectionsForMove = $userSections;
                        @endphp
                        <li wire:key="channel-{{ $room->id }}"
                            data-channel-id="{{ $room->id }}"
                            draggable="true"
                            @dragstart="onDragStart($event, {{ $room->id }})"
                            @dragover="onDragOver($event, {{ $room->id }})"
                            @dragend="onDragEnd($event)"
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
                                    move(dir) {
                                        const ul = document.querySelector('ul [data-channel-id=\'{{ $room->id }}\']')?.parentElement;
                                        if (! ul) return;
                                        const items = Array.from(ul.querySelectorAll('[data-channel-id]'));
                                        const idx = items.findIndex(el => el.dataset.channelId === '{{ $room->id }}');
                                        if (idx === -1) return;
                                        const swapIdx = dir === 'up' ? idx - 1 : idx + 1;
                                        if (swapIdx < 0 || swapIdx >= items.length) return;
                                        const el = items[idx];
                                        const target = items[swapIdx];
                                        if (dir === 'up') ul.insertBefore(el, target);
                                        else ul.insertBefore(el, target.nextSibling);
                                        const ids = Array.from(ul.querySelectorAll('[data-channel-id]')).map(x => x.dataset.channelId);
                                        localStorage.setItem('bonfire.channel.order', JSON.stringify(ids));
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
                                        <flux:menu.item icon="arrow-up" @click="move('up')">
                                            Move up
                                        </flux:menu.item>
                                        <flux:menu.item icon="arrow-down" @click="move('down')">
                                            Move down
                                        </flux:menu.item>
                                        @if ($sectionsForMove->isNotEmpty())
                                            <flux:menu.separator />
                                            <div class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                                                Move to section
                                            </div>
                                            @foreach ($sectionsForMove as $sect)
                                                <flux:menu.item icon="folder"
                                                                wire:click="assignRoomToSection({{ $room->id }}, {{ $sect->id }})">
                                                    {{ $sect->name }}
                                                </flux:menu.item>
                                            @endforeach
                                        @endif
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
                    @empty
                        <li class="px-2 py-1 text-xs text-zinc-500">No channels yet.</li>
                    @endforelse
                    @if ($this->isAdmin)
                        <li data-channel-add>
                            <button type="button"
                                    @click="$dispatch('modal-show', { name: 'create-channel' })"
                                    class="flex w-full items-center gap-2 rounded px-2 py-1 text-zinc-500
                                           hover:bg-zinc-200/60 hover:text-zinc-900
                                           dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                <flux:icon name="plus" class="size-4" />
                                <span>Add channels</span>
                            </button>
                        </li>
                    @endif
                </ul>
            </div>
        </section>

        @if ($archivedRooms->isNotEmpty())
            <section class="mt-3 px-2">
                <button type="button"
                        @click="toggle('archived')"
                        class="group flex w-full items-center gap-1 rounded px-2 py-1 text-xs font-semibold uppercase
                               tracking-wider text-zinc-400 hover:bg-zinc-200/60 hover:text-zinc-900
                               dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="chevron-down"
                               class="size-3 transition"
                               ::class="{ '-rotate-90': ! isOpen('archived') }" />
                    <span class="ml-1 normal-case tracking-normal">Archived</span>
                </button>
                <div x-show="isOpen('archived')">
                    <ul class="flex flex-col">
                        @foreach ($archivedRooms as $room)
                            @php
                                $isActive = $room->id === $currentRoomId;
                            @endphp
                            <li wire:key="archived-{{ $room->id }}">
                                <a href="{{ route('bonfire.room.show', $room) }}"
                                   class="group flex items-center gap-2 rounded px-2 py-1
                                          {{ $isActive
                                              ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100'
                                              : 'text-zinc-500 hover:bg-zinc-200/60 hover:text-zinc-800 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-300' }}">
                                    <flux:icon name="archive-box" class="size-4 flex-shrink-0" />
                                    <span class="truncate">{{ $room->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        <div class="mx-3 my-3 h-px bg-zinc-200 dark:bg-zinc-800"></div>

        <section class="px-2">
            <button type="button"
                    @click="toggle('dms')"
                    class="group flex w-full items-center gap-1 rounded px-2 py-1 text-xs font-semibold uppercase
                           tracking-wider text-zinc-500 hover:bg-zinc-200/60 hover:text-zinc-900
                           dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                <flux:icon name="chevron-down"
                           class="size-3 transition"
                           ::class="{ '-rotate-90': ! isOpen('dms') }" />
                <flux:icon name="at-symbol" class="size-3.5" />
                <span class="ml-0.5 normal-case tracking-normal">Direct messages</span>
            </button>
            <div x-show="isOpen('dms')">
                <ul class="flex flex-col">
                    @forelse ($dms as $member)
                        <li wire:key="dm-{{ $member->id }}">
                            <button type="button"
                                    wire:click="openDm({{ $member->id }})"
                                    class="flex w-full items-center gap-2 rounded px-2 py-1 text-left
                                           text-zinc-700 hover:bg-zinc-200/60 hover:text-zinc-900
                                           dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                <span class="relative flex-shrink-0">
                                    <img src="{{ $member->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($member->display_name) }}"
                                         alt="" class="size-5 rounded bg-zinc-200 dark:bg-zinc-700">
                                    <span class="absolute -right-0.5 -bottom-0.5 size-2 rounded-full border-2 border-zinc-50
                                                 {{ $member->is_away ? 'bg-amber-400' : 'bg-emerald-500' }}
                                                 dark:border-zinc-900"></span>
                                </span>
                                <span class="flex-1 truncate">{{ $member->display_name }}</span>
                                @if (in_array($member->id, $this->memberIdsInCall, true))
                                    <span title="In a call"
                                          class="flex items-center gap-0.5 text-[10px] text-emerald-600 dark:text-emerald-400">
                                        <flux:icon name="phone" class="size-3 animate-pulse" />
                                    </span>
                                @endif
                                @if ($member->status_emoji)
                                    <span class="text-xs">{{ $member->status_emoji }}</span>
                                @endif
                            </button>
                        </li>
                    @empty
                        <li class="px-2 py-1 text-xs text-zinc-400 dark:text-zinc-500">No one else here yet.</li>
                    @endforelse
                    <li>
                        <button type="button"
                                class="flex w-full items-center gap-2 rounded px-2 py-1 text-zinc-500
                                       hover:bg-zinc-200/60 hover:text-zinc-900
                                       dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                            <flux:icon name="plus" class="size-4" />
                            <span>Invite people</span>
                        </button>
                    </li>
                </ul>
            </div>
        </section>

        <div class="pb-4"></div>
    </div>

    @if ($this->isAdmin)
        <flux:modal name="delete-channel" class="max-w-md" @close="cancelDelete()">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Delete channel?</flux:heading>
                    <flux:text class="mt-2">
                        <span x-text="'#' + (deleteTarget.name || '')"></span>
                        will be removed from the sidebar immediately. You can restore it from the admin panel.
                    </flux:text>
                </div>
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800
                            dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                    <span x-show="deleteCountdown > 0">
                        Safety hold — you can confirm in <span x-text="deleteCountdown"></span>
                        <span x-text="deleteCountdown === 1 ? 'second' : 'seconds'"></span>.
                    </span>
                    <span x-show="deleteCountdown === 0">Ready. Click delete to confirm.</span>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" @click="cancelDelete()">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger"
                                 ::disabled="deleteCountdown > 0"
                                 @click="confirmDelete()">
                        <span x-show="deleteCountdown > 0" x-text="'Delete in ' + deleteCountdown"></span>
                        <span x-show="deleteCountdown === 0">Delete channel</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="create-channel" class="max-w-lg">
            <form wire:submit="createChannel" class="space-y-5">
                <div>
                    <flux:heading size="lg">Create a channel</flux:heading>
                    <flux:text class="mt-1">
                        Channels are where your team communicates. They're best when organized around a topic.
                    </flux:text>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Name
                        </label>
                        <div class="flex items-center gap-2 rounded-md border border-zinc-300 bg-white px-2
                                    focus-within:border-zinc-500 focus-within:ring-1 focus-within:ring-zinc-500
                                    dark:border-zinc-700 dark:bg-zinc-900">
                            <span class="text-zinc-400">#</span>
                            <input type="text"
                                   wire:model="newChannelName"
                                   placeholder="e.g. plan-budget"
                                   required
                                   class="w-full border-0 bg-transparent py-2 text-sm focus:outline-none focus:ring-0
                                          dark:text-zinc-100" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Description <span class="text-zinc-400">(optional)</span>
                        </label>
                        <input type="text"
                               wire:model="newChannelDescription"
                               placeholder="What's this channel about?"
                               class="w-full rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm
                                      focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                      dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                    </div>

                    <div x-data="{
                            memberSearch: '',
                            allMembers: @js($dms->map(fn($m) => ['id' => $m->id, 'display_name' => $m->display_name, 'avatar_url' => $m->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($m->display_name)])->values()->all()),
                            get filteredMembers() {
                                const q = this.memberSearch.trim().toLowerCase();
                                if (q === '') return this.allMembers;
                                return this.allMembers.filter(m => m.display_name.toLowerCase().includes(q));
                            },
                            isSelected(id) {
                                return this.$wire.newChannelMemberIds.map(Number).includes(Number(id));
                            },
                            toggleMember(id) {
                                const list = this.$wire.newChannelMemberIds.map(Number);
                                const idx = list.indexOf(Number(id));
                                if (idx >= 0) list.splice(idx, 1);
                                else list.push(Number(id));
                                this.$wire.set('newChannelMemberIds', list);
                            },
                        }">
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Add people <span class="text-zinc-400">(optional — you'll be added automatically)</span>
                        </label>
                        <div class="rounded-md border border-zinc-300 dark:border-zinc-700">
                            <div class="border-b border-zinc-200 p-1 dark:border-zinc-700">
                                <input type="search"
                                       x-model="memberSearch"
                                       placeholder="Search people"
                                       class="h-7 w-full rounded border-0 bg-transparent px-2 text-xs
                                              focus:outline-none focus:ring-0
                                              dark:text-zinc-100" />
                            </div>
                            <ul class="max-h-40 overflow-y-auto p-1">
                                <template x-for="m in filteredMembers" :key="m.id">
                                    <li>
                                        <button type="button"
                                                @click="toggleMember(m.id)"
                                                class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm
                                                       hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                            <input type="checkbox"
                                                   :checked="isSelected(m.id)"
                                                   class="size-3.5 rounded border-zinc-300 text-sky-600
                                                          focus:ring-sky-500
                                                          dark:border-zinc-600 dark:bg-zinc-900"
                                                   @click.stop="toggleMember(m.id)" />
                                            <img :src="m.avatar_url" alt="" class="size-5 flex-shrink-0 rounded bg-zinc-200 dark:bg-zinc-700">
                                            <span x-text="m.display_name" class="truncate"></span>
                                        </button>
                                    </li>
                                </template>
                                <template x-if="filteredMembers.length === 0">
                                    <li class="px-2 py-2 text-xs text-zinc-500">No matches.</li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-md border border-zinc-200 p-3
                                dark:border-zinc-700">
                        <div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                Make private
                            </div>
                            <div class="text-xs text-zinc-500">
                                Only specific people will see this channel.
                            </div>
                        </div>
                        <input type="checkbox"
                               wire:model="newChannelPrivate"
                               class="size-4 rounded border-zinc-300 text-sky-600
                                      focus:ring-sky-500
                                      dark:border-zinc-600 dark:bg-zinc-900" />
                    </div>

                    <div class="flex items-center justify-between rounded-md border border-zinc-200 p-3
                                dark:border-zinc-700">
                        <div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                Announcements only
                            </div>
                            <div class="text-xs text-zinc-500">
                                Only moderators and admins can post here.
                            </div>
                        </div>
                        <input type="checkbox"
                               wire:model="newChannelAnnouncements"
                               class="size-4 rounded border-zinc-300 text-sky-600
                                      focus:ring-sky-500
                                      dark:border-zinc-600 dark:bg-zinc-900" />
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">
                        Create channel
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <flux:modal name="new-message" class="max-w-lg">
        <div x-data="{
                    query: '',
                    channels: @js($activeRooms->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->values()->all()),
                    members: @js($dms->map(fn($m) => ['id' => $m->id, 'display_name' => $m->display_name, 'avatar_url' => $m->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($m->display_name)])->values()->all()),
                    get filteredChannels() {
                        const q = this.query.trim().replace(/^#/, '').toLowerCase();
                        if (q === '') return this.channels.slice(0, 6);
                        return this.channels.filter(c => c.name.toLowerCase().includes(q)).slice(0, 6);
                    },
                    get filteredMembers() {
                        const q = this.query.trim().replace(/^@/, '').toLowerCase();
                        if (q === '') return this.members.slice(0, 6);
                        return this.members.filter(m => m.display_name.toLowerCase().includes(q)).slice(0, 6);
                    },
                    goChannel(slug) {
                        this.$dispatch('modal-close', { name: 'new-message' });
                        window.location.href = '{{ url(config('bonfire.route_prefix', 'bonfire')) }}/' + slug;
                    },
                    goMember(id) {
                        this.$dispatch('modal-close', { name: 'new-message' });
                        this.$wire.openDm(id);
                    },
                }"
             class="space-y-4">
            <div>
                <flux:heading size="lg">New message</flux:heading>
                <flux:text class="mt-1">
                    Start a conversation with a channel or teammate.
                </flux:text>
            </div>

            <flux:input x-model="query"
                        placeholder="#channel, @person"
                        icon="magnifying-glass"
                        autofocus />

            <div class="max-h-80 overflow-y-auto">
                <template x-if="filteredChannels.length > 0">
                    <div>
                        <div class="px-1 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500
                                    dark:text-zinc-400">Channels</div>
                        <ul class="flex flex-col">
                            <template x-for="channel in filteredChannels" :key="'c-' + channel.id">
                                <li>
                                    <button type="button"
                                            @click="goChannel(channel.slug)"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm
                                                   hover:bg-zinc-100
                                                   dark:hover:bg-zinc-800">
                                        <span class="w-4 flex-shrink-0 text-center text-zinc-400">#</span>
                                        <span x-text="channel.name" class="truncate"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="filteredMembers.length > 0">
                    <div>
                        <div class="px-1 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500
                                    dark:text-zinc-400">People</div>
                        <ul class="flex flex-col">
                            <template x-for="member in filteredMembers" :key="'m-' + member.id">
                                <li>
                                    <button type="button"
                                            @click="goMember(member.id)"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm
                                                   hover:bg-zinc-100
                                                   dark:hover:bg-zinc-800">
                                        <img :src="member.avatar_url" alt="" class="size-5 flex-shrink-0 rounded bg-zinc-200 dark:bg-zinc-700">
                                        <span x-text="member.display_name" class="truncate"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="filteredChannels.length === 0 && filteredMembers.length === 0">
                    <div class="px-2 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        No matches.
                    </div>
                </template>
            </div>
        </div>
    </flux:modal>
</div>
