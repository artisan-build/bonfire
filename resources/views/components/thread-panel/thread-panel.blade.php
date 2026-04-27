<div class="flex h-full min-h-0 w-full flex-col">
    <header class="flex h-12 flex-shrink-0 items-center justify-between border-b border-zinc-200 px-4
                   dark:border-zinc-800">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                Thread
            </h2>
            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                #{{ $room->name }}
            </p>
        </div>
        <button wire:click="close"
                type="button"
                title="Close thread"
                class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900
                       dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
            <flux:icon name="x-mark" class="size-5" />
        </button>
    </header>

    @php($parent = $this->parent)

    <div class="min-h-0 flex-1 overflow-y-auto">
        <article wire:key="thread-parent-{{ $parent->id }}"
                 class="flex gap-3 border-b border-zinc-200 px-4 py-3
                        dark:border-zinc-800">
            <div class="relative flex-shrink-0"
                 x-data="{ open: false, t: null }"
                 @mouseenter="clearTimeout(t); t = setTimeout(() => open = true, 200)"
                 @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)">
                <img src="{{ $parent->member?->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($parent->member?->display_name ?? '?') }}"
                     alt=""
                     class="size-9 cursor-pointer rounded bg-zinc-200 object-cover dark:bg-zinc-800">
                <div x-show="open"
                     x-transition.opacity.duration.100ms
                     @mouseenter="clearTimeout(t); open = true"
                     @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)"
                     class="absolute left-full top-0 z-40 ml-2"
                     style="display: none;">
                    @include('bonfire::partials.member-hover-card', ['member' => $parent->member])
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline gap-2">
                    <span class="relative inline-block"
                          x-data="{ open: false, t: null }"
                          @mouseenter="clearTimeout(t); t = setTimeout(() => open = true, 200)"
                          @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)">
                        <span class="cursor-pointer text-sm font-semibold text-zinc-900 hover:underline
                                     dark:text-zinc-100">
                            {{ $parent->member?->display_name ?? 'Unknown' }}
                        </span>
                        <span x-show="open"
                              x-transition.opacity.duration.100ms
                              @mouseenter="clearTimeout(t); open = true"
                              @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)"
                              class="absolute left-0 top-full z-40 mt-1"
                              style="display: none;">
                            @include('bonfire::partials.member-hover-card', ['member' => $parent->member])
                        </span>
                    </span>
                    <time class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $parent->created_at?->format('g:i A') }}
                    </time>
                </div>
                <div class="bonfire-message-body max-w-none break-words text-zinc-800 dark:text-zinc-200">
                    @if ($parent->trashed())
                        <em class="text-zinc-500">This message was deleted.</em>
                    @elseif (preg_match('/<\\w+[^>]*>/', (string) $parent->body) === 1)
                        {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->highlightMentions($parent->body) !!}
                    @else
                        {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->render($parent->body, $parent->tenant_id) !!}
                    @endif
                </div>
            </div>
        </article>

        @php($replyCount = $this->replies->count())
        @if ($replyCount > 0)
            <div class="flex items-center gap-3 px-4 py-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span>{{ $replyCount }} {{ \Illuminate\Support\Str::plural('reply', $replyCount) }}</span>
                <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
            </div>
        @endif

        <ul role="list" class="flex flex-col py-1">
            @forelse ($this->replies as $reply)
                <li wire:key="reply-{{ $reply->id }}"
                    class="group flex gap-3 px-4 py-1 hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                    <div class="relative flex-shrink-0"
                         x-data="{ open: false, t: null }"
                         @mouseenter="clearTimeout(t); t = setTimeout(() => open = true, 200)"
                         @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)">
                        <img src="{{ $reply->member?->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($reply->member?->display_name ?? '?') }}"
                             alt=""
                             class="size-9 cursor-pointer rounded bg-zinc-200 object-cover dark:bg-zinc-800">
                        <div x-show="open"
                             x-transition.opacity.duration.100ms
                             @mouseenter="clearTimeout(t); open = true"
                             @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)"
                             class="absolute left-full top-0 z-40 ml-2"
                             style="display: none;">
                            @include('bonfire::partials.member-hover-card', ['member' => $reply->member])
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline gap-2">
                            <span class="relative inline-block"
                                  x-data="{ open: false, t: null }"
                                  @mouseenter="clearTimeout(t); t = setTimeout(() => open = true, 200)"
                                  @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)">
                                <span class="cursor-pointer text-sm font-semibold text-zinc-900 hover:underline
                                             dark:text-zinc-100">
                                    {{ $reply->member?->display_name ?? 'Unknown' }}
                                </span>
                                <span x-show="open"
                                      x-transition.opacity.duration.100ms
                                      @mouseenter="clearTimeout(t); open = true"
                                      @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)"
                                      class="absolute left-0 top-full z-40 mt-1"
                                      style="display: none;">
                                    @include('bonfire::partials.member-hover-card', ['member' => $reply->member])
                                </span>
                            </span>
                            <time class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $reply->created_at?->format('g:i A') }}
                            </time>
                        </div>
                        <div class="bonfire-message-body max-w-none break-words text-zinc-800 dark:text-zinc-200">
                            @if (preg_match('/<\\w+[^>]*>/', (string) $reply->body) === 1)
                                {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->highlightMentions($reply->body) !!}
                            @else
                                {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->render($reply->body, $reply->tenant_id) !!}
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
                    No replies yet. Start the conversation.
                </li>
            @endforelse
        </ul>
    </div>

    <div class="flex-shrink-0 border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
        <livewire:bonfire::message-composer
            :room="$room"
            :parent-id="$parentId"
            wire:key="thread-composer-{{ $parentId }}" />
    </div>
</div>
