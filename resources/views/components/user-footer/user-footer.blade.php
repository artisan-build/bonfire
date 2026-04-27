@php
    $member = $this->member;
    $isAway = (bool) ($member?->is_away ?? false);
    $showStatus = $this->statusVisible();
    $display = $member?->display_name ?? auth()->user()?->name ?? 'Me';
    $avatar = $member?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($display);
@endphp

<div class="flex-shrink-0 border-t border-zinc-200 px-2 py-2
            dark:border-zinc-800"
     x-data="{
         dndUntil: parseInt(localStorage.getItem('bonfire.dnd.until') ?? '0', 10),
         quietEnabled: localStorage.getItem('bonfire.dnd.quiet.enabled') === '1',
         quietFrom: localStorage.getItem('bonfire.dnd.quiet.from') || '22:00',
         quietTo: localStorage.getItem('bonfire.dnd.quiet.to') || '08:00',
         now: Date.now(),
         get inQuiet() {
             if (! this.quietEnabled) return false;
             const parse = (s) => {
                 const m = /^(\d{1,2}):(\d{2})$/.exec(s || '');
                 return m ? (parseInt(m[1], 10) * 60 + parseInt(m[2], 10)) : null;
             };
             const f = parse(this.quietFrom), t = parse(this.quietTo);
             if (f === null || t === null || f === t) return false;
             const d = new Date(this.now);
             const n = d.getHours() * 60 + d.getMinutes();
             return f < t ? (n >= f && n < t) : (n >= f || n < t);
         },
         get dndActive() { return this.dndUntil > this.now || this.inQuiet; },
         get dndLabel() {
             if (this.dndUntil > this.now) {
                 const d = new Date(this.dndUntil);
                 const sameDay = d.toDateString() === new Date().toDateString();
                 if (sameDay) return 'Paused until ' + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                 return 'Paused until ' + d.toLocaleString(undefined, { weekday: 'short', hour: 'numeric', minute: '2-digit' });
             }
             if (this.inQuiet) return 'Quiet hours · ' + this.quietFrom + '–' + this.quietTo;
             return '';
         },
         pause(minutes) {
             const until = minutes ? Date.now() + minutes * 60 * 1000 : 0;
             this.dndUntil = until;
             if (until) localStorage.setItem('bonfire.dnd.until', String(until));
             else localStorage.removeItem('bonfire.dnd.until');
         },
         pauseUntilTomorrow() {
             const t = new Date();
             t.setDate(t.getDate() + 1);
             t.setHours(9, 0, 0, 0);
             this.dndUntil = t.getTime();
             localStorage.setItem('bonfire.dnd.until', String(this.dndUntil));
         },
         toggleQuiet() {
             this.quietEnabled = ! this.quietEnabled;
             localStorage.setItem('bonfire.dnd.quiet.enabled', this.quietEnabled ? '1' : '0');
         },
         saveQuiet() {
             localStorage.setItem('bonfire.dnd.quiet.from', this.quietFrom);
             localStorage.setItem('bonfire.dnd.quiet.to', this.quietTo);
             if (this.quietEnabled) localStorage.setItem('bonfire.dnd.quiet.enabled', '1');
         },
         init() {
             setInterval(() => {
                 this.now = Date.now();
                 if (this.dndUntil && this.dndUntil <= this.now) this.pause(0);
             }, 30_000);
         },
     }">
    <flux:dropdown position="top" align="start">
        <button type="button"
                class="group flex w-full items-center gap-2 rounded px-2 py-1.5 text-left
                       hover:bg-zinc-200/60
                       dark:hover:bg-zinc-800">
            <span class="relative flex-shrink-0">
                <img src="{{ $avatar }}" alt="" class="size-7 rounded bg-zinc-200 dark:bg-zinc-700">
                <span x-show="dndActive"
                      class="absolute -right-0.5 -bottom-0.5 flex size-3.5 items-center justify-center rounded-full border-2 border-zinc-50 bg-zinc-700 text-[8px] text-white
                             dark:border-zinc-900"
                      title="Notifications paused"
                      style="display: none;">
                    <flux:icon name="moon" class="size-2" />
                </span>
                @if ($isAway)
                    <span x-show="! dndActive"
                          class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full border-2 border-zinc-50 bg-amber-400
                                 dark:border-zinc-900"></span>
                @else
                    <span x-show="! dndActive"
                          class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full border-2 border-zinc-50 bg-emerald-500
                                 dark:border-zinc-900"></span>
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1 truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    <span class="truncate">{{ $display }}</span>
                    @if ($showStatus && $member?->status_emoji)
                        <span class="shrink-0">{{ $member->status_emoji }}</span>
                    @endif
                </div>
                <div class="truncate text-xs
                            {{ $isAway ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    @if ($showStatus && $member?->status_text)
                        {{ $member->status_text }}
                    @else
                        {{ $isAway ? 'Away' : 'Active' }}
                    @endif
                </div>
            </div>
            <flux:icon name="chevron-up" class="size-3.5 text-zinc-400" />
        </button>

        <flux:menu>
            <flux:menu.item icon="user" wire:click="openProfileModal">
                Profile
            </flux:menu.item>
            <flux:menu.item icon="face-smile" wire:click="openStatusModal">
                Set a status
            </flux:menu.item>
            <flux:menu.item :icon="$isAway ? 'sun' : 'moon'"
                            wire:click="toggleAway">
                {{ $isAway ? 'Set yourself active' : 'Set yourself away' }}
            </flux:menu.item>

            <flux:menu.separator />

            <flux:menu.submenu icon="bell-slash" heading="Pause notifications">
                <div class="px-2 pb-1 pt-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-500"
                     x-show="dndActive"
                     x-text="dndLabel"></div>
                <flux:menu.item icon="bell-slash" @click="pause(30)">For 30 minutes</flux:menu.item>
                <flux:menu.item icon="bell-slash" @click="pause(60)">For 1 hour</flux:menu.item>
                <flux:menu.item icon="bell-slash" @click="pause(240)">For 4 hours</flux:menu.item>
                <flux:menu.item icon="moon" @click="pauseUntilTomorrow()">Until tomorrow</flux:menu.item>
                <template x-if="dndUntil > now">
                    <div>
                        <flux:menu.separator />
                        <flux:menu.item icon="arrow-uturn-left"
                                        class="!text-sky-600 dark:!text-sky-400"
                                        @click="pause(0)">
                            Resume notifications
                        </flux:menu.item>
                    </div>
                </template>
            </flux:menu.submenu>

            <div x-show="dndActive" class="px-2 pb-1 pt-1 text-[10px] text-sky-600 dark:text-sky-400">
                <flux:icon name="moon" class="mr-1 inline size-3" />
                <span x-text="dndLabel"></span>
            </div>
            <div x-show="quietEnabled && ! dndUntil" class="px-2 pb-1 pt-1 text-[10px] text-sky-600 dark:text-sky-400">
                <flux:icon name="moon" class="mr-1 inline size-3" />
                <span x-text="'Quiet hours ' + quietFrom + '–' + quietTo + (inQuiet ? ' (active)' : '')"></span>
            </div>

            <flux:menu.separator />
            <flux:menu.item icon="cog-6-tooth" href="{{ route('profile.edit', absolute: false) }}" wire:navigate>
                Settings
            </flux:menu.item>
            @if (auth()->user()?->is_admin && \Illuminate\Support\Facades\Route::has('admin.features.index'))
                <flux:menu.item icon="adjustments-horizontal"
                                href="{{ route('admin.features.index', absolute: false) }}" wire:navigate>
                    Feature flags
                </flux:menu.item>
            @endif
            <flux:menu.separator />
            <flux:menu.item icon="arrow-right-start-on-rectangle"
                            href="{{ route('logout', absolute: false) }}" wire:navigate>
                Sign out
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>

    <flux:modal name="set-status" class="max-w-md">
        <div x-data="{
                 presets: [
                     { emoji: '📅', text: 'In a meeting', clearIn: 'one_hour' },
                     { emoji: '🚌', text: 'Commuting', clearIn: 'one_hour' },
                     { emoji: '🤒', text: 'Out sick', clearIn: 'today' },
                     { emoji: '🌴', text: 'Vacationing', clearIn: 'this_week' },
                     { emoji: '🏠', text: 'Working remotely', clearIn: 'today' },
                     { emoji: '🎧', text: 'Focusing', clearIn: 'one_hour' },
                     { emoji: '🍽️', text: 'Lunch', clearIn: 'one_hour' },
                     { emoji: '💤', text: 'Zzz', clearIn: 'today' },
                 ],
                 applyPreset(p) {
                     this.$wire.set('statusEmoji', p.emoji);
                     this.$wire.set('statusText', p.text);
                     this.$wire.set('statusClearIn', p.clearIn);
                 },
             }">
            <form wire:submit.prevent="saveStatus" class="space-y-5">
                <div>
                    <flux:heading size="lg">Set a status</flux:heading>
                    <flux:text class="mt-1">
                        Let your teammates know what you're up to.
                    </flux:text>
                </div>

                <div>
                    <flux:label>Custom status</flux:label>
                    <div class="mt-1 flex w-full items-stretch gap-2">
                        <div class="w-14 flex-shrink-0">
                            <flux:input wire:model="statusEmoji"
                                        maxlength="4"
                                        aria-label="Status emoji"
                                        class="[&_input]:text-center [&_input]:text-lg" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <flux:input wire:model="statusText"
                                        placeholder="What's your status?"
                                        aria-label="Status text" />
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Or pick a suggestion
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in presets" :key="p.text">
                            <button type="button"
                                    @click="applyPreset(p)"
                                    class="flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1 text-sm
                                           hover:border-zinc-400 hover:bg-zinc-50
                                           dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500">
                                <span x-text="p.emoji"></span>
                                <span x-text="p.text"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <flux:field>
                    <flux:label>Clear after</flux:label>
                    <flux:select wire:model="statusClearIn">
                        <option value="">Don't clear</option>
                        <option value="one_hour">In 1 hour</option>
                        <option value="four_hours">In 4 hours</option>
                        <option value="today">Today</option>
                        <option value="this_week">This week</option>
                    </flux:select>
                </flux:field>

                <div class="flex justify-between gap-2">
                    <flux:button type="button" variant="ghost" wire:click="clearStatus">
                        Clear status
                    </flux:button>
                    <div class="flex gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">Save</flux:button>
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="user-profile" class="max-w-lg">
        <form wire:submit="saveProfile"
              x-data="{
                  previewUrl: null,
                  uploading: false,
                  uploadProgress: 0,
                  uploadError: '',
                  onFilePick(event) {
                      const file = event.target.files?.[0];
                      if (! file) { this.previewUrl = null; return; }
                      if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                      this.previewUrl = URL.createObjectURL(file);
                  },
                  get localTime() {
                      try {
                          return new Intl.DateTimeFormat(undefined, {
                              timeZone: @this.profileTimezone || undefined,
                              hour: 'numeric',
                              minute: '2-digit',
                              weekday: 'short',
                          }).format(new Date());
                      } catch (e) {
                          return '—';
                      }
                  },
              }"
              x-on:livewire-upload-start="uploading = true; uploadProgress = 0; uploadError = ''"
              x-on:livewire-upload-finish="uploading = false; uploadProgress = 100"
              x-on:livewire-upload-error="uploading = false; uploadError = 'Upload failed'"
              x-on:livewire-upload-progress="uploadProgress = $event.detail.progress"
              class="space-y-5">
            <div>
                <flux:heading size="lg">Edit your profile</flux:heading>
                <flux:text class="mt-1">Visible to others in the workspace.</flux:text>
            </div>

            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700
                            dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex items-center gap-4">
                <img :src="previewUrl || '{{ $avatar }}'" alt=""
                     class="size-16 rounded-md bg-zinc-200 object-cover dark:bg-zinc-700">
                <div class="flex flex-col gap-1">
                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-zinc-300 px-2.5 py-1 text-xs font-medium text-zinc-700
                                  hover:bg-zinc-100
                                  dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        <flux:icon name="arrow-up-tray" class="size-3.5" />
                        <span>Upload photo</span>
                        <input type="file"
                               wire:model="profileAvatar"
                               accept="image/*"
                               @change="onFilePick($event)"
                               class="hidden">
                    </label>
                    <div x-show="uploading" class="flex items-center gap-2 text-[11px] text-zinc-500">
                        <span>Uploading…</span>
                        <span class="h-1 w-16 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <span class="block h-full bg-sky-500 transition-all"
                                  :style="'width: ' + uploadProgress + '%'"></span>
                        </span>
                    </div>
                    <div x-show="uploadError" class="text-[11px] text-rose-600" x-text="uploadError"></div>
                    <div class="text-[11px] text-zinc-500">
                        PNG or JPG, up to 4MB.
                    </div>
                    @error('profileAvatar')
                        <p class="text-[11px] text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                        Display name
                    </label>
                    <input type="text"
                           wire:model="profileDisplayName"
                           class="w-full rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm
                                  focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                  dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                    @error('profileDisplayName')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                        Email <span class="text-zinc-400">(read-only)</span>
                    </label>
                    <input type="email"
                           value="{{ auth()->user()?->email }}"
                           disabled
                           class="w-full rounded-md border border-zinc-200 bg-zinc-50 px-2 py-2 text-sm text-zinc-500
                                  dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-400" />
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                        Phone <span class="text-zinc-400">(optional)</span>
                    </label>
                    <input type="tel"
                           wire:model="profilePhone"
                           placeholder="+1 555 000 0000"
                           class="w-full rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm
                                  focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                  dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                    @error('profilePhone')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                        Time zone
                    </label>
                    <select wire:model.live="profileTimezone"
                            class="w-full rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm
                                   focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500
                                   dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        @foreach ($this->timezones as $tz)
                            <option value="{{ $tz['value'] }}">{{ $tz['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-zinc-500">
                        Local time: <span x-text="localTime" class="font-medium text-zinc-700 dark:text-zinc-300"></span>
                    </p>
                </div>
            </div>

            @if ($member)
                <dl class="divide-y divide-zinc-100 rounded-md border border-zinc-200 text-sm dark:divide-zinc-800 dark:border-zinc-700">
                    <div class="flex justify-between px-3 py-2">
                        <dt class="text-zinc-500">Role</dt>
                        <dd class="text-zinc-900 capitalize dark:text-zinc-100">{{ $member->role->value }}</dd>
                    </div>
                    <div class="flex justify-between px-3 py-2">
                        <dt class="text-zinc-500">Joined</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $member->created_at?->format('M j, Y') }}</dd>
                    </div>
                </dl>
            @endif

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    Save profile
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
