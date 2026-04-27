@php
    $isDm = str_starts_with((string) $room->slug, 'dm-');
    $dmPartner = null;
    if ($isDm) {
        $currentMember = \ArtisanBuild\Bonfire\Facades\Bonfire::memberFor(auth()->user());
        $dmPartner = $room->members()
            ->when($currentMember !== null, fn ($q) => $q->where('bonfire_members.id', '!=', $currentMember->id))
            ->first();
    }
    $displayName = $isDm && $dmPartner ? $dmPartner->display_name : $room->name;
@endphp

<div class="flex h-full min-h-0 flex-1 flex-col"
     data-bonfire-room-id="{{ $room->id }}"
     x-data="{
         init() {
             const flag = localStorage.getItem('bonfire.open-details');
             if (flag === '{{ $room->id }}') {
                 localStorage.removeItem('bonfire.open-details');
                 this.$nextTick(() => this.$dispatch('modal-show', { name: 'channel-details' }));
             }
         },
     }">
    @php
        $channelMembers = $this->channelMembers;
        $memberPreview = $channelMembers->take(3);
        $memberCount = $channelMembers->count();
    @endphp

    <header x-data="{
                showSearch: false,
                search: '',
                toggleSearch() {
                    this.showSearch = ! this.showSearch;
                    if (this.showSearch) {
                        this.$nextTick(() => this.$refs.searchInput?.focus());
                    } else {
                        this.search = '';
                        Livewire.dispatch('bonfire-search', { value: '' });
                    }
                },
                runSearch() {
                    Livewire.dispatch('bonfire-search', { value: this.search });
                },
            }"
            class="flex h-12 flex-shrink-0 items-center gap-3 border-b border-zinc-200 px-4
                   dark:border-zinc-800">
        @unless ($isDm)
            <button type="button"
                    wire:click="toggleStar"
                    title="{{ $this->isStarred ? 'Unstar this channel' : 'Star this channel' }}"
                    class="rounded p-1 {{ $this->isStarred ? 'text-amber-500' : 'text-zinc-400' }} hover:bg-zinc-100 hover:text-amber-500
                           dark:hover:bg-zinc-800">
                <flux:icon name="star" :variant="$this->isStarred ? 'solid' : 'outline'" class="size-4" />
            </button>
        @endunless

        <div class="flex min-w-0 items-center gap-2">
            @if ($isDm && $dmPartner)
                <span class="relative flex-shrink-0">
                    <img src="{{ $dmPartner->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($dmPartner->display_name) }}"
                         alt="" class="size-6 rounded bg-zinc-200 dark:bg-zinc-700">
                    <span class="absolute -right-0.5 -bottom-0.5 size-2 rounded-full border-2 border-white bg-emerald-500
                                 dark:border-zinc-950"></span>
                </span>
            @elseif ($room->isAnnouncements())
                <flux:icon name="megaphone" class="size-4 flex-shrink-0 text-amber-500" />
            @elseif ($room->isArchived())
                <flux:icon name="archive-box" class="size-4 flex-shrink-0 text-zinc-500" />
            @else
                <span class="text-zinc-500 dark:text-zinc-400">#</span>
            @endif
            <h1 class="truncate text-base font-semibold text-zinc-900 dark:text-zinc-100">
                {{ $displayName }}
            </h1>
            @if (! $isDm && $room->description)
                <span class="hidden truncate border-l border-zinc-200 pl-3 text-sm text-zinc-500
                             lg:inline
                             dark:border-zinc-800 dark:text-zinc-400">
                    {{ $room->description }}
                </span>
            @endif
        </div>

        <div class="ml-auto flex items-center gap-4">
            <div x-show="showSearch"
                 x-transition.opacity.duration.100ms
                 class="relative"
                 style="display: none;">
                <flux:icon name="magnifying-glass"
                           class="absolute left-2 top-1/2 size-3.5 -translate-y-1/2 text-zinc-400" />
                <input type="search"
                       x-ref="searchInput"
                       x-model.debounce.200ms="search"
                       @input.debounce.200ms="runSearch()"
                       @keydown.escape="toggleSearch()"
                       placeholder="Search this channel"
                       class="h-7 w-56 rounded-md border border-zinc-300 bg-white pl-7 pr-2 text-sm
                              focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                              dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
            </div>

            @unless ($isDm)
                <button type="button"
                        title="{{ $memberCount }} {{ \Illuminate\Support\Str::plural('member', $memberCount) }}"
                        @click="$dispatch('modal-show', { name: 'channel-details' })"
                        class="flex items-center gap-1.5 rounded-md border border-zinc-200 px-2 py-1
                               hover:bg-zinc-100
                               dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span class="flex -space-x-1.5">
                        @foreach ($memberPreview as $m)
                            <img src="{{ $m->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($m->display_name) }}"
                                 alt="{{ $m->display_name }}"
                                 class="size-5 rounded bg-zinc-200 ring-2 ring-white
                                        dark:bg-zinc-700 dark:ring-zinc-900">
                        @endforeach
                    </span>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $memberCount }}</span>
                </button>
            @endunless

            <div class="flex items-center gap-1 text-zinc-500"
                 x-data="{
                     notifyKey: 'bonfire.notify.{{ $room->id }}',
                     notify: localStorage.getItem('bonfire.notify.{{ $room->id }}') ?? 'all',
                     setNotify(v) {
                         this.notify = v;
                         localStorage.setItem(this.notifyKey, v);
                         this.$refs.bell?.blur();
                     },
                 }">
                <flux:dropdown align="end">
                    <button type="button"
                            x-ref="bell"
                            title="Notification preferences"
                            class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                                   dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                        <span x-show="notify === 'all'">
                            <flux:icon name="speaker-wave" class="size-4 text-emerald-600" />
                        </span>
                        <span x-show="notify === 'mentions'">
                            <flux:icon name="at-symbol" class="size-4 text-amber-500" />
                        </span>
                        <span x-show="notify === 'off'">
                            <flux:icon name="moon" class="size-4 text-zinc-400" />
                        </span>
                    </button>
                    <flux:menu>
                        <div class="px-2 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            Notifications
                        </div>
                        <button type="button" @click="setNotify('all')"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm
                                       hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-800">
                            <flux:icon name="speaker-wave" class="size-4 text-emerald-600" />
                            <span class="flex-1">All new messages</span>
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
                    </flux:menu>
                </flux:dropdown>

                @if ($isDm && $dmPartner)
                    <button type="button"
                            title="Call {{ $dmPartner->display_name }}"
                            data-room-id="{{ $room->id }}"
                            data-member-id="{{ $dmPartner->id }}"
                            data-name="{{ $dmPartner->display_name }}"
                            data-avatar="{{ $dmPartner->avatar_url ?? '' }}"
                            @click="$dispatch('bonfire-start-call', {
                                roomId: Number($event.currentTarget.dataset.roomId),
                                memberId: Number($event.currentTarget.dataset.memberId),
                                name: $event.currentTarget.dataset.name || '',
                                avatar: $event.currentTarget.dataset.avatar || '',
                            })"
                            class="rounded p-1.5 text-emerald-600 hover:bg-emerald-50
                                   dark:text-emerald-400 dark:hover:bg-emerald-950/30">
                        <flux:icon name="phone" class="size-4" />
                    </button>
                @else
                    <button type="button"
                            title="Start or join meeting in this channel"
                            data-room-id="{{ $room->id }}"
                            data-room-name="{{ $room->name }}"
                            @click="$dispatch('bonfire-join-meeting', {
                                roomId: Number($event.currentTarget.dataset.roomId),
                                roomName: $event.currentTarget.dataset.roomName,
                            })"
                            class="rounded p-1.5 text-emerald-600 hover:bg-emerald-50
                                   dark:text-emerald-400 dark:hover:bg-emerald-950/30">
                        <flux:icon name="video-camera" class="size-4" />
                    </button>
                @endif

                <button type="button"
                        title="Search in channel"
                        @click="toggleSearch()"
                        :class="showSearch ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : ''"
                        class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                               dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="magnifying-glass" class="size-4" />
                </button>

                <flux:dropdown align="end">
                    <button type="button"
                            title="More"
                            class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                                   dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                        <flux:icon name="ellipsis-vertical" class="size-4" />
                    </button>

                <flux:menu>
                    <flux:menu.item icon="information-circle"
                                    @click="$dispatch('modal-show', { name: 'channel-details' })">
                        Open channel details
                    </flux:menu.item>
                    <flux:menu.item icon="star" wire:click="toggleStar">
                        {{ $this->isStarred ? 'Unstar channel' : 'Star channel' }}
                    </flux:menu.item>
                    <flux:menu.item icon="bell">Edit notifications</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item icon="clipboard-document"
                                    x-on:click="navigator.clipboard?.writeText('{{ route('bonfire.room.show', $room) }}')">
                        Copy channel link
                    </flux:menu.item>
                    <flux:menu.item icon="magnifying-glass" @click="toggleSearch()">Search in channel</flux:menu.item>
                    @if ($room->isPrivate() && ! $isDm)
                        <flux:menu.separator />
                        <flux:menu.item icon="arrow-right-start-on-rectangle"
                                        variant="danger"
                                        wire:click="leaveChannel"
                                        wire:confirm="Leave this channel?">
                            Leave channel
                        </flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
        </div>
    </header>

    <div class="relative flex min-h-0 flex-1">
        <div class="flex min-h-0 flex-1 flex-col">
            <livewire:bonfire::message-list :room="$room" wire:key="room-{{ $room->id }}-list" />

            <div class="flex-shrink-0 border-t border-zinc-200 px-4 py-3
                        dark:border-zinc-800">
                @if ($this->canPost)
                    <livewire:bonfire::message-composer
                        :room="$room"
                        wire:key="room-{{ $room->id }}-composer" />
                @elseif ($room->isArchived())
                    <p class="rounded-md bg-zinc-100 p-3 text-center text-sm text-zinc-600
                              dark:bg-zinc-900 dark:text-zinc-400">
                        This room is archived and read-only.
                    </p>
                @elseif ($room->isAnnouncements())
                    <p class="rounded-md bg-amber-50 p-3 text-center text-sm text-amber-800
                              dark:bg-amber-950 dark:text-amber-200">
                        Only moderators and admins can post in announcement rooms.
                    </p>
                @endif
            </div>
        </div>

        @if ($openThreadId !== null)
            <aside class="flex h-full w-full max-w-md flex-shrink-0 border-l border-zinc-200
                          dark:border-zinc-800">
                <livewire:bonfire::thread-panel
                    :parent-id="$openThreadId"
                    :room="$room"
                    wire:key="thread-{{ $openThreadId }}" />
            </aside>
        @endif
    </div>

    @php
        $callRoomName = 'bonfire-'.\Illuminate\Support\Str::slug(config('app.name', 'app')).'-room-'.$room->id;
        $callDisplayName = auth()->user()?->name ?? 'Guest';
        $callAvatar = $this->currentMember?->avatar_url
            ?? 'https://ui-avatars.com/api/?name='.urlencode($callDisplayName);
    @endphp

    <flux:modal name="channel-call" class="!max-w-[90vw] !w-[90vw]"
                x-data="{ joined: false }"
                x-on:modal-show.window="if ($event.detail?.name === 'channel-call') joined = true"
                x-on:close="joined = false">
        <div class="flex h-[85vh] flex-col gap-3">
            <div class="flex flex-shrink-0 items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:icon name="video-camera" class="size-5 text-emerald-600" />
                    <flux:heading size="lg">
                        @if ($isDm && $dmPartner)
                            Call with {{ $dmPartner->display_name }}
                        @else
                            #{{ $room->name }} · call
                        @endif
                    </flux:heading>
                </div>
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark">
                        Leave call
                    </flux:button>
                </flux:modal.close>
            </div>

            <div class="flex-1 overflow-hidden rounded-lg bg-zinc-950">
                <template x-if="joined">
                    <iframe
                        :src="'https://meet.jit.si/{{ $callRoomName }}'
                                + '#userInfo.displayName=' + encodeURIComponent({{ Illuminate\Support\Js::from($callDisplayName) }})
                                + '&config.prejoinConfig.enabled=false'
                                + '&config.disableDeepLinking=true'
                                + '&config.startWithAudioMuted=true'
                                + '&config.startWithVideoMuted=false'
                                + '&interfaceConfig.MOBILE_APP_PROMO=false'
                                + '&interfaceConfig.SHOW_JITSI_WATERMARK=false'
                                + '&interfaceConfig.SHOW_WATERMARK_FOR_GUESTS=false'
                                + '&interfaceConfig.DEFAULT_REMOTE_DISPLAY_NAME=Bonfire%20user'
                                + '&interfaceConfig.DEFAULT_LOCAL_DISPLAY_NAME=' + encodeURIComponent({{ Illuminate\Support\Js::from($callDisplayName) }})"
                        allow="camera; microphone; fullscreen; display-capture; autoplay; clipboard-write"
                        class="h-full w-full border-0"></iframe>
                </template>
            </div>

            <p class="flex-shrink-0 text-xs text-zinc-500">
                Anyone in this channel who opens this call joins the same meeting.
                Your mic starts muted — unmute it when you're ready.
            </p>
        </div>
    </flux:modal>

    <flux:modal name="channel-details" class="max-w-lg">
        @php
            $creator = $room->creator;
            $createdAt = $room->created_at;
        @endphp

        <div x-data="{ tab: 'about' }" class="space-y-4">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    @if ($isDm && $dmPartner)
                        <img src="{{ $dmPartner->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($dmPartner->display_name) }}"
                             alt="" class="size-6 rounded bg-zinc-200 dark:bg-zinc-700">
                        <span>{{ $dmPartner->display_name }}</span>
                    @elseif ($room->isAnnouncements())
                        <flux:icon name="megaphone" class="size-5 text-amber-500" />
                        <span>{{ $room->name }}</span>
                    @elseif ($room->isArchived())
                        <flux:icon name="archive-box" class="size-5 text-zinc-500" />
                        <span>{{ $room->name }}</span>
                    @else
                        <span class="text-zinc-500">#</span>
                        <span>{{ $room->name }}</span>
                    @endif
                </flux:heading>
                @if ($room->isArchived())
                    <flux:text class="mt-1 text-amber-600 dark:text-amber-400">
                        This channel is archived and read-only.
                    </flux:text>
                @endif
            </div>

            @unless ($isDm)
                <div class="flex items-center gap-1 border-b border-zinc-200 text-sm dark:border-zinc-700">
                    <button type="button" @click="tab = 'about'"
                            :class="tab === 'about'
                                ? 'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100'
                                : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                            class="border-b-2 px-3 py-2 font-medium">
                        About
                    </button>
                    <button type="button" @click="tab = 'members'"
                            :class="tab === 'members'
                                ? 'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100'
                                : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                            class="border-b-2 px-3 py-2 font-medium">
                        Members
                        <span class="ml-1 text-xs text-zinc-500">{{ $memberCount }}</span>
                    </button>
                    <button type="button" @click="tab = 'pinned'"
                            :class="tab === 'pinned'
                                ? 'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100'
                                : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                            class="border-b-2 px-3 py-2 font-medium">
                        Pinned
                        <span class="ml-1 text-xs text-zinc-500">{{ $this->pinnedMessages->count() }}</span>
                    </button>
                    <button type="button" @click="tab = 'files'"
                            :class="tab === 'files'
                                ? 'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100'
                                : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                            class="border-b-2 px-3 py-2 font-medium">
                        Files
                        <span class="ml-1 text-xs text-zinc-500">{{ $this->roomAttachments->count() }}</span>
                    </button>
                </div>
            @endunless

            <div x-show="tab === 'about'" class="space-y-3 text-sm">
                @unless ($isDm)
                    <div class="rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            Description
                        </div>
                        <div class="mt-1 text-zinc-800 dark:text-zinc-200">
                            {{ $room->description ?: 'No description yet.' }}
                        </div>
                    </div>

                    @if ($creator && $createdAt)
                        <div class="rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                                Created by
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <img src="{{ $creator->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($creator->display_name) }}"
                                     alt="" class="size-5 rounded bg-zinc-200 dark:bg-zinc-700">
                                <span class="text-zinc-800 dark:text-zinc-200">{{ $creator->display_name }}</span>
                                <span class="text-zinc-500">on {{ $createdAt->format('F j, Y') }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            Channel link
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <code class="flex-1 truncate rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-700
                                         dark:bg-zinc-800 dark:text-zinc-300">{{ route('bonfire.room.show', $room) }}</code>
                            <button type="button"
                                    @click="navigator.clipboard?.writeText('{{ route('bonfire.room.show', $room) }}')"
                                    class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900
                                           dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                    title="Copy link">
                                <flux:icon name="clipboard-document" class="size-4" />
                            </button>
                        </div>
                    </div>
                @endunless

                @if ($isDm && $dmPartner)
                    <div class="rounded-md border border-zinc-200 p-3 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
                        Direct conversation with {{ $dmPartner->display_name }}.
                    </div>
                @endif
            </div>

            @unless ($isDm)
                <div x-show="tab === 'members'" style="display: none;">
                    <ul class="max-h-96 divide-y divide-zinc-100 overflow-y-auto
                               dark:divide-zinc-800">
                        @foreach ($channelMembers as $m)
                            @php
                                $memberUser = $m->memberable_type === \App\Models\User::class
                                    ? \App\Models\User::find($m->memberable_id)
                                    : null;
                                $memberEmail = $memberUser?->email;
                            @endphp
                            <li class="flex items-start gap-3 py-2">
                                <span class="relative flex-shrink-0 pt-0.5">
                                    <img src="{{ $m->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($m->display_name) }}"
                                         alt="" class="size-9 rounded bg-zinc-200 object-cover dark:bg-zinc-700">
                                    <span class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full border-2 border-white
                                                 {{ $m->is_away ? 'bg-amber-400' : 'bg-emerald-500' }}
                                                 dark:border-zinc-900"></span>
                                </span>
                                <div class="min-w-0 flex-1 space-y-0.5">
                                    <div class="flex items-baseline gap-2">
                                        <span class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $m->display_name }}
                                        </span>
                                        @if ($this->currentMember && $m->id === $this->currentMember->id)
                                            <span class="text-[11px] text-zinc-500">(you)</span>
                                        @endif
                                        @if ($m->status_emoji)
                                            <span class="text-sm">{{ $m->status_emoji }}</span>
                                        @endif
                                        @if ($m->status_text)
                                            <span class="truncate text-xs text-zinc-500">{{ $m->status_text }}</span>
                                        @endif
                                    </div>
                                    @if ($memberEmail)
                                        <a href="mailto:{{ $memberEmail }}"
                                           class="block truncate text-xs text-zinc-500 hover:text-sky-600
                                                  dark:hover:text-sky-400">
                                            {{ $memberEmail }}
                                        </a>
                                    @endif
                                    @if ($m->phone)
                                        <a href="tel:{{ $m->phone }}"
                                           class="block truncate text-xs text-zinc-500 hover:text-sky-600
                                                  dark:hover:text-sky-400">
                                            {{ $m->phone }}
                                        </a>
                                    @endif
                                </div>
                                @if ($m->timezone)
                                    <div class="flex-shrink-0 text-right text-xs"
                                         x-data="{
                                             tz: {{ Illuminate\Support\Js::from($m->timezone) }},
                                             get time() {
                                                 try {
                                                     return new Intl.DateTimeFormat(undefined, {
                                                         timeZone: this.tz,
                                                         hour: 'numeric',
                                                         minute: '2-digit',
                                                     }).format(new Date());
                                                 } catch (e) { return ''; }
                                             },
                                             get region() {
                                                 return this.tz.split('/').pop().replace(/_/g, ' ');
                                             },
                                         }">
                                        <div class="text-zinc-700 dark:text-zinc-300" x-text="time"></div>
                                        <div class="text-zinc-500" x-text="region"></div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div x-show="tab === 'files'" style="display: none;"
                     x-data="{ filter: 'all' }">
                    @if ($this->roomAttachments->isEmpty())
                        <div class="rounded-md border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500
                                    dark:border-zinc-700">
                            No files shared in this channel yet.
                        </div>
                    @else
                        @php
                            $attachments = $this->roomAttachments;
                            $imageCount = $attachments->filter(fn ($a) => $a->isImage())->count();
                            $videoCount = $attachments->filter(fn ($a) => $a->isVideo())->count();
                            $audioCount = $attachments->filter(fn ($a) => $a->isAudio())->count();
                            $docCount = $attachments->count() - $imageCount - $videoCount - $audioCount;
                        @endphp
                        <div class="mb-3 flex flex-wrap gap-1 text-xs">
                            @foreach ([
                                ['all', 'All', $attachments->count()],
                                ['image', 'Images', $imageCount],
                                ['video', 'Videos', $videoCount],
                                ['audio', 'Audio', $audioCount],
                                ['doc', 'Docs', $docCount],
                            ] as [$key, $label, $count])
                                <button type="button"
                                        @click="filter = '{{ $key }}'"
                                        :class="filter === '{{ $key }}'
                                            ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                            : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700'"
                                        class="rounded-full px-2.5 py-1 font-medium">
                                    {{ $label }}
                                    <span class="ml-0.5 opacity-70">{{ $count }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="max-h-96 space-y-1.5 overflow-y-auto pr-1">
                            @foreach ($attachments as $att)
                                @php
                                    if ($att->isImage()) {
                                        $kind = 'image';
                                    } elseif ($att->isVideo()) {
                                        $kind = 'video';
                                    } elseif ($att->isAudio()) {
                                        $kind = 'audio';
                                    } else {
                                        $kind = 'doc';
                                    }
                                    $url = route('bonfire.attachments.show', $att);
                                    $author = $att->message?->member?->display_name ?? 'Unknown';
                                    $sharedAt = $att->created_at ?? $att->message?->created_at;
                                    $sizeKb = $att->size ? number_format($att->size / 1024, 1).' KB' : '—';
                                @endphp
                                <div x-show="filter === 'all' || filter === '{{ $kind }}'"
                                     class="flex items-center gap-3 rounded-md border border-zinc-200 bg-white p-2
                                            hover:bg-zinc-50
                                            dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">
                                    <div class="flex size-11 flex-shrink-0 items-center justify-center overflow-hidden rounded
                                                bg-zinc-100 dark:bg-zinc-800">
                                        @if ($kind === 'image')
                                            <img src="{{ $url }}" alt="" class="size-full object-cover">
                                        @elseif ($kind === 'video')
                                            <flux:icon name="film" class="size-5 text-sky-500" />
                                        @elseif ($kind === 'audio')
                                            <flux:icon name="musical-note" class="size-5 text-purple-500" />
                                        @else
                                            <flux:icon name="document" class="size-5 text-zinc-500" />
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $url }}" target="_blank" rel="noopener"
                                           class="block truncate text-sm font-medium text-zinc-800 hover:text-sky-600
                                                  dark:text-zinc-200 dark:hover:text-sky-400">
                                            {{ $att->filename }}
                                        </a>
                                        <div class="truncate text-[11px] text-zinc-500">
                                            <span>{{ $author }} · </span>
                                            <span x-data="bonfireRelativeTime({{ Illuminate\Support\Js::from($sharedAt?->toIso8601String()) }})"
                                                  x-init="start()"
                                                  x-text="text || '—'"></span>
                                            <span> · {{ $sizeKb }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-shrink-0 items-center gap-1">
                                        @if ($att->message_id)
                                            <a href="#m-{{ $att->message_id }}"
                                               @click="$dispatch('modal-close', { name: 'channel-details' })"
                                               title="Jump to message"
                                               class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-sky-600
                                                      dark:hover:bg-zinc-800 dark:hover:text-sky-400">
                                                <flux:icon name="arrow-top-right-on-square" class="size-4" />
                                            </a>
                                        @endif
                                        <a href="{{ $url }}" download
                                           title="Download"
                                           class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900
                                                  dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                            <flux:icon name="arrow-down-tray" class="size-4" />
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div x-show="tab === 'pinned'" style="display: none;">
                    @if ($this->pinnedMessages->isEmpty())
                        <div class="rounded-md border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500
                                    dark:border-zinc-700">
                            No pinned messages yet. Hover any message and click the bookmark icon to pin it.
                        </div>
                    @else
                        <ul class="max-h-96 space-y-2 overflow-y-auto pr-1">
                            @foreach ($this->pinnedMessages as $pm)
                                <li class="rounded-md border border-zinc-200 bg-zinc-50 p-3
                                           dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="flex items-center gap-2 text-xs">
                                        <img src="{{ $pm->member?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($pm->member?->display_name ?? '?') }}"
                                             alt="" class="size-5 rounded bg-zinc-200 dark:bg-zinc-700">
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                            {{ $pm->member?->display_name ?? 'Unknown' }}
                                        </span>
                                        <span class="text-zinc-500"
                                              x-data="bonfireRelativeTime({{ Illuminate\Support\Js::from($pm->created_at?->toIso8601String()) }})"
                                              x-init="start()"
                                              x-text="'· ' + text"></span>
                                    </div>
                                    <div class="bonfire-message-body mt-1.5 max-w-none break-words text-sm text-zinc-800 dark:text-zinc-200">
                                        @php
                                            $pinnedBodyIsHtml = preg_match('/<\\w+[^>]*>/', (string) $pm->body) === 1;
                                        @endphp
                                        @if ($pinnedBodyIsHtml)
                                            {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->highlightMentions($pm->body) !!}
                                        @else
                                            {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->render($pm->body, $pm->tenant_id) !!}
                                        @endif
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <a href="#m-{{ $pm->id }}"
                                           @click="$dispatch('modal-close', { name: 'channel-details' })"
                                           class="text-[11px] font-medium text-sky-600 hover:underline
                                                  dark:text-sky-400">
                                            Jump to message
                                        </a>
                                        <span class="text-[10px] text-zinc-500"
                                              x-data="bonfireRelativeTime({{ Illuminate\Support\Js::from($pm->pinned_at?->toIso8601String()) }})"
                                              x-init="start()"
                                              x-text="'Pinned ' + text"></span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endunless

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                @if (! $isDm && $room->isPrivate() && ! $room->isArchived())
                    <flux:button variant="danger"
                                 wire:click="leaveChannel"
                                 wire:confirm="Leave this channel?">
                        Leave channel
                    </flux:button>
                @endif
                <flux:modal.close>
                    <flux:button variant="ghost">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
