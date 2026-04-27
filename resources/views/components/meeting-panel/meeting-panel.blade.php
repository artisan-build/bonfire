@php
    $me = \ArtisanBuild\Bonfire\Facades\Bonfire::memberFor(auth()->user());
    $myName = $me?->display_name ?? auth()->user()?->name ?? 'You';
    $myAvatar = $me?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($myName);
    $myUserId = auth()->id();
@endphp

<div x-data="bonfireMeetingPanel()"
     data-my-user-id="{{ $myUserId }}"
     data-my-name="{{ $myName }}"
     data-my-avatar="{{ $myAvatar }}"
     x-on:bonfire-join-meeting.window="joinMeeting($event.detail)"
     class="fixed inset-0 z-[55]"
     :style="'pointer-events: ' + (active ? 'auto' : 'none')">

    <div x-show="active"
         x-transition.opacity.duration.150ms
         class="fixed inset-0 flex flex-col bg-zinc-900/95 backdrop-blur-sm"
         style="display: none;">

        <div class="flex flex-shrink-0 items-center justify-between border-b border-zinc-700/50 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-white">
                <flux:icon name="users" class="size-4 text-emerald-400" />
                <span class="font-medium" x-text="roomName || 'Meeting'"></span>
                <span class="text-xs text-zinc-400">
                    <span x-text="participantCount"></span>
                    <span x-text="participantCount === 1 ? 'participant' : 'participants'"></span>
                    <template x-if="startedAt">
                        <span> · <span class="font-mono text-emerald-300" x-text="durationLabel"></span></span>
                    </template>
                    <template x-if="! startedAt">
                        <span class="text-amber-300"> · Waiting for others…</span>
                    </template>
                </span>
            </div>
        </div>

        <div class="flex-1 overflow-hidden p-4">
            <div class="grid h-full gap-3"
                 :class="gridClass">
                {{-- Local tile --}}
                <div class="relative flex items-center justify-center overflow-hidden rounded-lg border border-zinc-700 bg-zinc-950 shadow-lg">
                    <video x-ref="localVideo"
                           autoplay
                           playsinline
                           muted
                           :class="sharing ? 'object-contain bg-black' : 'object-cover'"
                           class="h-full w-full"></video>

                    <div x-show="! camOn && ! sharing"
                         class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-zinc-900"
                         style="display: none;">
                        <img :src="me.avatar" alt="" class="size-20 rounded-full bg-zinc-700 object-cover">
                        <span class="text-sm font-semibold text-zinc-100" x-text="me.name"></span>
                    </div>

                    {{-- Pointer overlay + camera-drag handle live in an aspect-ratio-matched
                         box that corresponds to the actual video content area. --}}
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="relative h-full w-full"
                             :style="'aspect-ratio: ' + localVideoAspect + '; max-width: 100%; max-height: 100%;'">
                            {{-- Watcher pointer dots (passive) --}}
                            <template x-for="(p, uid) in remotePointers" :key="uid">
                                <div class="pointer-events-none absolute z-10 flex items-center gap-1"
                                     :style="'left: calc(' + (p.x * 100) + '% - 9px); top: calc(' + (p.y * 100) + '% - 9px);'">
                                    <span class="size-[18px] rounded-full ring-2 ring-white/90 shadow-lg"
                                          :style="'background: ' + p.color + '; box-shadow: 0 0 12px ' + p.color"></span>
                                    <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold text-white shadow"
                                          :style="'background: ' + p.color"
                                          x-text="p.name"></span>
                                </div>
                            </template>

                            {{-- Draggable camera handle, only while I'm sharing WITH camera --}}
                            <div x-show="sharing && includeCameraInShare && shareCamX !== null"
                                 @mousedown="startCamDrag($event)"
                                 :class="shareCamDragging ? 'ring-emerald-400' : 'ring-white/50'"
                                 :style="'left: calc(' + ((shareCamX ?? 0.8) * 100) + '%); top: calc(' + ((shareCamY ?? 0.8) * 100) + '%); width: ' + shareCamSize + '%; aspect-ratio: 16 / 9;'"
                                 class="absolute z-20 cursor-move rounded-lg ring-2 ring-dashed hover:ring-emerald-300"
                                 style="display: none;">
                                <div class="pointer-events-none absolute inset-0 rounded-lg bg-emerald-500/10"></div>
                                <div class="pointer-events-none absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center gap-1 rounded bg-black/70 px-2 py-0.5 text-[10px] font-semibold text-white">
                                    <svg class="size-3" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v4h-4zM3 10h4v4H3zm14 0h4v4h-4zM10 17h4v4h-4z"/></svg>
                                    Drag
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pointer-events-none absolute bottom-2 left-2 inline-flex items-center gap-1.5 rounded-md bg-black/60 px-2 py-0.5 text-xs text-white backdrop-blur-sm">
                        <span x-show="! sharing" x-text="me.name + ' (you)'"></span>
                        <span x-show="sharing" class="inline-flex items-center gap-1 text-emerald-300">
                            <flux:icon name="computer-desktop" class="size-3" />
                            Sharing screen
                        </span>
                        <span x-show="! micOn && ! sharing" class="text-rose-300">muted</span>
                    </div>

                    <div x-show="sharing"
                         class="absolute top-2 left-2 flex flex-col items-start gap-1 rounded-md bg-black/60 p-1.5 backdrop-blur-sm"
                         style="display: none;">
                        <div class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-300">
                            <flux:icon name="eye" class="size-3 text-emerald-400" />
                            <span>
                                <span x-text="watcherPeers.length"></span>
                                watching
                            </span>
                        </div>
                        <div x-show="watcherPeers.length > 0" class="flex -space-x-1.5" style="display: none;">
                            <template x-for="w in watcherPeers" :key="w.userId">
                                <img :src="w.avatar" :title="w.name" alt=""
                                     class="size-5 rounded-full border border-zinc-800 bg-zinc-700 object-cover">
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Remote tiles --}}
                <template x-for="peer in Object.values(peers)" :key="peer.userId">
                    <div class="relative flex items-center justify-center overflow-hidden rounded-lg border border-zinc-700 bg-zinc-950 shadow-lg">
                        <video :id="'meeting-video-' + peer.userId"
                               autoplay
                               playsinline
                               @mousemove="sendPointer(peer, $event)"
                               @mouseleave="sendPointer(peer, null)"
                               :class="peer.isSharing && peer.watchingScreen ? 'object-contain bg-black' : 'object-cover'"
                               class="h-full w-full"></video>

                        <div x-show="peer.isSharing && ! peer.watchingScreen"
                             class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-zinc-950/95 p-4"
                             style="display: none;">
                            <flux:icon name="computer-desktop" class="size-10 text-emerald-400" />
                            <span class="text-sm font-semibold text-zinc-100" x-text="peer.name + ' is sharing their screen'"></span>
                            <button type="button"
                                    @click="toggleWatchScreen(peer)"
                                    class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                Join the screen share
                            </button>
                        </div>

                        <div x-show="(! peer.hasVideo || peer.camOn === false) && ! peer.isSharing"
                             class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-zinc-900"
                             style="display: none;">
                            <img :src="peer.avatar" alt="" class="size-20 rounded-full bg-zinc-700 object-cover">
                            <span class="text-sm font-semibold text-zinc-100" x-text="peer.name"></span>
                            <span x-show="! peer.connected" class="text-xs text-amber-300">connecting…</span>
                        </div>

                        <button type="button"
                                x-show="peer.isSharing && peer.watchingScreen"
                                @click="toggleWatchScreen(peer)"
                                class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-md bg-emerald-600/90 px-2 py-1 text-[11px] font-semibold text-white shadow hover:bg-emerald-700"
                                title="Stop watching share"
                                style="display: none;">
                            <flux:icon name="computer-desktop" class="size-3" />
                            Watching
                        </button>

                        <div class="pointer-events-none absolute bottom-2 left-2 inline-flex items-center gap-1.5 rounded-md bg-black/60 px-2 py-0.5 text-xs text-white backdrop-blur-sm">
                            <span x-text="peer.name"></span>
                            <span x-show="peer.micOn === false" class="text-rose-300" title="Muted">
                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/>
                                </svg>
                            </span>
                        </div>
                        <div x-show="peer.camOn === false && peer.connected"
                             class="pointer-events-none absolute top-2 right-2 rounded-full bg-rose-600/90 p-1.5 text-white shadow"
                             title="Camera off"
                             style="display: none;">
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5 21 6.75v10.5L15.75 13.5m-12-3v6a2.25 2.25 0 0 0 2.25 2.25h7.5M3 3l18 18"/>
                            </svg>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex flex-shrink-0 items-center justify-center gap-3 border-t border-zinc-700/50 px-4 py-3">
            <button type="button"
                    @click="toggleMic()"
                    :title="micOn ? 'Mute' : 'Unmute'"
                    :class="micOn
                        ? 'bg-zinc-700 text-white ring-1 ring-zinc-500/40 hover:bg-zinc-600'
                        : 'bg-rose-600 text-white ring-4 ring-rose-400/70 shadow-lg shadow-rose-500/30 hover:bg-rose-700'"
                    class="inline-flex items-center justify-center rounded-full p-3 transition">
                <flux:icon variant="solid" name="microphone" class="size-5" x-show="micOn" />
                <svg x-show="! micOn" class="size-5" viewBox="0 0 24 24" fill="currentColor" style="display: none;">
                    <path d="M12 2a3 3 0 0 0-3 3v3.88l6 6V5a3 3 0 0 0-3-3Z"/>
                    <path d="M16.5 10.5a.75.75 0 0 1 1.5 0v2c0 .86-.18 1.68-.51 2.43l-1.15-1.16c.1-.4.16-.83.16-1.27v-2Z"/>
                    <path d="M13.9 18.45a7.5 7.5 0 0 1-7.9-7.45v-.75a.75.75 0 0 1 1.5 0v.75a6 6 0 0 0 8.61 5.42l-1.2-1.22a4.5 4.5 0 0 1-6.9-3.95V10.5 10h-.01L5.11 8.22A.75.75 0 0 0 6 8.5v2a4.5 4.5 0 0 0 4.5 4.5h.2l-.2 3.25H8.25a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H13.2l.7-1.75v1.95Z"/>
                    <path fill-rule="evenodd" d="M3.28 3.22a.75.75 0 0 0-1.06 1.06l18 18a.75.75 0 1 0 1.06-1.06l-18-18Z" clip-rule="evenodd"/>
                </svg>
            </button>
            <button type="button"
                    @click="toggleCam()"
                    :title="camOn ? 'Turn camera off' : 'Turn camera on'"
                    :class="camOn
                        ? 'bg-zinc-700 text-white ring-1 ring-zinc-500/40 hover:bg-zinc-600'
                        : 'bg-rose-600 text-white ring-4 ring-rose-400/70 shadow-lg shadow-rose-500/30 hover:bg-rose-700'"
                    class="inline-flex items-center justify-center rounded-full p-3 transition">
                <flux:icon variant="solid" name="video-camera" class="size-5" x-show="camOn" />
                <flux:icon variant="solid" name="video-camera-slash" class="size-5" x-show="! camOn" style="display: none;" />
            </button>
            <div class="relative">
                <button type="button"
                        @click="showQuality = ! showQuality"
                        title="Camera quality"
                        :class="showQuality
                            ? 'bg-zinc-600 text-white ring-1 ring-zinc-400'
                            : 'bg-zinc-700 text-white ring-1 ring-zinc-500/40 hover:bg-zinc-600'"
                        class="inline-flex items-center justify-center rounded-full p-3 transition">
                    <flux:icon variant="solid" name="cog-6-tooth" class="size-5" />
                </button>
                <div x-show="showQuality"
                     @click.outside="showQuality = false"
                     x-transition.opacity.duration.100ms
                     class="absolute bottom-full left-1/2 mb-2 w-80 -translate-x-1/2 rounded-xl border border-zinc-700 bg-zinc-900 p-4 text-sm text-zinc-100 shadow-xl"
                     style="display: none;">
                    {{-- Webcam quality section --}}
                    <div class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                        <flux:icon variant="solid" name="video-camera" class="size-3.5 text-sky-400" />
                        Webcam quality
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <template x-for="q in [240, 480, 720, 1080]" :key="q">
                            <button type="button"
                                    @click="applyCameraQuality(q)"
                                    :class="cameraQuality === q
                                        ? 'bg-sky-600 text-white ring-2 ring-sky-400'
                                        : 'bg-zinc-800 text-zinc-200 hover:bg-zinc-700'"
                                    class="rounded-md px-2 py-2 text-sm font-semibold transition">
                                <span x-text="q + 'p'"></span>
                            </button>
                        </template>
                    </div>
                    <p class="mt-1.5 text-[10px] text-zinc-500">
                        Sharper face ↔ more upload bandwidth.
                    </p>

                    {{-- Divider --}}
                    <div class="my-4 h-px bg-zinc-700"></div>

                    {{-- Camera PiP during share --}}
                    <div class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                        <flux:icon variant="solid" name="computer-desktop" class="size-3.5 text-emerald-400" />
                        Camera in screen share
                    </div>
                    <button type="button"
                            @click="includeCameraInShare = ! includeCameraInShare"
                            :class="includeCameraInShare
                                ? 'border-emerald-500/60 bg-emerald-500/10 text-emerald-100'
                                : 'border-zinc-700 bg-zinc-800/60 text-zinc-400'"
                            class="flex w-full items-center justify-between rounded-lg border p-3 transition hover:brightness-110">
                        <span class="flex items-center gap-2">
                            <flux:icon variant="solid" name="video-camera" class="size-4" />
                            <span class="text-sm font-medium">Show my face on the share</span>
                        </span>
                        <span :class="includeCameraInShare ? 'bg-emerald-500' : 'bg-zinc-700'"
                              class="relative inline-flex h-5 w-9 items-center rounded-full transition">
                            <span :class="includeCameraInShare ? 'translate-x-5' : 'translate-x-1'"
                                  class="inline-block size-3.5 rounded-full bg-white transition"></span>
                        </span>
                    </button>

                    <div x-show="includeCameraInShare" class="mt-3">
                        <label class="mb-1.5 flex items-center justify-between text-xs">
                            <span class="font-medium text-zinc-300">Camera size</span>
                            <span class="text-zinc-400"><span x-text="shareCamSize"></span>%</span>
                        </label>
                        <input type="range" min="8" max="60" step="1" x-model.number="shareCamSize"
                               class="h-1.5 w-full cursor-pointer accent-emerald-500">
                        <div class="flex justify-between text-[10px] text-zinc-500">
                            <span>tiny</span>
                            <span>huge</span>
                        </div>
                        <p x-show="sharing" class="mt-2 text-[10px] text-emerald-300" style="display: none;">
                            Drag the dashed box on your preview to reposition.
                        </p>
                    </div>
                </div>
            </div>
            <button type="button"
                    @click="togglePointer()"
                    :title="pointerEnabled ? 'Disable cursor sharing' : 'Enable cursor sharing'"
                    :class="pointerEnabled
                        ? 'bg-sky-500 text-white ring-4 ring-sky-300 shadow-lg shadow-sky-500/40 hover:bg-sky-600'
                        : 'bg-zinc-800 text-zinc-500 ring-1 ring-zinc-700 hover:bg-zinc-700'"
                    class="inline-flex items-center justify-center rounded-full p-3 transition">
                <flux:icon variant="solid" name="cursor-arrow-rays" class="size-5" x-show="pointerEnabled" />
                <flux:icon variant="solid" name="cursor-arrow-rays" class="size-5 opacity-60" x-show="! pointerEnabled" style="display: none;" />
            </button>

            <div class="relative">
                <button type="button"
                        @click="showShareOptions = ! showShareOptions"
                        :title="sharing ? 'Share settings' : 'Share screen'"
                        :class="sharing
                            ? 'bg-emerald-500 text-white ring-4 ring-emerald-300 shadow-lg shadow-emerald-500/50 hover:bg-emerald-600'
                            : 'bg-zinc-700 text-white ring-1 ring-zinc-500/40 hover:bg-zinc-600'"
                        class="relative inline-flex items-center justify-center rounded-full p-3 transition">
                    <flux:icon variant="solid" name="computer-desktop" class="size-5" />
                    <span x-show="sharing"
                          class="absolute -right-0.5 -top-0.5 flex size-2.5"
                          style="display: none;">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-emerald-300"></span>
                    </span>
                </button>
                <div x-show="showShareOptions"
                     @click.outside="showShareOptions = false"
                     x-transition.opacity.duration.100ms
                     class="absolute bottom-full left-1/2 mb-2 w-80 -translate-x-1/2 rounded-xl border border-zinc-700 bg-zinc-900 p-4 text-sm text-zinc-100 shadow-xl"
                     style="display: none;">
                    <div class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider">
                        <flux:icon variant="solid" name="computer-desktop" class="size-3.5 text-emerald-400" />
                        <span x-show="! sharing" class="text-zinc-400">Screen share</span>
                        <span x-show="sharing" class="text-emerald-300">Live screen share</span>
                    </div>
                    <p class="mb-2 text-[10px] text-zinc-500">
                        Affects the screen/window you're sharing. Your webcam quality is set separately (gear button).
                    </p>
                    <div class="space-y-3">
                        {{-- Quality + FPS are only editable before sharing starts. --}}
                        <div x-show="! sharing">
                            <label class="mb-1 block text-[10px] font-medium text-zinc-400">Screen resolution</label>
                            <select x-model="shareQuality"
                                    class="w-full rounded border border-zinc-700 bg-zinc-800 px-2 py-1 text-xs">
                                <option value="720">720p (HD)</option>
                                <option value="1080">1080p (Full HD)</option>
                                <option value="1440">1440p (QHD)</option>
                                <option value="2160">2160p (4K)</option>
                            </select>
                        </div>
                        <div x-show="! sharing">
                            <label class="mb-1 block text-[10px] font-medium text-zinc-400">Frame rate</label>
                            <select x-model="shareFps"
                                    class="w-full rounded border border-zinc-700 bg-zinc-800 px-2 py-1 text-xs">
                                <option value="15">15 fps</option>
                                <option value="30">30 fps (standard)</option>
                                <option value="60">60 fps (smooth)</option>
                            </select>
                        </div>
                        <div class="rounded-md border border-zinc-700 bg-zinc-800/40 p-2 text-[10px] text-zinc-400">
                            <flux:icon name="video-camera" class="inline size-3 text-sky-400" />
                            Camera overlay &amp; size live in the
                            <strong class="text-zinc-200">gear button</strong> settings.
                        </div>
                        <p x-show="! sharing" class="text-[10px] text-zinc-500 leading-relaxed">
                            Your browser will ask you to pick which screen, window, or tab to share.
                        </p>
                        <button x-show="! sharing"
                                type="button"
                                @click="startScreenShare()"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            <flux:icon variant="solid" name="computer-desktop" class="size-4" />
                            Start sharing
                        </button>
                        <button x-show="sharing"
                                type="button"
                                @click="stopScreenShare(); showShareOptions = false"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-rose-600 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">
                            <flux:icon variant="solid" name="phone-x-mark" class="size-4" />
                            Stop sharing
                        </button>
                    </div>
                </div>
            </div>

            <div class="relative">
                <button type="button"
                        @click="showInvite = ! showInvite; if (showInvite) loadCandidates()"
                        title="Invite people"
                        class="inline-flex items-center justify-center rounded-full bg-sky-600 p-3 text-white ring-1 ring-sky-400/40 transition hover:bg-sky-500">
                    <flux:icon variant="solid" name="user-plus" class="size-5" />
                </button>
                <div x-show="showInvite"
                     @click.outside="showInvite = false"
                     x-transition.opacity.duration.100ms
                     class="absolute bottom-full left-1/2 mb-2 w-72 -translate-x-1/2 overflow-hidden rounded-lg border border-zinc-700 bg-zinc-900 text-xs text-zinc-100 shadow-xl"
                     style="display: none;">
                    <div class="border-b border-zinc-700 px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                        Invite to meeting
                    </div>
                    <ul class="max-h-72 overflow-y-auto py-1">
                        <template x-for="c in inviteCandidates" :key="c.id">
                            <li>
                                <button type="button"
                                        @click="invite(c)"
                                        :disabled="isCandidateBlocked(c)"
                                        :class="isCandidateBlocked(c) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer text-zinc-100 hover:bg-zinc-800'"
                                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm">
                                    <img :src="c.avatar_url" alt="" class="size-6 flex-shrink-0 rounded bg-zinc-700 object-cover">
                                    <span x-text="c.display_name" class="flex-1 truncate"></span>
                                    <span x-show="!! peers[c.user_id]" class="text-[10px] text-emerald-400">in call</span>
                                    <span x-show="invitedIds.includes(c.id) && ! peers[c.user_id]" class="text-[10px] text-zinc-400">invited</span>
                                </button>
                            </li>
                        </template>
                        <template x-if="inviteCandidates.length === 0">
                            <li class="px-3 py-3 text-center text-xs text-zinc-500">No one else to invite.</li>
                        </template>
                    </ul>
                </div>
            </div>
            <button type="button"
                    @click="leave()"
                    title="Leave meeting"
                    class="inline-flex items-center justify-center rounded-full bg-rose-600 p-3 text-white ring-2 ring-rose-400/60 shadow-lg shadow-rose-500/30 transition hover:bg-rose-700">
                <flux:icon variant="solid" name="phone-x-mark" class="size-5" />
            </button>
        </div>
    </div>
