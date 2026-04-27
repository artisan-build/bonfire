@php
    /** @var \ArtisanBuild\Bonfire\Models\Member|null $member */
    $member ??= null;
    $memberUser = null;
    if ($member && $member->memberable_type === \App\Models\User::class) {
        $memberUser = \App\Models\User::find($member->memberable_id);
    }
    $memberEmail = $memberUser?->email;
    $avatarSrc = $member?->avatar_url
        ?: 'https://ui-avatars.com/api/?name='.urlencode($member?->display_name ?? '?');
@endphp

@if ($member)
    <div class="w-72 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg
                dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center gap-3 p-3">
            <img src="{{ $avatarSrc }}" alt=""
                 class="size-12 flex-shrink-0 rounded-md bg-zinc-200 object-cover dark:bg-zinc-700">
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $member->display_name }}
                </div>
                <div class="mt-0.5 flex items-center gap-1 text-[11px]">
                    <span class="inline-flex size-1.5 rounded-full
                                 {{ $member->is_away ? 'bg-amber-400' : 'bg-emerald-500' }}"></span>
                    <span class="text-zinc-500">{{ $member->is_away ? 'Away' : 'Active' }}</span>
                    @if (isset($member->role))
                        <span class="mx-1 text-zinc-300 dark:text-zinc-700">·</span>
                        <span class="text-zinc-500 capitalize">{{ $member->role->value }}</span>
                    @endif
                </div>
                @if ($member->status_emoji || $member->status_text)
                    <div class="mt-1 truncate text-[11px] text-zinc-600 dark:text-zinc-400">
                        @if ($member->status_emoji)
                            <span>{{ $member->status_emoji }}</span>
                        @endif
                        @if ($member->status_text)
                            <span>{{ $member->status_text }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <dl class="divide-y divide-zinc-100 border-t border-zinc-100 text-xs
                   dark:divide-zinc-800 dark:border-zinc-800">
            @if ($memberEmail)
                <div class="flex items-center gap-2 px-3 py-2">
                    <flux:icon name="envelope" class="size-3.5 flex-shrink-0 text-zinc-400" />
                    <a href="mailto:{{ $memberEmail }}"
                       class="truncate text-zinc-700 hover:text-sky-600
                              dark:text-zinc-300 dark:hover:text-sky-400">
                        {{ $memberEmail }}
                    </a>
                </div>
            @endif
            @if ($member->phone)
                <div class="flex items-center gap-2 px-3 py-2">
                    <flux:icon name="phone" class="size-3.5 flex-shrink-0 text-zinc-400" />
                    <a href="tel:{{ $member->phone }}"
                       class="truncate text-zinc-700 hover:text-sky-600
                              dark:text-zinc-300 dark:hover:text-sky-400">
                        {{ $member->phone }}
                    </a>
                </div>
            @endif
            @if ($member->timezone)
                <div class="flex items-center gap-2 px-3 py-2"
                     x-data="{
                         tz: {{ \Illuminate\Support\Js::from($member->timezone) }},
                         get time() {
                             try {
                                 return new Intl.DateTimeFormat(undefined, {
                                     timeZone: this.tz,
                                     hour: 'numeric',
                                     minute: '2-digit',
                                     weekday: 'short',
                                 }).format(new Date());
                             } catch (e) { return ''; }
                         },
                         get region() {
                             return this.tz.split('/').pop().replace(/_/g, ' ');
                         },
                     }">
                    <flux:icon name="clock" class="size-3.5 flex-shrink-0 text-zinc-400" />
                    <span class="truncate text-zinc-700 dark:text-zinc-300">
                        <span x-text="time"></span>
                        <span class="text-zinc-500">· <span x-text="region"></span></span>
                    </span>
                </div>
            @endif
        </dl>
    </div>
@endif
