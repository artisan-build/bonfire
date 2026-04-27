<div class="flex min-h-0 flex-1 flex-col overflow-y-auto"
     data-search="{{ $this->search }}"
     x-data="{
         pinned: true,
         typing: {},
         pendingDeleteId: null,
         pendingDeletePreview: '',
         askDelete(id, preview) {
             this.pendingDeleteId = id;
             this.pendingDeletePreview = preview;
             this.$dispatch('modal-show', { name: 'delete-message' });
         },
         confirmDelete() {
             if (this.pendingDeleteId === null) return;
             this.$wire.deleteMessage(this.pendingDeleteId);
             this.$dispatch('modal-close', { name: 'delete-message' });
             this.pendingDeleteId = null;
             this.pendingDeletePreview = '';
         },
         copyToast: '',
         copyToastTimer: null,
         forwardId: null,
         forwardNote: '',
         forwardSearch: '',
         openForward(id) {
             this.forwardId = id;
             this.forwardNote = '';
             this.forwardSearch = '';
             this.$dispatch('modal-show', { name: 'forward-message' });
         },
         submitForward(targetRoomId) {
             if (this.forwardId === null) return;
             this.$wire.forwardMessage(this.forwardId, targetRoomId, this.forwardNote);
             this.$dispatch('modal-close', { name: 'forward-message' });
             this.forwardId = null;
             this.forwardNote = '';
             this.forwardSearch = '';
         },
         async copyMessageLink(id, trigger) {
             const url = window.location.origin + window.location.pathname + '#m-' + id;
             try {
                 await navigator.clipboard.writeText(url);
                 this.copyToast = 'Link copied';
             } catch (e) {
                 // Clipboard API unavailable — fall back to selecting + copy.
                 const ta = document.createElement('textarea');
                 ta.value = url;
                 document.body.appendChild(ta);
                 ta.select();
                 try { document.execCommand('copy'); this.copyToast = 'Link copied'; }
                 catch (err) { this.copyToast = 'Could not copy'; }
                 document.body.removeChild(ta);
             }
             clearTimeout(this.copyToastTimer);
             this.copyToastTimer = setTimeout(() => { this.copyToast = ''; }, 1600);
         },
         focusHashMessage() {
             const m = (window.location.hash || '').match(/^#m-(\d+)$/);
             if (! m) return;
             const el = document.getElementById('m-' + m[1]);
             if (! el) return;
             el.scrollIntoView({ behavior: 'smooth', block: 'center' });
             el.classList.add('bonfire-message-flash');
             setTimeout(() => el.classList.remove('bonfire-message-flash'), 2200);
         },
         _highlighting: false,
         applyHighlight() {
             if (this._highlighting) return;
             this._highlighting = true;
             const root = $el.querySelector('ul[role=list]');
             if (root) {
                 root.querySelectorAll('mark[data-search-hl]').forEach(m => {
                     const p = m.parentNode;
                     while (m.firstChild) p.insertBefore(m.firstChild, m);
                     p.removeChild(m);
                     p.normalize();
                 });
                 const term = ($el.dataset.search || '').trim();
                 if (term && root) {
                     const regex = new RegExp(term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
                     const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
                         acceptNode: (node) => {
                             if (! node.nodeValue || ! node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                             const p = node.parentElement;
                             if (! p || p.closest('.bonfire-message-body') === null) return NodeFilter.FILTER_REJECT;
                             return NodeFilter.FILTER_ACCEPT;
                         },
                     });
                     const nodes = [];
                     let n;
                     while ((n = walker.nextNode())) nodes.push(n);
                     nodes.forEach(node => {
                         const text = node.nodeValue;
                         regex.lastIndex = 0;
                         if (! regex.test(text)) return;
                         regex.lastIndex = 0;
                         const frag = document.createDocumentFragment();
                         let lastIdx = 0;
                         let match;
                         while ((match = regex.exec(text)) !== null) {
                             if (match.index > lastIdx) frag.appendChild(document.createTextNode(text.slice(lastIdx, match.index)));
                             const mark = document.createElement('mark');
                             mark.setAttribute('data-search-hl', '');
                             mark.textContent = match[0];
                             frag.appendChild(mark);
                             lastIdx = match.index + match[0].length;
                         }
                         if (lastIdx < text.length) frag.appendChild(document.createTextNode(text.slice(lastIdx)));
                         node.parentNode.replaceChild(frag, node);
                     });
                 }
             }
             requestAnimationFrame(() => { this._highlighting = false; });
         },
         nearBottom: true,
         forceScrollNext: false,
         updateNearBottom() {
             const threshold = 100;
             this.nearBottom = $el.scrollTop + $el.clientHeight >= $el.scrollHeight - threshold;
         },
         scrollToBottom(smooth = true) {
             $el.scrollTo({ top: $el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
         },
         init() {
             $el.scrollTop = $el.scrollHeight;
             this.$nextTick(() => this.applyHighlight());

             // Track whether the user is parked at the bottom so auto-scroll only kicks
             // in when it won't yank them away from history they're reading.
             this.updateNearBottom();
             $el.addEventListener('scroll', () => this.updateNearBottom(), { passive: true });

             const isMessageLi = (n) => n && n.nodeType === 1
                 && n.tagName === 'LI'
                 && typeof n.id === 'string'
                 && n.id.indexOf('m-') === 0;
             const containsMessageLi = (n) => {
                 if (! n || n.nodeType !== 1 || typeof n.querySelectorAll !== 'function') return false;
                 const lis = n.querySelectorAll('li');
                 for (let i = 0; i < lis.length; i++) {
                     if (isMessageLi(lis[i])) return true;
                 }
                 return false;
             };

             const obs = new MutationObserver((mutations) => {
                 this.applyHighlight();

                 const hasNewMessage = mutations.some(m =>
                     m.type === 'childList' && Array.from(m.addedNodes).some(n =>
                         isMessageLi(n) || containsMessageLi(n)
                     )
                 );

                 if (hasNewMessage && (this.nearBottom || this.forceScrollNext)) {
                     this.forceScrollNext = false;
                     this.$nextTick(() => this.scrollToBottom());
                 }
             });
             obs.observe($el, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['data-search'] });

             // After we send our own message, force-scroll regardless of current position.
             window.addEventListener('bonfire-own-message-sent', () => {
                 this.forceScrollNext = true;
             });

             // If the URL has a #m-123 hash when we arrive, jump to that message.
             this.$nextTick(() => this.focusHashMessage());
             window.addEventListener('hashchange', () => this.focusHashMessage());

             if (typeof window.Echo === 'undefined') return;
             const channel = window.Echo.join('bonfire.room.{{ $room->id }}');
             channel
                 .listenForWhisper('user.typing', (e) => {
                     if (!e || !e.member_id) return;
                     this.typing[e.member_id] = {
                         name: e.display_name,
                         at: Date.now(),
                     };
                     setTimeout(() => {
                         const entry = this.typing[e.member_id];
                         if (entry && Date.now() - entry.at >= 3000) {
                             delete this.typing[e.member_id];
                         }
                     }, 3100);
                 })
                 .listen('.message.posted', () => {
                     this.$wire.call('onMessagePosted');
                 })
                 .listen('.message.deleted', () => {
                     this.$wire.call('onMessageDeleted');
                 });
         },
         get typingLabel() {
             const names = Object.values(this.typing).map(t => t.name).filter(Boolean);
             if (names.length === 0) return '';
             if (names.length === 1) return names[0] + ' is typing…';
             if (names.length === 2) return names.join(' and ') + ' are typing…';
             return names.length + ' people are typing…';
         }
     }">
    @php
        $previous = null;
        $groupThresholdSeconds = 5 * 60;
    @endphp

    @if ($this->messages->hasMorePages())
        <div class="p-3 text-center">
            <button wire:click="$set('perPage', {{ $this->perPage + 40 }})"
                    type="button"
                    class="text-xs text-zinc-600 hover:text-zinc-900
                           dark:text-zinc-400 dark:hover:text-zinc-200">
                Load earlier messages
            </button>
        </div>
    @endif

    <ul role="list" class="flex flex-col py-2">
        @forelse ($this->messages as $message)
            @php
                $sameAuthor = $previous
                    && $previous->member_id === $message->member_id
                    && $previous->created_at
                    && $message->created_at
                    && $previous->created_at->diffInSeconds($message->created_at) <= $groupThresholdSeconds;
                $previous = $message;
            @endphp
            <li wire:key="message-{{ $message->id }}"
                id="m-{{ $message->id }}"
                class="group relative flex gap-3 px-4 {{ $sameAuthor ? 'py-0.5' : 'mt-3 py-1 first:mt-0' }}
                       hover:bg-zinc-50 dark:hover:bg-zinc-900/50 scroll-mt-20 transition-colors duration-500">
                <div class="flex w-10 flex-shrink-0 justify-center">
                    @if ($sameAuthor)
                        <time class="mt-1 hidden w-full text-center text-[10px] leading-none text-zinc-400
                                     group-hover:block
                                     dark:text-zinc-500">
                            {{ $message->created_at?->format('g:i') }}
                        </time>
                    @else
                        <div class="relative"
                             x-data="{ open: false, t: null }"
                             @mouseenter="clearTimeout(t); t = setTimeout(() => open = true, 200)"
                             @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)">
                            <img src="{{ $message->member?->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($message->member?->display_name ?? '?') }}"
                                 alt=""
                                 class="size-9 cursor-pointer rounded bg-zinc-200 object-cover dark:bg-zinc-800">
                            <div x-show="open"
                                 x-transition.opacity.duration.100ms
                                 @mouseenter="clearTimeout(t); open = true"
                                 @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)"
                                 class="absolute left-full top-0 z-40 ml-2"
                                 style="display: none;">
                                @include('bonfire::partials.member-hover-card', ['member' => $message->member])
                            </div>
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    @unless ($sameAuthor)
                        <div class="flex items-baseline gap-2">
                            <span class="relative inline-block"
                                  x-data="{ open: false, t: null }"
                                  @mouseenter="clearTimeout(t); t = setTimeout(() => open = true, 200)"
                                  @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)">
                                <span class="cursor-pointer text-sm font-semibold text-zinc-900 hover:underline
                                             dark:text-zinc-100">
                                    {{ $message->member?->display_name ?? 'Unknown' }}
                                </span>
                                <span x-show="open"
                                      x-transition.opacity.duration.100ms
                                      @mouseenter="clearTimeout(t); open = true"
                                      @mouseleave="clearTimeout(t); t = setTimeout(() => open = false, 150)"
                                      class="absolute left-0 top-full z-40 mt-1"
                                      style="display: none;">
                                    @include('bonfire::partials.member-hover-card', ['member' => $message->member])
                                </span>
                            </span>
                            <time class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $message->created_at?->format('g:i A') }}
                            </time>
                            @if ($message->pinned_at)
                                <span class="inline-flex items-center gap-0.5 rounded bg-amber-100 px-1 py-0.5 text-[10px] font-medium text-amber-700
                                             dark:bg-amber-950/60 dark:text-amber-300"
                                      title="Pinned to this channel">
                                    <svg class="size-2.5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2z"/>
                                    </svg>
                                    Pinned
                                </span>
                            @endif
                        </div>
                    @endunless

                    <div class="text-sm text-zinc-800 dark:text-zinc-200"
                         x-data="{ editing: false, draft: @js($message->body) }"
                         @bonfire-edit-message-{{ $message->id }}.window="editing = true; draft = @js($message->body); $nextTick(() => { const el = document.getElementById('edit-area-{{ $message->id }}'); if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); } })">
                        @if ($message->trashed())
                            <em class="text-zinc-500">This message was deleted.</em>
                        @else
                            <div x-show="! editing" class="bonfire-message-body max-w-none break-words">
                                @php
                                    $bodyLooksLikeHtml = preg_match('/<\\w+[^>]*>/', (string) $message->body) === 1;
                                    $wasEdited = $message->updated_at && $message->created_at
                                        && $message->updated_at->diffInSeconds($message->created_at) > 1;
                                @endphp
                                @if ($message->isPoll())
                                    @php
                                        $poll = $message->poll;
                                        $votes = $message->relationLoaded('pollVotes')
                                            ? $message->pollVotes
                                            : $message->pollVotes()->with('member')->get();
                                        $total = $votes->count();
                                        $byOption = $votes->groupBy('option_index');
                                        $currentMember = $this->currentMember;
                                        $myOption = $currentMember
                                            ? (int) ($votes->firstWhere('member_id', $currentMember->id)?->option_index ?? -1)
                                            : -1;
                                        $pollBodyPlain = trim(strip_tags(html_entity_decode((string) $message->body, ENT_QUOTES | ENT_HTML5)));
                                        $questionPlain = trim((string) ($poll['question'] ?? ''));
                                        $hasIntro = $pollBodyPlain !== '' && $pollBodyPlain !== $questionPlain;
                                    @endphp
                                    @if ($hasIntro)
                                        <div class="mb-2 max-w-none break-words">
                                            @if ($bodyLooksLikeHtml)
                                                {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->highlightMentions($message->body) !!}
                                            @else
                                                {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->render($message->body, $message->tenant_id) !!}
                                            @endif
                                        </div>
                                    @endif
                                    <div class="mt-0.5 max-w-lg rounded-md border border-zinc-200 bg-white p-3
                                                dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                                            <flux:icon name="chart-bar" class="size-3.5" />
                                            Poll
                                        </div>
                                        <div class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $poll['question'] ?? '' }}
                                        </div>
                                        <div class="mt-2 space-y-1.5">
                                            @foreach (($poll['options'] ?? []) as $idx => $option)
                                                @php
                                                    $optVotes = $byOption->get($idx, collect());
                                                    $count = $optVotes->count();
                                                    $pct = $total > 0 ? (int) round(($count / $total) * 100) : 0;
                                                    $mine = $idx === $myOption;
                                                @endphp
                                                <button type="button"
                                                        wire:click="togglePollVote({{ $message->id }}, {{ $idx }})"
                                                        class="group relative w-full overflow-hidden rounded border px-3 py-1.5 text-left text-sm
                                                               {{ $mine
                                                                    ? 'border-sky-500 bg-sky-50 dark:border-sky-500 dark:bg-sky-950/40'
                                                                    : 'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800' }}">
                                                    <span aria-hidden="true"
                                                          class="absolute inset-y-0 left-0 transition-all
                                                                 {{ $mine ? 'bg-sky-200/70 dark:bg-sky-800/40' : 'bg-zinc-100 dark:bg-zinc-800' }}"
                                                          style="width: {{ $pct }}%"></span>
                                                    <span class="relative flex items-center justify-between gap-2">
                                                        <span class="flex min-w-0 items-center gap-1.5">
                                                            @if ($mine)
                                                                <flux:icon name="check-circle" variant="solid" class="size-3.5 flex-shrink-0 text-sky-600 dark:text-sky-400" />
                                                            @endif
                                                            <span class="truncate {{ $mine ? 'font-semibold text-sky-900 dark:text-sky-100' : 'text-zinc-800 dark:text-zinc-200' }}">
                                                                {{ $option }}
                                                            </span>
                                                        </span>
                                                        <span class="flex flex-shrink-0 items-center gap-2 text-xs {{ $mine ? 'text-sky-700 dark:text-sky-300' : 'text-zinc-500' }}">
                                                            @if ($count > 0)
                                                                <span class="flex -space-x-1">
                                                                    @foreach ($optVotes->take(3) as $v)
                                                                        <img src="{{ $v->member?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($v->member?->display_name ?? '?') }}"
                                                                             alt="{{ $v->member?->display_name }}"
                                                                             title="{{ $v->member?->display_name }}"
                                                                             class="size-4 rounded-full bg-zinc-200 ring-2 ring-white
                                                                                    dark:bg-zinc-700 dark:ring-zinc-900">
                                                                    @endforeach
                                                                    @if ($count > 3)
                                                                        <span class="flex size-4 items-center justify-center rounded-full bg-zinc-200 text-[9px] font-medium ring-2 ring-white
                                                                                     dark:bg-zinc-700 dark:ring-zinc-900">
                                                                            +{{ $count - 3 }}
                                                                        </span>
                                                                    @endif
                                                                </span>
                                                            @endif
                                                            <span class="font-medium">{{ $count }}</span>
                                                            <span class="w-8 text-right tabular-nums">{{ $pct }}%</span>
                                                        </span>
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                        <div class="mt-2 text-[11px] text-zinc-500">
                                            {{ $total }} {{ \Illuminate\Support\Str::plural('vote', $total) }}
                                            @if ($myOption >= 0)
                                                · <button type="button"
                                                          wire:click="togglePollVote({{ $message->id }}, {{ $myOption }})"
                                                          class="text-sky-600 hover:underline dark:text-sky-400">
                                                    Remove my vote
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @elseif ($bodyLooksLikeHtml)
                                    {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->highlightMentions($message->body) !!}
                                @else
                                    {!! app(\ArtisanBuild\Bonfire\Support\MarkdownRenderer::class)->render($message->body, $message->tenant_id) !!}
                                @endif
                                @if ($wasEdited && ! $message->isPoll())
                                    <span class="ml-1 text-[11px] italic text-zinc-400 dark:text-zinc-500"
                                          title="Last edited {{ $message->updated_at?->diffForHumans() }}">
                                        (edited)
                                    </span>
                                @endif
                            </div>

                            <div x-show="editing"
                                 x-cloak
                                 class="mt-1 rounded-md border border-sky-400 bg-white
                                        dark:border-sky-600 dark:bg-zinc-900"
                                 @keydown.escape.stop.prevent="editing = false; draft = @js($message->body)"
                                 @keydown.meta.enter.stop.prevent="$wire.editMessage({{ $message->id }}, draft); editing = false"
                                 @keydown.ctrl.enter.stop.prevent="$wire.editMessage({{ $message->id }}, draft); editing = false">
                                <textarea x-model="draft"
                                          id="edit-area-{{ $message->id }}"
                                          rows="3"
                                          class="block w-full resize-y rounded-t-md border-0 bg-transparent p-2 text-sm
                                                 focus:outline-none focus:ring-0
                                                 dark:text-zinc-100"></textarea>
                                <div class="flex items-center justify-between border-t border-zinc-200 px-2 py-1
                                            dark:border-zinc-800">
                                    <span class="text-[10px] text-zinc-500">
                                        <kbd class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">Esc</kbd>
                                        to cancel ·
                                        <kbd class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">⌘↵</kbd>
                                        to save
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button type="button"
                                                @click="editing = false; draft = @js($message->body)"
                                                class="rounded px-2 py-0.5 text-xs text-zinc-600 hover:bg-zinc-100
                                                       dark:text-zinc-400 dark:hover:bg-zinc-800">
                                            Cancel
                                        </button>
                                        <button type="button"
                                                @click="$wire.editMessage({{ $message->id }}, draft); editing = false"
                                                class="rounded bg-sky-600 px-2 py-0.5 text-xs font-medium text-white
                                                       hover:bg-sky-700">
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>

                            @if ($message->relationLoaded('linkPreview') && $message->linkPreview && ! $message->linkPreview->failed)
                                <a href="{{ $message->linkPreview->url }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="mt-2 flex max-w-lg gap-3 rounded-md border border-zinc-200 p-3
                                          hover:bg-zinc-50
                                          dark:border-zinc-800 dark:hover:bg-zinc-900">
                                    @if ($message->linkPreview->image_url)
                                        <img src="{{ $message->linkPreview->image_url }}"
                                             alt=""
                                             class="size-16 flex-shrink-0 rounded object-cover">
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        @if ($message->linkPreview->title)
                                            <div class="truncate text-sm font-medium text-zinc-900
                                                        dark:text-zinc-100">
                                                {{ $message->linkPreview->title }}
                                            </div>
                                        @endif
                                        @if ($message->linkPreview->description)
                                            <p class="mt-0.5 line-clamp-2 text-xs text-zinc-600
                                                      dark:text-zinc-400">
                                                {{ $message->linkPreview->description }}
                                            </p>
                                        @endif
                                        @if ($message->linkPreview->site_name)
                                            <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-500">
                                                {{ $message->linkPreview->site_name }}
                                            </div>
                                        @endif
                                    </div>
                                </a>
                            @endif

                            @if ($message->attachments->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($message->attachments as $attachment)
                                        @if ($attachment->isImage())
                                            <a href="{{ route('bonfire.attachments.show', $attachment) }}"
                                               target="_blank"
                                               rel="noopener">
                                                <img src="{{ route('bonfire.attachments.show', $attachment) }}"
                                                     alt="{{ $attachment->filename }}"
                                                     class="max-h-40 rounded border border-zinc-200
                                                            dark:border-zinc-800">
                                            </a>
                                        @elseif ($attachment->isAudio())
                                            <div class="flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2
                                                        dark:border-zinc-800 dark:bg-zinc-900">
                                                <flux:icon name="microphone" class="size-4 flex-shrink-0 text-zinc-500" />
                                                <audio controls
                                                       preload="metadata"
                                                       src="{{ route('bonfire.attachments.show', $attachment) }}"
                                                       class="h-8 w-72 max-w-full"></audio>
                                                <a href="{{ route('bonfire.attachments.show', $attachment) }}"
                                                   download="{{ $attachment->filename }}"
                                                   class="rounded p-1 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900
                                                          dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                                   title="Download">
                                                    <flux:icon name="arrow-down-tray" class="size-3.5" />
                                                </a>
                                            </div>
                                        @elseif ($attachment->isVideo())
                                            <div class="flex flex-col gap-1">
                                                <video controls
                                                       preload="metadata"
                                                       src="{{ route('bonfire.attachments.show', $attachment) }}"
                                                       class="max-h-72 max-w-md rounded border border-zinc-200
                                                              dark:border-zinc-800"></video>
                                                <a href="{{ route('bonfire.attachments.show', $attachment) }}"
                                                   download="{{ $attachment->filename }}"
                                                   class="inline-flex items-center gap-1 self-start text-[11px] text-zinc-500 hover:text-zinc-900
                                                          dark:hover:text-zinc-200">
                                                    <flux:icon name="arrow-down-tray" class="size-3" />
                                                    Download
                                                </a>
                                            </div>
                                        @else
                                            <a href="{{ route('bonfire.attachments.show', $attachment) }}"
                                               class="flex items-center gap-2 rounded-md border border-zinc-200 p-2 text-xs
                                                      hover:bg-zinc-50
                                                      dark:border-zinc-800 dark:hover:bg-zinc-900">
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $attachment->filename }}
                                                </span>
                                                <span class="text-zinc-500">
                                                    {{ number_format($attachment->size / 1024, 1) }} KB
                                                </span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>

                    @if (! $message->trashed() && $message->replies->isNotEmpty())
                        <button wire:click="openThread({{ $message->id }})"
                                type="button"
                                class="mt-1 inline-flex items-center gap-1.5 rounded border border-transparent
                                       px-1.5 py-0.5 text-xs font-medium text-sky-700
                                       hover:border-zinc-200 hover:bg-white
                                       dark:text-sky-300 dark:hover:border-zinc-700 dark:hover:bg-zinc-900">
                            <flux:icon name="chat-bubble-left-right" class="size-3.5" />
                            {{ $message->replies->count() }}
                            {{ \Illuminate\Support\Str::plural('reply', $message->replies->count()) }}
                        </button>
                    @endif
                </div>

                @if (! $message->trashed())
                    <div class="pointer-events-none absolute right-4 -top-3 z-10 hidden items-center gap-0.5
                                rounded-md border border-zinc-200 bg-white p-0.5 shadow-sm
                                group-hover:pointer-events-auto group-hover:flex
                                dark:border-zinc-700 dark:bg-zinc-900">
                        <button wire:click="openThread({{ $message->id }})"
                                type="button"
                                title="Reply in thread"
                                class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900
                                       dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                            <flux:icon name="chat-bubble-left-right" class="size-4" />
                        </button>
                        <button type="button"
                                title="Copy link to message"
                                @click="copyMessageLink({{ $message->id }}, $event.currentTarget)"
                                class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900
                                       dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                            <flux:icon name="link" class="size-4" />
                        </button>
                        <button type="button"
                                title="Forward to another channel"
                                @click="openForward({{ $message->id }})"
                                class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900
                                       dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                            <flux:icon name="paper-airplane" class="size-4" />
                        </button>
                        @if ($this->canPin($message))
                            <button type="button"
                                    wire:click="togglePin({{ $message->id }})"
                                    title="{{ $message->pinned_at ? 'Unpin from channel' : 'Pin to channel' }}"
                                    class="rounded p-1 {{ $message->pinned_at ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400' }}
                                           hover:bg-amber-50 hover:text-amber-600
                                           dark:hover:bg-amber-950/40 dark:hover:text-amber-400">
                                @if ($message->pinned_at)
                                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2z"/>
                                    </svg>
                                @else
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                                        <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2z"/>
                                    </svg>
                                @endif
                            </button>
                        @endif
                        @if ($this->canEdit($message))
                            <button
                                    type="button"
                                    @click="$dispatch('bonfire-edit-message-{{ $message->id }}')"
                                    title="Edit"
                                    class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900
                                           dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                <flux:icon name="pencil-square" class="size-4" />
                            </button>
                        @endif
                        @if ($this->canDelete($message))
                            <button
                                    type="button"
                                    @click="askDelete({{ $message->id }}, @js(\Illuminate\Support\Str::limit($message->body, 120)))"
                                    title="Delete"
                                    class="rounded p-1 text-zinc-500 hover:bg-rose-50 hover:text-rose-600
                                           dark:text-zinc-400 dark:hover:bg-rose-950 dark:hover:text-rose-400">
                                <flux:icon name="trash" class="size-4" />
                            </button>
                        @endif
                    </div>
                @endif
            </li>
        @empty
            <li class="p-6 text-center text-sm text-zinc-600 dark:text-zinc-400">
                No messages yet. Say hello.
            </li>
        @endforelse
    </ul>

    <div x-show="typingLabel"
         x-text="typingLabel"
         class="px-4 py-1 text-xs italic text-zinc-500 dark:text-zinc-400"></div>

    <div x-show="copyToast"
         x-transition.opacity.duration.150ms
         class="pointer-events-none fixed bottom-20 left-1/2 z-50 -translate-x-1/2 rounded-md bg-zinc-900
                px-3 py-1.5 text-xs font-medium text-white shadow-lg
                dark:bg-zinc-100 dark:text-zinc-900"
         x-text="copyToast"
         style="display: none;"></div>

    <flux:modal name="delete-message" class="max-w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Delete message?</flux:heading>
                <flux:text class="mt-2">
                    This will remove the message for everyone. You can't undo this.
                </flux:text>
            </div>

            <div x-show="pendingDeletePreview"
                 class="rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-700
                        dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                <span x-text="pendingDeletePreview"></span>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" @click="confirmDelete()">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="forward-message" class="max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Forward message</flux:heading>
                <flux:text class="mt-1">Send this message to another channel. Add an optional note.</flux:text>
            </div>

            <textarea x-model="forwardNote"
                      placeholder="Add a note (optional)"
                      rows="2"
                      class="block w-full resize-none rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm
                             focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500
                             dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>

            <div>
                <div class="relative">
                    <flux:icon name="magnifying-glass"
                               class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-400" />
                    <input type="search"
                           x-model="forwardSearch"
                           placeholder="Find channel"
                           class="block w-full rounded-md border border-zinc-300 bg-white py-1.5 pl-8 pr-3 text-sm
                                  focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500
                                  dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                </div>

                <ul class="mt-2 max-h-72 space-y-0.5 overflow-y-auto pr-1">
                    @foreach ($this->forwardableRooms as $r)
                        <li x-show="forwardSearch === '' || '{{ \Illuminate\Support\Str::slug($r->name) }}'.includes(forwardSearch.toLowerCase()) || '{{ strtolower($r->name) }}'.includes(forwardSearch.toLowerCase())"
                            class="rounded">
                            <button type="button"
                                    @click="submitForward({{ $r->id }})"
                                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm
                                           hover:bg-sky-50 hover:text-sky-700
                                           dark:hover:bg-sky-950/40 dark:hover:text-sky-300">
                                @if ($r->is_private)
                                    <flux:icon name="lock-closed" class="size-3.5 text-zinc-500" />
                                @else
                                    <span class="w-3.5 text-center text-zinc-500">#</span>
                                @endif
                                <span class="flex-1 truncate">{{ $r->name }}</span>
                                @if ((int) $r->id === (int) $this->room->id)
                                    <span class="text-[10px] text-zinc-400">(this channel)</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