</div>

@once
    <script>
        window.bonfireMeetingPanel = function () {
            return {
                active: false,
                joining: false,
                me: { userId: null, name: 'You', avatar: null },
                roomId: null,
                roomName: '',
                localStream: null,
                peers: {}, // { userId: { userId, name, avatar, pc, hasVideo, connected, stream } }
                channel: null,
                pendingSignals: {}, // userId → array of buffered { kind: 'sdp'|'ice', data }
                micOn: true,
                camOn: true,
                showQuality: false,
                cameraQuality: 720, // 240 | 480 | 720 | 1080
                startedAt: null,
                durationTimer: null,
                durationLabel: '00:00',
                showInvite: false,
                inviteCandidates: [],
                invitedIds: [],
                announcedStart: false,
                seenParticipants: new Set(),
                sharing: false,
                screenStream: null,
                cameraTrack: null,
                currentVideoTrack: null, // the track currently being sent (camera, raw screen, or composite)
                showShareOptions: false,
                shareQuality: 1080,
                shareFps: 30,
                includeCameraInShare: true,
                shareCamSize: 20, // % of canvas width
                shareCamX: null,  // 0–1 percentage of canvas width (top-left of PiP)
                shareCamY: null,  // 0–1 percentage of canvas height
                shareCamDragging: false,
                shareCanvas: null,
                shareCanvasStream: null,
                shareRafId: null,
                watcherIds: [],
                remotePointers: {}, // when I'm sharing, map of viewerUserId → { x, y, name, color }
                pendingPointers: {},
                pointerFlushScheduled: false,
                pointerSendAt: 0,
                pointerEnabled: (localStorage.getItem('bonfire.meeting.pointer') ?? '1') === '1',
                localVideoAspect: '16 / 9',
                pointerPalette: ['#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4', '#ef4444'],
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' },
                ],
                init() {
                    this.me.userId = Number(this.$el.dataset.myUserId);
                    this.me.name = this.$el.dataset.myName || 'You';
                    this.me.avatar = this.$el.dataset.myAvatar || null;

                    // Delegated listener for "Join meeting" buttons inside message bodies.
                    document.addEventListener('click', (e) => {
                        const btn = e.target.closest?.('[data-bonfire-join-meeting]');
                        if (! btn) return;
                        e.preventDefault();
                        const roomId = Number(btn.dataset.roomId);
                        const roomName = btn.dataset.roomName || '';
                        if (roomId) this.joinMeeting({ roomId, roomName });
                    });

                    // Pause the duration ticker when the tab is hidden — no point
                    // burning cycles updating invisible UI.
                    document.addEventListener('visibilitychange', () => {
                        if (! this.active) return;
                        if (document.hidden) {
                            if (this.durationTimer) { clearInterval(this.durationTimer); this.durationTimer = null; }
                        } else if (this.startedAt) {
                            this.startDurationTimer();
                        }
                    });
                },
                get participantCount() {
                    return 1 + Object.keys(this.peers).length;
                },
                get gridClass() {
                    const n = this.participantCount;
                    if (n <= 1) return 'grid-cols-1';
                    if (n === 2) return 'grid-cols-2 grid-rows-1';
                    if (n <= 4) return 'grid-cols-2 grid-rows-2';
                    if (n <= 6) return 'grid-cols-3 grid-rows-2';
                    return 'grid-cols-3 grid-rows-3';
                },
                async joinMeeting(detail) {
                    if (this.active || this.joining) return;
                    this.joining = true;
                    // Give any in-flight Echo.leave() from a previous session a chance to
                    // finish so we don't race with its unsubscribe.
                    await new Promise(r => setTimeout(r, 50));
                    this.roomId = detail.roomId;
                    this.roomName = '#' + (detail.roomName || 'channel');
                    this.active = true;

                    try {
                        this.localStream = await navigator.mediaDevices.getUserMedia({
                            audio: {
                                echoCancellation: true,
                                noiseSuppression: true,
                                autoGainControl: true,
                            },
                            video: {
                                width: { ideal: 1280 },
                                height: { ideal: 720 },
                                frameRate: { ideal: 30, max: 30 },
                            },
                        });
                        // Hint to the encoder that this is a talking-head feed.
                        this.localStream.getVideoTracks().forEach(t => {
                            if ('contentHint' in t) t.contentHint = 'motion';
                        });
                        this.$nextTick(() => {
                            if (this.$refs.localVideo) {
                                this.$refs.localVideo.srcObject = this.localStream;
                                this.$refs.localVideo.addEventListener('loadedmetadata', () => this.updateLocalAspect());
                                this.$refs.localVideo.addEventListener('resize', () => this.updateLocalAspect());
                                this.updateLocalAspect();
                            }
                        });
                    } catch (err) {
                        console.error('getUserMedia failed', err);
                        this.joining = false;
                        this.leave();
                        return;
                    }

                    this.subscribe();
                    this.joining = false;
                },
                subscribe() {
                    if (typeof window.Echo === 'undefined') {
                        console.error('Echo not ready');
                        return;
                    }
                    const channelName = 'bonfire.meeting.' + this.roomId;
                    this.channel = window.Echo.join(channelName);
                    this.channel
                        .here((members) => {
                            // Existing members: initiate offer if my userId is LOWER (rule to avoid glare)
                            const others = members.filter(m => m.user_id !== this.me.userId);
                            others.forEach((m) => {
                                this.seenParticipants.add(m.user_id);
                                this.addPeer(m, this.me.userId < m.user_id);
                            });
                            this.seenParticipants.add(this.me.userId);
                            // Fetch the meeting start time from the server so every
                            // client shares the same timer, whether they started it
                            // or joined later / rejoined.
                            if (others.length === 0 && ! this.announcedStart) {
                                this.announcedStart = true;
                                this.$wire.announceStart(this.roomId).then(ts => {
                                    if (ts) this.syncStartedAt(ts);
                                });
                            } else {
                                this.$wire.getMeetingStartedAt(this.roomId).then(ts => {
                                    if (ts) this.syncStartedAt(ts);
                                });
                            }
                        })
                        .joining((m) => {
                            if (m.user_id === this.me.userId) return;
                            this.seenParticipants.add(m.user_id);
                            this.addPeer(m, this.me.userId < m.user_id);
                        })
                        .leaving((m) => {
                            this.removePeer(m.user_id);
                        })
                        .listenForWhisper('sdp', (data) => {
                            if (data.to !== this.me.userId) return;
                            this.onRemoteSdp(data);
                        })
                        .listenForWhisper('ice', (data) => {
                            if (data.to !== this.me.userId) return;
                            this.onRemoteIce(data);
                        })
                        .listenForWhisper('state', (data) => {
                            if (! data || data.userId === this.me.userId) return;
                            const p = this.peers[data.userId];
                            if (! p) return;
                            p.micOn = !! data.micOn;
                            p.camOn = !! data.camOn;
                            const wasSharing = p.isSharing;
                            p.isSharing = !! data.isSharing;
                            if (wasSharing && ! p.isSharing) {
                                p.watchingScreen = false;
                            }
                            this.peers = { ...this.peers }; // nudge reactivity
                        })
                        .listenForWhisper('view-toggle', (data) => {
                            if (! data || data.sharerUserId !== this.me.userId) return;
                            // Only the sharer cares about this.
                            if (data.watching) {
                                if (! this.watcherIds.includes(data.viewerUserId)) {
                                    this.watcherIds = [...this.watcherIds, data.viewerUserId];
                                }
                            } else {
                                this.watcherIds = this.watcherIds.filter(id => id !== data.viewerUserId);
                                // Clear their pointer if they had one.
                                if (this.remotePointers[data.viewerUserId]) {
                                    const next = { ...this.remotePointers };
                                    delete next[data.viewerUserId];
                                    this.remotePointers = next;
                                }
                            }
                        })
                        .listenForWhisper('pointer', (data) => {
                            if (! data || data.sharerUserId !== this.me.userId) return;
                            if (! this.pointerEnabled) return; // locally disabled → ignore
                            // Queue the update — apply at most once per animation frame.
                            if (data.active) {
                                this.pendingPointers[data.viewerUserId] = {
                                    x: Number(data.x) || 0,
                                    y: Number(data.y) || 0,
                                    name: data.viewerName || 'Viewer',
                                    color: this.colorForUserId(data.viewerUserId),
                                };
                            } else {
                                this.pendingPointers[data.viewerUserId] = null;
                            }
                            if (! this.pointerFlushScheduled) {
                                this.pointerFlushScheduled = true;
                                requestAnimationFrame(() => this.flushPointerUpdates());
                            }
                        });
                },
                async addPeer(memberInfo, shouldOffer) {
                    const uid = memberInfo.user_id;
                    if (this.peers[uid]) return;

                    const pc = new RTCPeerConnection({
                        iceServers: this.iceServers,
                        bundlePolicy: 'max-bundle',
                        rtcpMuxPolicy: 'require',
                    });
                    const peer = {
                        userId: uid,
                        name: memberInfo.display_name || 'User',
                        avatar: memberInfo.avatar_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(memberInfo.display_name || 'User')),
                        pc,
                        stream: new MediaStream(),
                        hasVideo: false,
                        connected: false,
                        micOn: true,
                        camOn: true,
                        isSharing: false,
                        watchingScreen: false,
                        pendingIce: [],
                    };
                    this.peers = { ...this.peers, [uid]: peer };

                    // Let the peer know our current mic/cam state once they've had a moment to subscribe.
                    setTimeout(() => this.broadcastState(), 400);

                    // Attach our local tracks. For new peers who join while I'm already
                    // sharing, we swap in the composite/screen track below.
                    this.localStream.getTracks().forEach(t => {
                        const sender = pc.addTrack(t, this.localStream);
                        if (t.kind === 'video') {
                            this.applyBitrateCap(sender, 1_200_000);
                        } else if (t.kind === 'audio') {
                            this.applyBitrateCap(sender, 48_000);
                        }
                    });

                    // If a share is already active when this peer joins, immediately swap
                    // their video track to the outgoing share track.
                    if (this.sharing && this.currentVideoTrack) {
                        const videoSender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                        if (videoSender) {
                            videoSender.replaceTrack(this.currentVideoTrack).catch(e => console.error(e));
                            this.applyBitrateCap(videoSender, 2_500_000);
                        }
                    }

                    pc.addEventListener('track', (ev) => {
                        if (ev.track && ! peer.stream.getTracks().includes(ev.track)) {
                            peer.stream.addTrack(ev.track);
                        }
                        const hasVid = peer.stream.getVideoTracks().some(tr => tr.readyState === 'live');
                        if (this.peers[uid]) {
                            this.peers[uid].hasVideo = hasVid;
                            this.peers = { ...this.peers };
                        }
                        this.$nextTick(() => {
                            const el = document.getElementById('meeting-video-' + uid);
                            if (el && el.srcObject !== peer.stream) el.srcObject = peer.stream;
                        });
                    });

                    pc.addEventListener('icecandidate', (ev) => {
                        if (ev.candidate && this.channel) {
                            this.channel.whisper('ice', {
                                to: uid,
                                from: this.me.userId,
                                candidate: ev.candidate.toJSON(),
                            });
                        }
                    });

                    pc.addEventListener('connectionstatechange', () => {
                        const connected = pc.connectionState === 'connected';
                        if (this.peers[uid]) {
                            this.peers[uid].connected = connected;
                            this.peers = { ...this.peers }; // nudge reactivity
                        }
                        if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
                            this.removePeer(uid);
                        }
                    });

                    if (shouldOffer) {
                        try {
                            const offer = await pc.createOffer();
                            await pc.setLocalDescription(offer);
                            this.channel.whisper('sdp', {
                                to: uid,
                                from: this.me.userId,
                                kind: 'offer',
                                sdp: offer,
                            });
                        } catch (err) { console.error('offer failed', err); }
                    }

                    // Replay any signals that arrived before this peer existed.
                    const buffered = this.pendingSignals[uid];
                    if (buffered) {
                        delete this.pendingSignals[uid];
                        for (const entry of buffered) {
                            if (entry.kind === 'sdp') await this.onRemoteSdp(entry.data);
                            else if (entry.kind === 'ice') await this.onRemoteIce(entry.data);
                        }
                    }
                },
                async onRemoteSdp(data) {
                    const peer = this.peers[data.from];
                    if (! peer) {
                        // Buffer — receiver may not have created its local peer yet.
                        if (! this.pendingSignals[data.from]) this.pendingSignals[data.from] = [];
                        this.pendingSignals[data.from].push({ kind: 'sdp', data });
                        return;
                    }
                    try {
                        if (data.kind === 'offer') {
                            await peer.pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
                            const answer = await peer.pc.createAnswer();
                            await peer.pc.setLocalDescription(answer);
                            this.channel.whisper('sdp', {
                                to: data.from,
                                from: this.me.userId,
                                kind: 'answer',
                                sdp: answer,
                            });
                            this.drainPendingIce(peer);
                        } else if (data.kind === 'answer') {
                            await peer.pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
                            this.drainPendingIce(peer);
                        }
                    } catch (err) { console.error('onRemoteSdp error', err); }
                },
                async onRemoteIce(data) {
                    const peer = this.peers[data.from];
                    if (! peer) {
                        if (! this.pendingSignals[data.from]) this.pendingSignals[data.from] = [];
                        this.pendingSignals[data.from].push({ kind: 'ice', data });
                        return;
                    }
                    try {
                        if (peer.pc.remoteDescription) {
                            await peer.pc.addIceCandidate(new RTCIceCandidate(data.candidate));
                        } else {
                            peer.pendingIce.push(data.candidate);
                        }
                    } catch (err) { console.error('addIceCandidate', err); }
                },
                async drainPendingIce(peer) {
                    while (peer.pendingIce.length) {
                        const c = peer.pendingIce.shift();
                        try { await peer.pc.addIceCandidate(new RTCIceCandidate(c)); } catch (e) {}
                    }
                },
                removePeer(uid) {
                    const peer = this.peers[uid];
                    if (! peer) return;
                    try { peer.pc.close(); } catch (e) {}
                    if (peer.stream) peer.stream.getTracks().forEach(t => t.stop());
                    const next = { ...this.peers };
                    delete next[uid];
                    this.peers = next;
                    // Clean up pointer + watcher state for this user.
                    this.watcherIds = this.watcherIds.filter(id => id !== Number(uid));
                    if (this.remotePointers[uid]) {
                        const np = { ...this.remotePointers };
                        delete np[uid];
                        this.remotePointers = np;
                    }
                },
                async loadCandidates() {
                    try {
                        this.inviteCandidates = await this.$wire.candidatesFor(this.roomId);
                    } catch (err) { console.error('loadCandidates', err); }
                },
                isCandidateBlocked(c) {
                    if (this.invitedIds.includes(c.id)) return true;
                    if (c.user_id && this.peers[c.user_id]) return true;
                    return false;
                },
                async invite(candidate) {
                    if (this.isCandidateBlocked(candidate)) return;
                    try {
                        await this.$wire.invite(this.roomId, candidate.id);
                        this.invitedIds = [...this.invitedIds, candidate.id];
                    } catch (err) { console.error('invite', err); }
                },
                toggleMic() {
                    if (! this.localStream) return;
                    this.micOn = ! this.micOn;
                    this.localStream.getAudioTracks().forEach(t => t.enabled = this.micOn);
                    this.broadcastState();
                },
                toggleCam() {
                    if (! this.localStream) return;
                    this.camOn = ! this.camOn;
                    this.localStream.getVideoTracks().forEach(t => t.enabled = this.camOn);
                    this.broadcastState();
                },
                togglePointer() {
                    this.pointerEnabled = ! this.pointerEnabled;
                    localStorage.setItem('bonfire.meeting.pointer', this.pointerEnabled ? '1' : '0');
                    if (! this.pointerEnabled) {
                        // Clear any live pointers so they disappear immediately.
                        this.remotePointers = {};
                        this.pendingPointers = {};
                        // Tell the sharer I've stopped (if they still have me cached).
                        Object.values(this.peers).forEach(peer => {
                            if (peer.isSharing && this.channel) {
                                try {
                                    this.channel.whisper('pointer', {
                                        sharerUserId: peer.userId,
                                        viewerUserId: this.me.userId,
                                        active: false,
                                    });
                                } catch (e) {}
                            }
                        });
                    }
                },
                async applyCameraQuality(heightValue) {
                    this.cameraQuality = Number(heightValue);
                    const map = {
                        240: { width: 426, height: 240, bitrate: 250_000 },
                        480: { width: 854, height: 480, bitrate: 600_000 },
                        720: { width: 1280, height: 720, bitrate: 1_200_000 },
                        1080: { width: 1920, height: 1080, bitrate: 2_000_000 },
                    };
                    const target = map[this.cameraQuality] ?? map[720];

                    // Apply constraints to the live camera track (downscales without re-prompting).
                    const track = (this.cameraTrack && ! this.sharing)
                        ? this.cameraTrack
                        : this.localStream?.getVideoTracks()[0];
                    if (track) {
                        try {
                            await track.applyConstraints({
                                width: { ideal: target.width },
                                height: { ideal: target.height },
                                frameRate: { ideal: 30, max: 30 },
                            });
                        } catch (e) { console.error('applyConstraints failed', e); }
                    }

                    // Re-cap bitrate on every outbound video sender.
                    Object.values(this.peers).forEach(peer => {
                        const sender = peer.pc?.getSenders().find(s => s.track && s.track.kind === 'video');
                        if (sender) this.applyBitrateCap(sender, target.bitrate);
                    });
                },
                startCamDrag(event) {
                    if (! this.sharing || ! this.includeCameraInShare) return;
                    event.preventDefault();
                    this.shareCamDragging = true;
                    const container = event.currentTarget.parentElement; // the aspect-ratio box
                    const rect = container.getBoundingClientRect();
                    const startX = event.clientX;
                    const startY = event.clientY;
                    const startCamX = this.shareCamX ?? 0.8;
                    const startCamY = this.shareCamY ?? 0.8;

                    const onMove = (e) => {
                        if (! this.shareCamDragging) return;
                        const dx = (e.clientX - startX) / rect.width;
                        const dy = (e.clientY - startY) / rect.height;
                        const sizeFrac = Number(this.shareCamSize) / 100;
                        const maxX = 1 - sizeFrac;
                        const maxY = 1 - sizeFrac * (9 / 16);
                        let nx = Math.max(0, Math.min(maxX, startCamX + dx));
                        let ny = Math.max(0, Math.min(maxY, startCamY + dy));
                        // Snap within 2% of any edge so users can easily pin to corners.
                        const snap = 0.02;
                        if (nx < snap) nx = 0;
                        if (ny < snap) ny = 0;
                        if (maxX - nx < snap) nx = maxX;
                        if (maxY - ny < snap) ny = maxY;
                        this.shareCamX = nx;
                        this.shareCamY = ny;
                    };
                    const onUp = () => {
                        this.shareCamDragging = false;
                        window.removeEventListener('mousemove', onMove);
                        window.removeEventListener('mouseup', onUp);
                    };
                    window.addEventListener('mousemove', onMove);
                    window.addEventListener('mouseup', onUp);
                },
                updateLocalAspect() {
                    const v = this.$refs.localVideo;
                    if (! v || ! v.videoWidth || ! v.videoHeight) return;
                    this.localVideoAspect = v.videoWidth + ' / ' + v.videoHeight;
                },
                applyBitrateCap(sender, bps) {
                    try {
                        const params = sender.getParameters();
                        if (! params.encodings) params.encodings = [{}];
                        params.encodings.forEach(e => {
                            e.maxBitrate = bps;
                        });
                        sender.setParameters(params).catch(() => {});
                    } catch (e) { /* best effort */ }
                },
                async startScreenShare() {
                    if (this.sharing) return;
                    this.showShareOptions = false;
                    const heightMap = { 720: 720, 1080: 1080, 1440: 1440, 2160: 2160 };
                    const targetHeight = heightMap[this.shareQuality] ?? 1080;
                    const targetWidth = Math.round(targetHeight * 16 / 9);
                    try {
                        const stream = await navigator.mediaDevices.getDisplayMedia({
                            video: {
                                frameRate: { ideal: Number(this.shareFps), max: Number(this.shareFps) },
                                width: { ideal: targetWidth },
                                height: { ideal: targetHeight },
                            },
                            audio: false,
                        });
                        this.screenStream = stream;
                        const rawScreenTrack = stream.getVideoTracks()[0];
                        if ('contentHint' in rawScreenTrack) rawScreenTrack.contentHint = 'detail';
                        this.cameraTrack = this.localStream?.getVideoTracks()[0] || null;

                        // Decide which track we'll actually send: raw screen, or composited
                        // screen + camera via canvas.
                        const outgoingTrack = this.includeCameraInShare && this.cameraTrack
                            ? await this.buildCompositeTrack(rawScreenTrack, this.cameraTrack, Number(this.shareFps))
                            : rawScreenTrack;

                        this.currentVideoTrack = outgoingTrack;

                        Object.values(this.peers).forEach(peer => {
                            const sender = peer.pc?.getSenders().find(s => s.track && s.track.kind === 'video');
                            if (sender) {
                                sender.replaceTrack(outgoingTrack).catch(e => console.error(e));
                                this.applyBitrateCap(sender, 2_500_000);
                            }
                        });

                        const preview = new MediaStream([outgoingTrack, ...(this.localStream?.getAudioTracks() || [])]);
                        if (this.$refs.localVideo) this.$refs.localVideo.srcObject = preview;
                        this.$nextTick(() => this.updateLocalAspect());

                        this.sharing = true;
                        this.broadcastState();
                        rawScreenTrack.addEventListener('ended', () => this.stopScreenShare());
                    } catch (err) {
                        console.error('startScreenShare failed', err);
                    }
                },
                async buildCompositeTrack(screenTrack, camTrack, fps) {
                    const whenReady = (vid) => new Promise((resolve) => {
                        const done = () => resolve();
                        if (vid.readyState >= 2 && vid.videoWidth > 0) return done();
                        vid.addEventListener('loadeddata', done, { once: true });
                        // Belt-and-suspenders: also resolve on timeout so we don't hang.
                        setTimeout(done, 2000);
                    });

                    const screenVid = document.createElement('video');
                    screenVid.muted = true;
                    screenVid.autoplay = true;
                    screenVid.playsInline = true;
                    screenVid.srcObject = new MediaStream([screenTrack]);

                    const camVid = document.createElement('video');
                    camVid.muted = true;
                    camVid.autoplay = true;
                    camVid.playsInline = true;
                    camVid.srcObject = new MediaStream([camTrack]);

                    // Wait for both video elements to have their first decoded frame.
                    try { await screenVid.play(); } catch (e) {}
                    try { await camVid.play(); } catch (e) {}
                    await Promise.all([whenReady(screenVid), whenReady(camVid)]);

                    // Size the canvas to the actual loaded screen dimensions.
                    const canvas = document.createElement('canvas');
                    canvas.width = screenVid.videoWidth || screenTrack.getSettings().width || 1920;
                    canvas.height = screenVid.videoHeight || screenTrack.getSettings().height || 1080;
                    this.shareCanvas = canvas;
                    const ctx = canvas.getContext('2d', { desynchronized: true });

                    const drawOnce = () => {
                        try {
                            ctx.drawImage(screenVid, 0, 0, canvas.width, canvas.height);
                            if (this.includeCameraInShare && camVid.readyState >= 2) {
                                const pipW = Math.round(canvas.width * (Number(this.shareCamSize) / 100));
                                const camRatio = (camVid.videoWidth || 16) / (camVid.videoHeight || 9);
                                const pipH = Math.round(pipW / camRatio);
                                const pad = Math.round(canvas.width * 0.015);

                                // If no position set yet, default to bottom-right corner.
                                if (this.shareCamX === null || this.shareCamY === null) {
                                    this.shareCamX = (canvas.width - pipW - pad) / canvas.width;
                                    this.shareCamY = (canvas.height - pipH - pad) / canvas.height;
                                }

                                // Clamp position so PiP stays on canvas.
                                const maxX = (canvas.width - pipW) / canvas.width;
                                const maxY = (canvas.height - pipH) / canvas.height;
                                const fx = Math.max(0, Math.min(maxX, this.shareCamX));
                                const fy = Math.max(0, Math.min(maxY, this.shareCamY));

                                const pipX = Math.round(fx * canvas.width);
                                const pipY = Math.round(fy * canvas.height);

                                ctx.save();
                                ctx.shadowColor = 'rgba(0,0,0,0.5)';
                                ctx.shadowBlur = 16;
                                this.roundedRect(ctx, pipX, pipY, pipW, pipH, 12);
                                ctx.clip();
                                ctx.drawImage(camVid, pipX, pipY, pipW, pipH);
                                ctx.restore();
                                ctx.strokeStyle = 'rgba(255,255,255,0.35)';
                                ctx.lineWidth = 2;
                                this.roundedRect(ctx, pipX, pipY, pipW, pipH, 12);
                                ctx.stroke();
                            }
                        } catch (e) { /* not ready */ }
                    };

                    // Paint a primer frame so captureStream has real content from the very first frame.
                    drawOnce();

                    const out = canvas.captureStream(fps);
                    this.shareCanvasStream = out;
                    const track = out.getVideoTracks()[0];
                    if ('contentHint' in track) track.contentHint = 'detail';

                    // Now start the continuous draw loop.
                    const loop = () => {
                        if (! this.sharing) return;
                        drawOnce();
                        this.shareRafId = requestAnimationFrame(loop);
                    };
                    this.shareRafId = requestAnimationFrame(loop);

                    return track;
                },
                roundedRect(ctx, x, y, w, h, r) {
                    ctx.beginPath();
                    ctx.moveTo(x + r, y);
                    ctx.lineTo(x + w - r, y);
                    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
                    ctx.lineTo(x + w, y + h - r);
                    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
                    ctx.lineTo(x + r, y + h);
                    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
                    ctx.lineTo(x, y + r);
                    ctx.quadraticCurveTo(x, y, x + r, y);
                    ctx.closePath();
                },
                async stopScreenShare() {
                    if (! this.sharing) return;
                    try {
                        Object.values(this.peers).forEach(peer => {
                            const sender = peer.pc?.getSenders().find(s => s.track && s.track.kind === 'video');
                            if (sender && this.cameraTrack) {
                                sender.replaceTrack(this.cameraTrack).catch(e => console.error(e));
                            }
                        });
                        this.currentVideoTrack = this.cameraTrack || null;
                        if (this.$refs.localVideo && this.localStream) {
                            this.$refs.localVideo.srcObject = this.localStream;
                            this.$nextTick(() => this.updateLocalAspect());
                        }
                        // Re-apply the user's chosen camera quality (resolution + bitrate cap)
                        // now that the camera track is the outgoing one again.
                        await this.applyCameraQuality(this.cameraQuality);
                    } catch (e) { console.error('restore camera failed', e); }
                    if (this.shareRafId) { cancelAnimationFrame(this.shareRafId); this.shareRafId = null; }
                    if (this.shareCanvasStream) {
                        this.shareCanvasStream.getTracks().forEach(t => t.stop());
                        this.shareCanvasStream = null;
                    }
                    this.shareCanvas = null;
                    this.shareCamX = null;
                    this.shareCamY = null;
                    this.shareCamDragging = false;
                    if (this.screenStream) {
                        this.screenStream.getTracks().forEach(t => t.stop());
                        this.screenStream = null;
                    }
                    this.cameraTrack = null;
                    this.currentVideoTrack = null;
                    this.sharing = false;
                    this.watcherIds = [];
                    this.remotePointers = {};
                    this.broadcastState();
                },
                get watcherPeers() {
                    return this.watcherIds
                        .map(id => this.peers[id])
                        .filter(p => !! p);
                },
                colorForUserId(uid) {
                    const n = Math.abs(Number(uid) || 0) % this.pointerPalette.length;
                    return this.pointerPalette[n];
                },
                flushPointerUpdates() {
                    this.pointerFlushScheduled = false;
                    const next = { ...this.remotePointers };
                    let changed = false;
                    for (const [uid, val] of Object.entries(this.pendingPointers)) {
                        if (val === null) {
                            if (next[uid] !== undefined) { delete next[uid]; changed = true; }
                        } else {
                            next[uid] = val;
                            changed = true;
                        }
                    }
                    this.pendingPointers = {};
                    if (changed) this.remotePointers = next;
                },
                sendPointer(peer, event) {
                    if (! this.pointerEnabled) return;
                    if (! this.channel || ! peer || ! peer.isSharing || ! peer.watchingScreen) return;
                    const now = performance.now();
                    if (event && now - this.pointerSendAt < 80) return;
                    this.pointerSendAt = now;
                    if (! event) {
                        try {
                            this.channel.whisper('pointer', {
                                sharerUserId: peer.userId,
                                viewerUserId: this.me.userId,
                                viewerName: this.me.name,
                                active: false,
                            });
                        } catch (e) {}
                        return;
                    }
                    const videoEl = event.currentTarget;
                    const vw = videoEl.videoWidth;
                    const vh = videoEl.videoHeight;
                    const rect = videoEl.getBoundingClientRect();
                    if (rect.width === 0 || rect.height === 0 || ! vw || ! vh) return;

                    // Figure out how object-contain positions the content inside the element
                    // so the pointer maps to the actual video pixels, not the letterbox area.
                    const elRatio = rect.width / rect.height;
                    const vidRatio = vw / vh;
                    let contentW, contentH, offX, offY;
                    if (vidRatio > elRatio) {
                        contentW = rect.width;
                        contentH = rect.width / vidRatio;
                        offX = 0;
                        offY = (rect.height - contentH) / 2;
                    } else {
                        contentH = rect.height;
                        contentW = rect.height * vidRatio;
                        offX = (rect.width - contentW) / 2;
                        offY = 0;
                    }

                    const mx = event.clientX - rect.left - offX;
                    const my = event.clientY - rect.top - offY;
                    // Ignore mouse in the letterbox area.
                    if (mx < 0 || my < 0 || mx > contentW || my > contentH) return;

                    const x = mx / contentW;
                    const y = my / contentH;
                    try {
                        this.channel.whisper('pointer', {
                            sharerUserId: peer.userId,
                            viewerUserId: this.me.userId,
                            viewerName: this.me.name,
                            x, y,
                            active: true,
                        });
                    } catch (e) {}
                },
                broadcastState() {
                    if (! this.channel) return;
                    try {
                        this.channel.whisper('state', {
                            userId: this.me.userId,
                            micOn: this.micOn,
                            camOn: this.camOn,
                            isSharing: this.sharing,
                        });
                    } catch (e) { /* best effort */ }
                },
                toggleWatchScreen(peer) {
                    if (! peer || ! peer.isSharing) return;
                    const next = ! peer.watchingScreen;
                    if (this.peers[peer.userId]) {
                        this.peers[peer.userId].watchingScreen = next;
                        this.peers = { ...this.peers };
                    }
                    if (! this.channel) return;
                    try {
                        this.channel.whisper('view-toggle', {
                            sharerUserId: peer.userId,
                            viewerUserId: this.me.userId,
                            watching: next,
                        });
                    } catch (e) {}
                },
                leave() {
                    const wasAlone = Object.keys(this.peers).length === 0;
                    const total = this.seenParticipants.size;
                    const roomId = this.roomId;

                    Object.keys(this.peers).forEach(uid => this.removePeer(Number(uid)));
                    if (this.channel && this.roomId) {
                        try { window.Echo.leave('bonfire.meeting.' + this.roomId); } catch (e) {}
                    }
                    this.channel = null;
                    this.pendingSignals = {};

                    if (wasAlone && roomId) {
                        // I was the last one here — server computes duration from cached start time.
                        this.$wire.announceEnd(roomId, total);
                    }
                    if (this.localStream) {
                        this.localStream.getTracks().forEach(t => t.stop());
                        this.localStream = null;
                    }
                    if (this.durationTimer) { clearInterval(this.durationTimer); this.durationTimer = null; }
                    this.active = false;
                    this.startedAt = null;
                    this.durationLabel = '00:00';
                    this.roomId = null;
                    this.roomName = '';
                    this.micOn = true;
                    this.camOn = true;
                    this.showInvite = false;
                    this.inviteCandidates = [];
                    this.invitedIds = [];
                    this.announcedStart = false;
                    this.seenParticipants = new Set();
                    this.showQuality = false;
                    this.joining = false;
                    if (this.screenStream) { this.screenStream.getTracks().forEach(t => t.stop()); this.screenStream = null; }
                    this.cameraTrack = null;
                    this.sharing = false;
                    this.showShareOptions = false;
                    this.watcherIds = [];
                    this.remotePointers = {};
                    this.pendingPointers = {};
                    this.pointerFlushScheduled = false;
                },
                startDurationTimer() {
                    if (this.durationTimer) return;
                    this.durationTimer = setInterval(() => {
                        const s = Math.floor((Date.now() - this.startedAt) / 1000);
                        const m = Math.floor(s / 60);
                        this.durationLabel = String(m).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
                    }, 1000);
                },
                syncStartedAt(epochSeconds) {
                    const ms = Number(epochSeconds) * 1000;
                    if (! ms) return;
                    this.startedAt = ms;
                    this.startDurationTimer();
                },
            };
        };
    </script>
@endonce
