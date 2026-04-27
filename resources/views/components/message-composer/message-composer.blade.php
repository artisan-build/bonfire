<div x-data="{
          lastWhisper: 0,
          showEmoji: false,
          showMentions: false,
          mentionQuery: '',
          mentionAnchor: null,
          mentionHead: null,
          mentionIndex: 0,
          emojis: ['👍','👏','🙏','🎉','🔥','🚀','💯','✅','❌','⭐','❤️','💙','😂','😅','😊','😢','😮','😡','🤔','👀','🙌','🤝','☕','🥳'],

          recording: null,
          recordArmed: false,
          recordKind: null,
          recordStartedAt: null,
          recordElapsed: 0,
          recordTimer: null,

          scheduleOpen: false,
          scheduledFor: @entangle('scheduledFor'),
          scheduleCustom: '',

          editorHeight: parseInt(localStorage.getItem('bonfire.editorHeight') ?? '112', 10),
          resizingEditor: false,
          startEditorResize(evt) {
              this.resizingEditor = true;
              const startY = evt.clientY;
              const startHeight = this.editorHeight;
              document.body.style.cursor = 'row-resize';
              document.body.style.userSelect = 'none';
              const move = (e) => {
                  if (! this.resizingEditor) return;
                  const delta = startY - e.clientY;
                  const next = Math.max(72, Math.min(480, startHeight + delta));
                  this.editorHeight = next;
                  localStorage.setItem('bonfire.editorHeight', String(next));
              };
              const up = () => {
                  this.resizingEditor = false;
                  document.body.style.cursor = '';
                  document.body.style.userSelect = '';
                  window.removeEventListener('mousemove', move);
                  window.removeEventListener('mouseup', up);
              };
              window.addEventListener('mousemove', move);
              window.addEventListener('mouseup', up);
          },
          resetEditorHeight() {
              this.editorHeight = 112;
              localStorage.setItem('bonfire.editorHeight', '112');
          },

          init() {
              const root = this.$root;
              let lastEnterAt = 0;
              let firstEnterWasInList = false;
              const self = this;
              const getEditor = () => {
                  const el = root.querySelector('[data-flux-editor]');
                  return el?._tiptap || el?.editor || null;
              };
              this._getEditor = getEditor;

              // Draft persistence: scope by room + thread parent + user, so switching
              // channels or refreshing restores exactly what you were typing.
              const draftKey = 'bonfire.draft.{{ $room->id }}.{{ $parentId ?? 'root' }}.{{ auth()->id() ?? 'guest' }}';
              this._draftKey = draftKey;
              this._draftSaveTimer = null;

              const saveDraft = (html) => {
                  clearTimeout(this._draftSaveTimer);
                  this._draftSaveTimer = setTimeout(() => {
                      try {
                          const trimmed = (html || '').replace(/<p>\s*<\/p>/g, '').trim();
                          if (trimmed === '') localStorage.removeItem(draftKey);
                          else localStorage.setItem(draftKey, html);
                      } catch (e) {}
                  }, 400);
              };

              const attachEditor = () => {
                  const editor = getEditor();
                  if (! editor) { setTimeout(attachEditor, 120); return; }
                  editor.on('update', () => self.checkMentionTrigger(editor));
                  editor.on('selectionUpdate', () => self.checkMentionTrigger(editor));
                  editor.on('update', () => {
                      try { saveDraft(editor.getHTML()); } catch (e) {}
                  });

                  // Restore any saved draft on mount.
                  try {
                      const saved = localStorage.getItem(draftKey);
                      if (saved && saved.trim() !== '' && saved !== '<p></p>') {
                          editor.commands.setContent(saved, false);
                          self.$wire.set('body', saved, false);
                      }
                  } catch (e) {}
              };
              attachEditor();


              const handleShortcut = (e) => {
                  if (! root.contains(e.target)) return;
                  if (! (e.target.matches('[contenteditable=true]') || e.target.closest('[contenteditable=true]'))) return;

                  if (self.showMentions && ['ArrowDown', 'ArrowUp', 'Enter', 'Escape', 'Tab'].includes(e.key)) {
                      const list = self.filteredMembers;
                      if (e.key === 'ArrowDown') {
                          e.preventDefault();
                          self.mentionIndex = Math.min(list.length - 1, self.mentionIndex + 1);
                          return;
                      }
                      if (e.key === 'ArrowUp') {
                          e.preventDefault();
                          self.mentionIndex = Math.max(0, self.mentionIndex - 1);
                          return;
                      }
                      if (e.key === 'Enter' || e.key === 'Tab') {
                          if (list.length > 0) {
                              e.preventDefault();
                              e.stopPropagation();
                              self.selectMention(list[self.mentionIndex].display_name);
                              return;
                          }
                      }
                      if (e.key === 'Escape') {
                          e.preventDefault();
                          self.showMentions = false;
                          return;
                      }
                  }

                  if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;

                  const now = Date.now();
                  const doubleTap = now - lastEnterAt < 250;

                  if (doubleTap) {
                      e.preventDefault();
                      e.stopPropagation();
                      if (firstEnterWasInList) {
                          getEditor()?.chain().focus().undo().run();
                      }
                      self.submitForm();
                      lastEnterAt = 0;
                      firstEnterWasInList = false;
                      return;
                  }

                  const editor = getEditor();
                  const inList = editor && (editor.isActive('bulletList') || editor.isActive('orderedList') || editor.isActive('listItem'));
                  lastEnterAt = now;
                  firstEnterWasInList = !! inList;

                  if (inList) return;

                  e.preventDefault();
                  e.stopPropagation();
                  self.submitForm();
              };
              document.addEventListener('keydown', handleShortcut, true);
          },

          checkMentionTrigger(editor) {
              const { from } = editor.state.selection;
              const start = Math.max(0, from - 60);
              const textBefore = editor.state.doc.textBetween(start, from, '\n', ' ');
              const match = textBefore.match(/(?:^|\s)@([\w-]*)$/);
              if (match) {
                  this.mentionQuery = match[1];
                  this.mentionAnchor = from - match[1].length - 1;
                  this.mentionHead = from;
                  this.mentionIndex = 0;
                  this.showMentions = true;
              } else if (this.showMentions) {
                  this.showMentions = false;
                  this.mentionQuery = '';
                  this.mentionAnchor = null;
                  this.mentionHead = null;
              }
          },

          submitForm() {
              this.showMentions = false;
              this.showEmoji = false;
              const form = this.$refs.form;
              if (form) form.requestSubmit();
          },

          whisperTyping() {
              if (typeof window.Echo === 'undefined') return;
              const now = Date.now();
              if (now - this.lastWhisper < 1500) return;
              this.lastWhisper = now;
              window.Echo.join('bonfire.room.{{ $room->id }}')
                  .whisper('user.typing', {
                      member_id: {{ $this->member?->id ?? 0 }},
                      display_name: @js($this->member?->display_name ?? ''),
                  });
          },

          insertIntoEditor(text) {
              // Search document-wide — the emoji popover lives in a nested x-data
              // where this.$root points at the popover, not the composer.
              const el = document.querySelector('.bonfire-composer [data-flux-editor]')
                  ?? document.querySelector('[data-flux-editor]');
              const editor = el?._tiptap || el?.editor;
              if (editor) {
                  editor.chain().focus().insertContent(text).run();
              }
          },

          toggleEmoji() {
              this.showMentions = false;
              this.showEmoji = ! this.showEmoji;
          },
          pickEmoji(emoji) {
              this.insertIntoEditor(emoji);
              this.showEmoji = false;
          },

          pollPreview() {
              const raw = (this.body || '').replace(/<[^>]+>/g, ' ')
                  .replace(/&nbsp;/g, ' ')
                  .replace(/&amp;/g, '&')
                  .replace(/&lt;/g, '<')
                  .replace(/&gt;/g, '>')
                  .replace(/&#39;/g, '\u0027')
                  .replace(/&quot;/g, '\u0022')
                  .trim();
              const m = raw.match(/\/poll\s+(.+)$/is);
              if (! m) return null;
              const parts = m[1].split('|').map(s => s.trim()).filter(Boolean);
              if (parts.length < 3) return { question: parts[0] || '', options: parts.slice(1), valid: false };
              return { question: parts[0], options: parts.slice(1, 11), valid: true };
          },

          openMentions() {
              this.showEmoji = false;
              this.mentionQuery = '';
              this.mentionIndex = 0;
              this.insertIntoEditor('\u0040');
              // checkMentionTrigger will pick it up from the editor.update event
          },
          selectMention(name) {
              const editor = this._getEditor ? this._getEditor() : null;
              const slug = name.replace(/\s+/g, '-');
              const at = '\u0040';
              const mentionHtml = '<a href=&quot;#mention-' + slug + '&quot;>' + at + name + '</a>&nbsp;';

              if (editor && this.mentionAnchor !== null && this.mentionHead !== null) {
                  editor.chain().focus()
                      .deleteRange({ from: this.mentionAnchor, to: this.mentionHead })
                      .insertContent(mentionHtml.replace(/&quot;/g, '\u0022'))
                      .run();
                  editor.chain().focus().unsetMark('link').run();
              } else {
                  this.insertIntoEditor(at + name + ' ');
              }
              this.showMentions = false;
              this.mentionQuery = '';
              this.mentionAnchor = null;
              this.mentionHead = null;
              this.mentionIndex = 0;
          },
          get filteredMembers() {
              const q = (this.mentionQuery || '').toLowerCase();
              const all = @js($this->mentionables);
              const specials = [
                  { id: 'channel', display_name: 'channel', avatar_url: null, broadcast: true, hint: 'Everyone in this channel' },
                  { id: 'here', display_name: 'here', avatar_url: null, broadcast: true, hint: 'Active members only' },
                  { id: 'everyone', display_name: 'everyone', avatar_url: null, broadcast: true, hint: 'Everyone in the workspace' },
              ];
              if (q === '') {
                  return [...specials, ...all.slice(0, 6)];
              }
              const s = specials.filter(sp => sp.display_name.startsWith(q));
              const m = all.filter(m => m.display_name.toLowerCase().includes(q)).slice(0, 6);
              return [...s, ...m];
          },

          triggerFile() {
              this.$refs.fileInput?.click();
          },

          async toggleRecordKind(kind) {
              if (this.recording) return;
              const wasKind = this.recordKind;
              if (this.recordArmed) this.cancelPreview();
              if (wasKind === kind) return;
              await this.openPreview(kind);
          },

          async openPreview(kind) {
              if (this.recording || this.recordArmed) return;
              if (! window.isSecureContext) {
                  alert('Recording requires a secure connection (https://). Run `herd secure artisan-community` and reload over HTTPS.');
                  return;
              }
              if (! navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                  alert('Your browser does not support recording.');
                  return;
              }
              try {
                  const constraints = kind === 'video'
                      ? { audio: true, video: { width: 640, height: 480 } }
                      : { audio: true };
                  const stream = await navigator.mediaDevices.getUserMedia(constraints);
                  this._stream = stream;
                  this.recordKind = kind;
                  this.recordArmed = true;
                  this.$nextTick(() => {
                      if (kind === 'video' && this.$refs.previewVideo) {
                          this.$refs.previewVideo.srcObject = stream;
                      }
                      if (kind === 'audio' && this.$refs.previewCanvas) {
                          this.startWaveform(stream);
                      }
                  });
              } catch (err) {
                  console.error(err);
                  alert('Could not start preview: ' + err.message);
              }
          },

          cancelPreview() {
              if (this._stream) {
                  this._stream.getTracks().forEach(t => t.stop());
                  this._stream = null;
              }
              if (this._audioCtx) { try { this._audioCtx.close(); } catch (e) {} this._audioCtx = null; }
              if (this._waveformRaf) { cancelAnimationFrame(this._waveformRaf); this._waveformRaf = null; }
              this.recording = null;
              this.recordArmed = false;
              this.recordKind = null;
              this.recordStartedAt = null;
              this.recordElapsed = 0;
              clearInterval(this.recordTimer);
          },

          beginRecording() {
              if (! this._stream || this.recording) return;
              const kind = this.recordKind;
              const stream = this._stream;
              const mimeType = kind === 'video'
                  ? (MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus') ? 'video/webm;codecs=vp8,opus' : 'video/webm')
                  : (MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm');
              const recorder = new MediaRecorder(stream, { mimeType });
              const chunks = [];
              recorder.addEventListener('dataavailable', (e) => { if (e.data.size) chunks.push(e.data); });
              recorder.addEventListener('stop', () => {
                  stream.getTracks().forEach(t => t.stop());
                  if (this._audioCtx) { try { this._audioCtx.close(); } catch (e) {} this._audioCtx = null; }
                  if (this._waveformRaf) { cancelAnimationFrame(this._waveformRaf); this._waveformRaf = null; }
                  const blob = new Blob(chunks, { type: mimeType });
                  const filename = `${kind}-${Date.now()}.webm`;
                  const file = new File([blob], filename, { type: mimeType });
                  this.$wire.upload('pendingAttachments', file, null, null, null, 'pendingAttachments.' + (this.$wire.pendingAttachments?.length ?? 0));
                  this.recording = null;
                  this.recordArmed = false;
                  this.recordKind = null;
                  this.recordStartedAt = null;
                  this.recordElapsed = 0;
                  this._stream = null;
                  clearInterval(this.recordTimer);
              });
              this.recording = recorder;
              this.recordArmed = false;
              this.recordStartedAt = Date.now();
              this.recordElapsed = 0;
              this.recordTimer = setInterval(() => {
                  this.recordElapsed = Math.floor((Date.now() - this.recordStartedAt) / 1000);
                  if (this.recordElapsed >= 120) this.stopRecording();
              }, 500);
              recorder.start();
          },

          startWaveform(stream) {
              const canvas = this.$refs.previewCanvas;
              if (! canvas) return;
              const AudioCtx = window.AudioContext || window.webkitAudioContext;
              if (! AudioCtx) return;
              const ctx = canvas.getContext('2d');
              const audioCtx = new AudioCtx();
              const source = audioCtx.createMediaStreamSource(stream);
              const analyser = audioCtx.createAnalyser();
              analyser.fftSize = 256;
              source.connect(analyser);
              this._audioCtx = audioCtx;
              const buf = new Uint8Array(analyser.frequencyBinCount);
              const draw = () => {
                  this._waveformRaf = requestAnimationFrame(draw);
                  analyser.getByteFrequencyData(buf);
                  const w = canvas.width = canvas.clientWidth;
                  const h = canvas.height = canvas.clientHeight;
                  ctx.clearRect(0, 0, w, h);
                  const bars = 40;
                  const step = Math.floor(buf.length / bars);
                  const barW = Math.max(2, (w / bars) - 2);
                  const accent = document.documentElement.classList.contains('dark') ? '#fca5a5' : '#e11d48';
                  ctx.fillStyle = accent;
                  for (let i = 0; i < bars; i++) {
                      const v = buf[i * step] / 255;
                      const barH = Math.max(2, v * h * 0.9);
                      const x = i * (barW + 2);
                      ctx.fillRect(x, (h - barH) / 2, barW, barH);
                  }
              };
              draw();
          },

          stopRecording() {
              if (this.recording && this.recording.state !== 'inactive') {
                  this.recording.stop();
              }
          },

          recordLabel() {
              const s = this.recordElapsed % 60;
              const m = Math.floor(this.recordElapsed / 60);
              return `${m}:${s.toString().padStart(2, '0')}`;
          },

          chooseSchedule(value) {
              this.scheduledFor = value;
              this.scheduleOpen = false;
          },
          clearSchedule() {
              this.scheduledFor = null;
              this.scheduleCustom = '';
              this.scheduleOpen = false;
          },
          applyCustomSchedule() {
              if (! this.scheduleCustom) return;
              this.scheduledFor = this.scheduleCustom;
              this.scheduleOpen = false;
          },
          get scheduleLabel() {
              if (! this.scheduledFor) return '';
              try {
                  const d = new Date(this.scheduledFor);
                  return d.toLocaleString(undefined, { weekday: 'short', hour: 'numeric', minute: '2-digit' });
              } catch (e) {
                  return this.scheduledFor;
              }
          },
          get presetTimes() {
              const now = new Date();
              const in1h = new Date(now.getTime() + 60 * 60 * 1000);
              const tmr = new Date(now); tmr.setDate(tmr.getDate() + 1); tmr.setHours(9, 0, 0, 0);
              const nextMon = new Date(now);
              const day = nextMon.getDay();
              const add = (8 - day) % 7 || 7;
              nextMon.setDate(nextMon.getDate() + add); nextMon.setHours(9, 0, 0, 0);
              const fmt = (d) => d.toISOString().slice(0, 16);
              return [
                  { label: 'In 1 hour', value: fmt(in1h) },
                  { label: 'Tomorrow at 9:00 AM', value: fmt(tmr) },
                  { label: 'Next Monday at 9:00 AM', value: fmt(nextMon) },
              ];
          },
      }"
      @click.outside="showEmoji = false; showMentions = false; scheduleOpen = false"
      :style="'--bonfire-editor-height: ' + editorHeight + 'px'"
      class="bonfire-composer relative">

    <div @mousedown.prevent="startEditorResize($event)"
         @dblclick="resetEditorHeight()"
         :class="resizingEditor ? 'bg-sky-500' : 'bg-zinc-200 hover:bg-zinc-400 dark:bg-zinc-700 dark:hover:bg-zinc-500'"
         title="Drag to resize · Double-click to reset"
         class="mx-auto mb-2 h-2 w-20 cursor-row-resize rounded-full"></div>

    @if ($this->lastScheduledAt)
        @php
            $scheduledCarbon = \Illuminate\Support\Carbon::parse($this->lastScheduledAt);
            $scheduledLabel = $scheduledCarbon->isToday()
                ? 'today at '.$scheduledCarbon->format('g:i A')
                : $scheduledCarbon->format('D M j').' at '.$scheduledCarbon->format('g:i A');
            $scheduledTotal = $this->scheduledMessagesCount();
        @endphp

        <div class="mb-2 flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs
                    text-zinc-700
                    dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
            <flux:icon name="clock" class="size-4 flex-shrink-0 text-zinc-500" />
            <span>Your message will be sent <strong>{{ $scheduledLabel }}</strong>.</span>
            <button type="button"
                    class="font-medium text-sky-600 hover:underline dark:text-sky-400">
                See all scheduled messages
                @if ($scheduledTotal > 1)
                    ({{ $scheduledTotal }})
                @endif
            </button>
            <button type="button"
                    wire:click="dismissScheduled"
                    class="ml-auto rounded p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700
                           dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
                <flux:icon name="x-mark" class="size-3.5" />
            </button>
        </div>
    @endif

    <form wire:submit="send"
          x-ref="form"
          class="flex flex-col gap-2">

        @if ($this->pendingAttachments)
            <div class="flex flex-wrap gap-2 rounded-md border border-zinc-200 p-2
                        dark:border-zinc-700">
                @foreach ($this->pendingAttachments as $index => $pending)
                    <div class="flex items-center gap-2 rounded-md border border-zinc-200 px-2 py-1 text-xs
                                dark:border-zinc-700">
                        <flux:icon name="paper-clip" class="size-3.5 text-zinc-500" />
                        <span class="max-w-40 truncate text-zinc-800 dark:text-zinc-200">
                            {{ $pending->getClientOriginalName() }}
                        </span>
                        <button type="button"
                                wire:click="removeAttachment({{ $index }})"
                                class="text-zinc-500 hover:text-rose-600 dark:hover:text-rose-400">
                            <flux:icon name="x-mark" class="size-3.5" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <div x-show="recordArmed || recording"
             :class="recording ? 'border-rose-200 bg-rose-50 dark:border-rose-900/50 dark:bg-rose-950/30'
                                : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900'"
             class="flex flex-col gap-2 rounded-md border p-2"
             style="display: none;">

            <div x-show="recordArmed && ! recording"
                 class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                <flux:icon name="eye" class="size-4 text-zinc-500" />
                <span class="font-medium">
                    Preview — click Record when ready
                </span>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button"
                            @click="cancelPreview()"
                            class="rounded px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-200
                                   dark:text-zinc-300 dark:hover:bg-zinc-700">
                        Cancel
                    </button>
                    <button type="button"
                            @click="beginRecording()"
                            class="inline-flex items-center gap-1 rounded bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white
                                   hover:bg-rose-700">
                        <span class="size-2 rounded-full bg-white"></span>
                        Record
                    </button>
                </div>
            </div>

            <div x-show="recording"
                 class="flex items-center gap-2 text-sm text-rose-700 dark:text-rose-300">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-rose-500"></span>
                </span>
                <span class="font-medium">
                    Recording <span x-text="recordKind"></span>
                </span>
                <span class="text-rose-600/70 dark:text-rose-400/70" x-text="recordLabel()"></span>
                <button type="button"
                        @click="stopRecording()"
                        class="ml-auto inline-flex items-center gap-1 rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white
                               hover:bg-rose-700">
                    <flux:icon name="stop" class="size-3.5" />
                    Stop
                </button>
            </div>

            <video x-show="recordKind === 'video'"
                   x-ref="previewVideo"
                   autoplay
                   muted
                   playsinline
                   :class="recording ? 'border-rose-200 dark:border-rose-900/50' : 'border-zinc-200 dark:border-zinc-700'"
                   class="max-h-64 w-full rounded border bg-black"
                   style="display: none;"></video>

            <div x-show="recordKind === 'audio'"
                 :class="recording ? 'border-rose-200 dark:border-rose-900/50' : 'border-zinc-200 dark:border-zinc-700'"
                 class="flex items-center justify-center rounded border bg-white py-2
                        dark:bg-zinc-900"
                 style="display: none;">
                <canvas x-ref="previewCanvas"
                        class="h-12 w-full"></canvas>
            </div>
        </div>

        @if ($pendingPoll !== null)
            <div class="mb-1 block w-full rounded-md border border-sky-200 bg-sky-50 p-3
                        dark:border-sky-800 dark:bg-sky-950/40">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <flux:icon name="chart-bar" class="size-4 shrink-0 text-sky-600 dark:text-sky-400" />
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">
                            {{ __('Poll attached') }}
                        </span>
                        <span class="text-[11px] text-sky-500/80 dark:text-sky-400/80">
                            · {{ trans_choice('{1}1 option|[2,*]:count options', count($pendingPoll['options'] ?? [])) }}
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button"
                                wire:click="editPendingPoll"
                                title="{{ __('Edit poll') }}"
                                class="rounded p-1 text-sky-700 hover:bg-sky-100
                                       dark:text-sky-300 dark:hover:bg-sky-900/40">
                            <flux:icon name="pencil-square" class="size-4" />
                        </button>
                        <button type="button"
                                wire:click="discardPendingPoll"
                                title="{{ __('Remove poll') }}"
                                class="rounded p-1 text-sky-700 hover:bg-sky-100
                                       dark:text-sky-300 dark:hover:bg-sky-900/40">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                </div>
                @if (! empty($pendingPoll['question']))
                    <div class="mt-1 truncate text-sm text-zinc-900 dark:text-zinc-100">
                        {{ $pendingPoll['question'] }}
                    </div>
                @endif
            </div>
        @endif

        <template x-if="!@js((bool) $pendingPoll) && pollPreview()">
            <div class="mb-1 rounded-md border px-2.5 py-1.5 text-xs"
                 :class="pollPreview().valid
                    ? 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200'
                    : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200'">
                <div class="flex items-center gap-1.5 font-semibold">
                    <flux:icon name="chart-bar" class="size-3.5" />
                    <span x-text="pollPreview().valid ? 'Poll ready to send' : 'Poll needs at least 2 options'"></span>
                </div>
                <div class="mt-0.5 text-[11px] opacity-90">
                    <span x-text="'Question: ' + (pollPreview().question || '(empty)')"></span>
                    <span class="mx-1">·</span>
                    <span x-text="pollPreview().options.length + ' option(s): ' + pollPreview().options.join(' / ')"></span>
                </div>
            </div>
        </template>

        <flux:editor wire:model="body"
                     @input.debounce.250ms="whisperTyping()"
                     toolbar="bold italic strike | bullet ordered blockquote | link | code" />

        @if ($this->parentId !== null)
            <label class="flex items-center gap-2 px-1 text-xs text-zinc-600 dark:text-zinc-400">
                <input type="checkbox" wire:model="alsoSendToChannel"
                       class="size-3.5 rounded border-zinc-300 text-sky-600
                              focus:ring-sky-500
                              dark:border-zinc-600 dark:bg-zinc-900" />
                Also send to <span class="font-semibold">#{{ $room->name }}</span>
            </label>
        @endif

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-0.5 text-zinc-500 dark:text-zinc-400">
                <input type="file"
                       wire:model="pendingAttachments"
                       x-ref="fileInput"
                       multiple
                       class="hidden" />

                <flux:dropdown>
                    <flux:button type="button" variant="ghost" size="sm" icon="paper-clip" title="Attach">
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item icon="arrow-up-tray" @click="triggerFile()">
                            Upload from computer
                        </flux:menu.item>
                        <flux:menu.item icon="clock" disabled>
                            Recent files
                        </flux:menu.item>
                        <flux:menu.item icon="list-bullet" disabled>
                            From list
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <button type="button"
                        title="Record voice note"
                        @click="toggleRecordKind('audio')"
                        :class="recordKind === 'audio' ? 'text-rose-600 dark:text-rose-400' : ''"
                        class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                               dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="microphone" class="size-4" />
                </button>
                <button type="button"
                        title="Record video clip"
                        @click="toggleRecordKind('video')"
                        :class="recordKind === 'video' ? 'text-rose-600 dark:text-rose-400' : ''"
                        class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                               dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="video-camera" class="size-4" />
                </button>

                <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>

                <button type="button"
                        title="Emoji"
                        @mousedown.prevent @click.stop="toggleEmoji()"
                        class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                               dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="face-smile" class="size-4" />
                </button>
                <button type="button"
                        title="Mention"
                        @mousedown.prevent @click="openMentions()"
                        class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                               dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="at-symbol" class="size-4" />
                </button>
                <button type="button"
                        title="Create a poll"
                        @mousedown.prevent
                        @click="$dispatch('modal-show', { name: 'create-poll' })"
                        class="rounded p-1.5 hover:bg-zinc-100 hover:text-zinc-900
                               dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    <flux:icon name="chart-bar" class="size-4" />
                </button>
            </div>

            <div class="flex items-center gap-2">
                <span x-show="scheduledFor"
                      class="inline-flex items-center gap-1 rounded-md bg-sky-100 px-2 py-1 text-xs text-sky-800
                             dark:bg-sky-950/50 dark:text-sky-300"
                      style="display: none;">
                    <flux:icon name="clock" class="size-3.5" />
                    Scheduled: <span x-text="scheduleLabel"></span>
                    <button type="button" @click="clearSchedule()" class="hover:text-sky-950 dark:hover:text-sky-100">
                        <flux:icon name="x-mark" class="size-3.5" />
                    </button>
                </span>


                <div class="flex items-stretch">
                    <flux:button type="submit" variant="primary" size="sm" icon="paper-airplane"
                                 class="rounded-r-none!">
                        <span x-text="scheduledFor ? 'Schedule' : 'Send'"></span>
                    </flux:button>
                    <div class="relative">
                        <button type="button"
                                @click.stop="scheduleOpen = ! scheduleOpen"
                                title="Schedule for later"
                                class="flex h-full items-center rounded-r-md border-l border-white/20 bg-zinc-800 px-1.5 text-white
                                       hover:bg-zinc-700
                                       dark:border-black/20">
                            <flux:icon name="chevron-down" class="size-3.5" />
                        </button>

                        <div x-show="scheduleOpen"
                             x-transition.opacity.duration.100ms
                             @click.stop
                             class="absolute bottom-full right-0 z-20 mb-1 w-72 overflow-hidden rounded-lg border border-zinc-200
                                    bg-white shadow-lg
                                    dark:border-zinc-700 dark:bg-zinc-900"
                             style="display: none;">
                            <div class="border-b border-zinc-200 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-500
                                        dark:border-zinc-800 dark:text-zinc-400">
                                Schedule for later
                            </div>
                            <ul class="py-1">
                                <template x-for="preset in presetTimes" :key="preset.value">
                                    <li>
                                        <button type="button"
                                                @click="chooseSchedule(preset.value)"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm
                                                       hover:bg-zinc-100
                                                       dark:hover:bg-zinc-800">
                                            <flux:icon name="clock" class="size-3.5 text-zinc-500" />
                                            <span x-text="preset.label"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800">
                                <label class="block text-[11px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    Custom time
                                </label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input type="datetime-local"
                                           x-model="scheduleCustom"
                                           class="flex-1 rounded border border-zinc-300 bg-white px-2 py-1 text-sm
                                                  focus:border-zinc-500 focus:outline-none
                                                  dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                                    <button type="button"
                                            @click="applyCustomSchedule()"
                                            class="rounded bg-zinc-900 px-2 py-1 text-sm text-white hover:bg-zinc-700
                                                   dark:bg-white dark:text-zinc-900">
                                        Set
                                    </button>
                                </div>
                                <button x-show="scheduledFor"
                                        type="button"
                                        @click="clearSchedule()"
                                        class="mt-2 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100">
                                    Clear schedule
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div x-show="showEmoji"
         x-data="{
             baseCats: (window.BonfireEmojis || []),
             emojiSearch: '',
             recentKey: 'bonfire.emoji.recent.{{ auth()->id() ?? 'guest' }}',
             recent: (() => {
                 try { return JSON.parse(localStorage.getItem('bonfire.emoji.recent.{{ auth()->id() ?? 'guest' }}') || '[]'); }
                 catch (e) { return []; }
             })(),
             emojiActiveCat: '',
             init() {
                 this.emojiActiveCat = this.recent.length > 0 ? 'recent' : (this.baseCats[0]?.key || 'smileys');
             },
             trackRecent(glyph) {
                 const idx = this.recent.indexOf(glyph);
                 if (idx !== -1) this.recent.splice(idx, 1);
                 this.recent.unshift(glyph);
                 this.recent = this.recent.slice(0, 24);
                 localStorage.setItem(this.recentKey, JSON.stringify(this.recent));
             },
             get emojiCats() {
                 if (this.recent.length === 0) return this.baseCats;
                 return [
                     {
                         key: 'recent',
                         label: 'Recently used',
                         tab: '🕒',
                         emojis: this.recent.map(c => ({ c, k: 'recent' })),
                     },
                     ...this.baseCats,
                 ];
             },
             get emojiVisible() {
                 const q = this.emojiSearch.trim().toLowerCase();
                 if (q === '') {
                     return this.emojiCats.filter(c => c.key === this.emojiActiveCat);
                 }
                 return this.emojiCats
                     .filter(c => c.key !== 'recent')
                     .map(cat => ({
                         ...cat,
                         emojis: cat.emojis.filter(e => e.k.includes(q)),
                     }))
                     .filter(cat => cat.emojis.length > 0);
             },
         }"
         x-transition.opacity.duration.100ms
         @click.stop
         class="absolute left-0 z-20 flex w-96 flex-col overflow-hidden rounded-lg border border-zinc-200
                bg-white shadow-lg
                dark:border-zinc-700 dark:bg-zinc-900"
         style="display: none; bottom: calc(var(--bonfire-editor-height, 7rem) + 2.75rem);">

        <div class="flex items-center gap-1 border-b border-zinc-200 p-2
                    dark:border-zinc-800">
            <input type="search"
                   x-model="emojiSearch"
                   placeholder="Search all emoji"
                   class="h-7 flex-1 rounded border border-zinc-200 bg-zinc-50 px-2 text-xs
                          focus:border-zinc-400 focus:outline-none focus:ring-0
                          dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
        </div>

        <div x-show="! emojiSearch"
             class="flex items-center gap-0 border-b border-zinc-200 px-1
                    dark:border-zinc-800">
            <template x-for="cat in emojiCats" :key="cat.key">
                <button type="button"
                        @mousedown.prevent @click="emojiActiveCat = cat.key"
                        :class="emojiActiveCat === cat.key ? 'border-sky-500' : 'border-transparent opacity-60 hover:opacity-100'"
                        :title="cat.label"
                        class="flex h-8 flex-1 items-center justify-center border-b-2 text-base"
                        x-text="cat.tab"></button>
            </template>
        </div>

        <div class="max-h-72 overflow-y-auto px-2 py-1">
            <template x-for="cat in emojiVisible" :key="cat.key">
                <div>
                    <div class="sticky top-0 bg-white px-1 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500
                                dark:bg-zinc-900 dark:text-zinc-400"
                         x-text="cat.label"></div>
                    <div class="grid grid-cols-8 gap-0.5">
                        <template x-for="e in cat.emojis" :key="cat.key + e.c">
                            <button type="button"
                                    @mousedown.prevent @click="trackRecent(e.c); pickEmoji(e.c)"
                                    :title="e.k"
                                    class="size-8 rounded text-lg hover:bg-zinc-100
                                           dark:hover:bg-zinc-800"
                                    x-text="e.c"></button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="emojiSearch && emojiVisible.length === 0">
                <div class="px-2 py-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
                    No matches for "<span x-text="emojiSearch"></span>"
                </div>
            </template>
        </div>
    </div>

    <div x-show="showMentions"
         x-transition.opacity.duration.100ms
         @click.stop
         class="absolute left-0 z-20 w-72 overflow-hidden rounded-lg border border-zinc-200
                bg-white shadow-lg
                dark:border-zinc-700 dark:bg-zinc-900"
         style="display: none; bottom: calc(var(--bonfire-editor-height, 7rem) + 2.75rem);">
        <div class="border-b border-zinc-200 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-500
                    dark:border-zinc-800 dark:text-zinc-400">
            People
        </div>
        <ul class="max-h-60 overflow-y-auto py-1">
            <template x-for="(member, idx) in filteredMembers" :key="member.id">
                <li>
                    <button type="button"
                            @mousedown.prevent @click="selectMention(member.display_name)"
                            @mouseenter="mentionIndex = idx"
                            :class="idx === mentionIndex
                                ? (member.broadcast ? 'bg-amber-50 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'
                                                     : 'bg-sky-50 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100')
                                : ''"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm
                                   hover:bg-zinc-100
                                   dark:hover:bg-zinc-800">
                        <template x-if="member.broadcast">
                            <span class="flex size-6 flex-shrink-0 items-center justify-center rounded bg-amber-500/20 text-sm text-amber-600 dark:text-amber-300">📣</span>
                        </template>
                        <template x-if="! member.broadcast">
                            <img :src="member.avatar_url" alt="" class="size-6 flex-shrink-0 rounded bg-zinc-200 dark:bg-zinc-800">
                        </template>
                        <span class="flex min-w-0 flex-1 items-center gap-1">
                            <span :class="member.broadcast ? 'font-semibold' : ''"
                                  x-text="'@' + member.display_name" class="truncate"></span>
                            <template x-if="member.hint">
                                <span class="truncate text-[11px] text-zinc-500" x-text="member.hint"></span>
                            </template>
                        </span>
                    </button>
                </li>
            </template>
            <template x-if="filteredMembers.length === 0">
                <li class="px-3 py-2 text-xs text-zinc-500 dark:text-zinc-400">No matches</li>
            </template>
        </ul>
    </div>

    {{-- Poll creation modal --}}
    <flux:modal name="create-poll" class="md:w-[28rem]" @close="$wire.resetPollDraft()">
        <form wire:submit.prevent="createPoll" class="space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex size-9 flex-shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-600
                            dark:bg-sky-950/60 dark:text-sky-400">
                    <flux:icon name="chart-bar" class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ __('Create a poll') }}</flux:heading>
                    <flux:text class="mt-0.5 text-sm">
                        {{ __('Anything you typed in the message box (including @mentions) will be sent above the poll.') }}
                    </flux:text>
                </div>
            </div>

            <flux:field>
                <flux:label>{{ __('Question') }}</flux:label>
                <flux:input wire:model="pollQuestion"
                            maxlength="500"
                            :placeholder="__('What should we do?')" />
                <flux:error name="pollQuestion" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Options') }}</flux:label>

                <div class="space-y-2">
                    @foreach ($pollOptions as $index => $option)
                        <div wire:key="poll-option-{{ $index }}"
                             class="group flex items-center gap-2">
                            <span class="flex size-7 flex-shrink-0 items-center justify-center rounded-md
                                         border border-zinc-200 bg-zinc-50 text-xs font-semibold text-zinc-500 tabular-nums
                                         dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">
                                {{ $index + 1 }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <flux:input wire:model="pollOptions.{{ $index }}"
                                            maxlength="200"
                                            :placeholder="__('Option :n', ['n' => $index + 1])" />
                            </div>

                            @if (count($pollOptions) > 2)
                                <flux:button type="button"
                                             variant="subtle"
                                             size="sm"
                                             icon="trash"
                                             square
                                             :title="__('Remove option')"
                                             wire:click="removePollOption({{ $index }})"
                                             class="opacity-0 transition group-hover:opacity-100 focus:opacity-100" />
                            @else
                                <span class="size-8 flex-shrink-0"></span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <flux:error name="pollOptions" />

                @if (count($pollOptions) < 10)
                    <div class="pt-1">
                        <flux:button type="button"
                                     variant="ghost"
                                     size="sm"
                                     icon="plus"
                                     wire:click="addPollOption">
                            {{ __('Add option') }}
                        </flux:button>
                    </div>
                @endif
            </flux:field>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:text class="text-xs text-zinc-500">
                    {{ trans_choice('{0}No options yet|{1}1 option|[2,*]:count options', count(array_filter($pollOptions, fn ($o) => trim((string) $o) !== ''))) }}
                </flux:text>
                <div class="flex flex-wrap gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="button" variant="filled" icon="document-plus"
                                 wire:click="stagePoll">
                        {{ __('Add to message') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="paper-airplane">
                        {{ __('Send poll') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
