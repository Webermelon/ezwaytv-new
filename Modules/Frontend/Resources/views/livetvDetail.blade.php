@extends('frontend::layouts.master', ['entertainment' => $entertainment])

@section('title')
    {{ $data['name'] ?? '' }}
@endsection
@section('content')
    <div id="thumbnail-section" class="section-spacing-bottom">
        @php
            if ($data['stream_type'] == 'Embedded') {
                $videodata = Crypt::encryptString($data['embedded']);
            } else {
                $videodata = $data['server_url'];
            }
        @endphp
        <div class="container-fluid">
            <div class="row g-4 align-items-stretch livetv-player-layout">
                <div class="col-12 {{ !empty($data['enable_live_chat']) ? 'col-xl-8' : '' }}">
                    @include('frontend::components.section.thumbnail', [
                        'data' => $videodata,
                        'embedded' => $data['embedded'],
                        'type' => $data['stream_type'],
                        'slug' => $data['slug'],
                        'thumbnail_image' => $data['thumbnail_image'],
                        'dataAccess' => $data['access'],
                        'plan_id' => $data['plan_id'],
                        'content_type' => 'livetv',
                        'content_id' => $data['id'],
                        'content_video_type' => 'video',
                        'schedules' => $data['schedules'] ?? [],
                        'schedules_api_key' => $data['schedules_api_key'] ?? null,
                    ])
                </div>
                @if (!empty($data['enable_live_chat']))
                    <div class="col-12 col-xl-4">
                        <aside
                            class="livetv-chat-panel"
                            id="livetv-chat-panel"
                            data-channel-id="{{ $data['id'] }}"
                            data-fetch-url="{{ route('livetv-chat.messages', ['channelId' => $data['id']]) }}"
                            data-session-url="{{ route('livetv-chat.session', ['channelId' => $data['id']]) }}"
                            data-post-url="{{ route('livetv-chat.store', ['channelId' => $data['id']]) }}"
                            data-identity-type="{{ $chatIdentityType ?? 'guest' }}"
                            data-can-change-name="{{ !empty($chatCanChangeName) ? 'true' : 'false' }}"
                        >
                            <div class="livetv-chat-panel__header">
                                <div>
                                    <span class="livetv-chat-panel__eyebrow">Top chat</span>
                                    <h5 class="mb-0">Live chat</h5>
                                </div>
                                <span class="livetv-chat-panel__limit">No links. Max 120 words.</span>
                            </div>

                            <div class="livetv-chat-panel__identity" id="chat-identity-card">

                                {{-- Not identified --}}
                                <div id="chat-not-identified" class="w-100">
                                    <div class="small text-muted mb-2">You are not logged in</div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-primary flex-fill" id="chat-open-guest-modal">Guest Login</button>
                                        <a href="{{ route('login') }}?redirect={{ urlencode(request()->fullUrl()) }}" class="btn btn-sm btn-outline-light flex-fill">Login</a>
                                    </div>
                                </div>

                                {{-- Identified (guest or user) --}}
                                <div id="chat-identified" class="d-none w-100 d-flex align-items-center gap-2">
                                    <div class="livetv-chat-panel__avatar" id="chat-avatar">
                                        <img src="{{ asset('dummy-images/avatars/icon1.png') }}" alt="avatar" id="chat-avatar-img" class="w-100 h-100 rounded-circle object-cover">
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="small text-muted" id="chat-identity-label">{{ ($chatIdentityType ?? 'guest') === 'user' ? 'Signed in as' : 'Guest' }}</div>
                                        <div class="fw-semibold text-white text-truncate" id="chat-active-name">{{ $chatGuestName ?? 'Anonymous' }}</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="chat-logout">Logout</button>
                                </div>

                            </div>

                            <div class="livetv-chat-panel__messages" id="chat-messages">
                                @forelse ($chatMessages as $chatMessage)
                                    @php $avatarIdx = (array_sum(array_map('ord', str_split($chatMessage['guest_name']))) % 8) + 1; @endphp
                                    <article class="chat-message-item" data-message-id="{{ $chatMessage['id'] }}">
                                        <div class="chat-message-item__avatar"><img src="{{ asset('dummy-images/avatars/icon'.$avatarIdx.'.png') }}" alt="avatar"></div>
                                        <div class="chat-message-item__body">
                                            <div class="chat-message-item__meta">
                                                <strong>{{ $chatMessage['guest_name'] }}</strong>
                                                <span>{{ $chatMessage['time'] }}</span>
                                            </div>
                                            <p>{{ $chatMessage['message'] }}</p>
                                        </div>
                                    </article>
                                @empty
                                    <div class="chat-empty-state" id="chat-empty-state">
                                        No comments yet. Start the conversation.
                                    </div>
                                @endforelse
                            </div>

                            <div class="livetv-chat-panel__composer d-none" id="chat-composer-card">
                                <textarea
                                    id="chat-message-input"
                                    class="form-control"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Chat publicly as {{ $chatGuestName ?? 'Anonymous' }}"
                                ></textarea>
                                <div class="d-flex justify-content-between align-items-center gap-3 mt-2">
                                    <small class="text-muted" id="chat-word-count">0 / 120 words</small>
                                    <button type="button" class="btn btn-primary" id="chat-send-message">Send</button>
                                </div>
                            </div>

                            <p class="livetv-chat-panel__error d-none" id="chat-error"></p>
                        </aside>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="detail-section">
        <div class="detail-page-info section-spacing">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-12 col-md-12">
                        <div class="movie-detail-content">
                            @if (!empty($data['category']))
                                <span class="badge bg-primary mb-2">{{ $data['category'] }}</span>
                            @endif
                            <h4>{{ $data['name'] }}</h4>
                            @include('frontend::components.section.content_stats')
                            <p class="font-size-14 js-episode-desc">
                                <span class="js-desc-text">{!! Str::limit(strip_tags($data['description']), 300) !!}</span>
                                @if(strlen(strip_tags($data['description'])) > 300)
                                    <a href="javascript:void(0)" class="btn btn-link p-0 align-baseline js-episode-toggle">{{ __('messages.read_more') }}</a>
                                @endif
                            </p>

                            <script>
                                (function(){
                                    var container = document.currentScript.previousElementSibling;
                                    if(!container) return;
                                    var toggle = container.querySelector('.js-episode-toggle');
                                    var desc = container.querySelector('.js-desc-text');
                                    if(!toggle || !desc) return;

                                    var fullText = `{!! addslashes($data['description']) !!}`;
                                    var shortText = `{!! addslashes(Str::limit(strip_tags($data['description']), 300)) !!}`;
                                    var expanded = false;

                                    toggle.addEventListener('click', function(e){
                                        e.preventDefault();
                                        if(!expanded){
                                            desc.innerHTML = fullText;
                                            toggle.textContent = ("{{ __('messages.read_less') ?? 'Read Less' }}").trim();
                                        } else {
                                            desc.innerHTML = shortText;
                                            toggle.textContent = ("{{ __('messages.read_more') ?? 'Read More' }}").trim();
                                        }
                                        expanded = !expanded;
                                    });
                                })();
                                </script>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ad Banner Slider: shown under channel title/description --}}
    @include('frontend::components.section.ad_banner_slider', ['placement' => 'livetv'])

    <div class="section-spacing-bottom">
        <div class="container-fluid">
            @if (!empty($suggestions))
                <h4>{{ __('frontend.suggested_channels') }}</h4>
                <div class="row mt-3 gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4">
                    @foreach ($suggestions as $suggested)
                        <div class="col">
                            <a href="{{ route('livetv-details', ['id' => $suggested['slug']]) }}"
                                class="livetv-card d-block position-relative">
                                <img src="{{ $suggested['poster_image'] }}" alt="{{ $suggested['name'] }}"
                                    class="livetv-img object-cover img-fluid w-100 rounded">
                                <span class="live-card-badge">
                                    <span class="live-badge fw-semibold text-uppercase">{{ __('frontend.live') }}</span>
                                </span>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="modal fade" id="DeviceSupport" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content position-relative">
                <div class="modal-body user-login-card m-0 p-4 position-relative">
                    <button type="button" class="btn btn-primary custom-close-btn rounded-2" data-bs-dismiss="modal">
                        <i class="ph ph-x text-white fw-bold align-middle"></i>
                    </button>

                    <div class="modal-body">
                        {{ __('frontend.device_not_support') }}
                    </div>

                    <div class="d-flex align-items-center justify-content-center">
                        <a
                            href="{{ Auth::check() ? route('subscriptionPlan') : route('login') }}"class="btn btn-primary mt-5">{{ __('frontend.upgrade') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live chat login / guest modal -->
    <div class="modal" id="ChatIdentityModal" tabindex="-1" aria-hidden="true" style="display:none;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content position-relative">
                <div class="modal-body user-login-card m-0 p-4 position-relative">
                    <button type="button" class="btn btn-primary custom-close-btn rounded-2" id="chat-modal-close">
                        <i class="ph ph-x text-white fw-bold align-middle"></i>
                    </button>

                    <h5 class="mb-3">Guest Login</h5>
                    <p class="small text-muted">Choose a display name and avatar to join the chat.</p>

                    <div id="chat-guest-form">
                        <label class="form-label">Display name</label>
                        <div class="input-group mb-2">
                            <input type="text" id="chat-guest-name" class="form-control" placeholder="Anonymous__123" maxlength="40">
                            <button class="btn btn-outline-secondary" type="button" id="chat-guest-generate">Generate</button>
                        </div>

                        <label class="form-label">Choose Avatar</label>
                        <div class="d-flex flex-wrap gap-2 mb-3" id="chat-avatar-list">
                            @foreach(range(1,8) as $i)
                            <button type="button" class="avatar-choice {{ $i === 1 ? 'active' : '' }}" data-avatar="{{ asset('dummy-images/avatars/icon'.$i.'.png') }}">
                                <img src="{{ asset('dummy-images/avatars/icon'.$i.'.png') }}" alt="avatar {{ $i }}">
                            </button>
                            @endforeach
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary" id="chat-guest-submit">Join chat</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('after-styles')
<style>
    .livetv-player-layout .detail-page-banner,
    .livetv-player-layout .video-player-wrapper,
    .livetv-player-layout .video-player {
        height: 100%;
    }

    .livetv-chat-panel {
        min-height: 100%;
        background: #0f0f0f;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
    }

    .livetv-chat-panel__header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem 1rem 0.75rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .livetv-chat-panel__eyebrow {
        display: inline-block;
        margin-bottom: 0.3rem;
        text-transform: uppercase;
        letter-spacing: 0.14em;
        font-size: 0.72rem;
        color: #ff4e45;
    }

    .livetv-chat-panel__limit,
    .livetv-chat-panel__error,
    .chat-message-item__meta span,
    .chat-empty-state {
        color: rgba(255, 255, 255, 0.62);
        font-size: 0.82rem;
    }

    .livetv-chat-panel__identity,
    .livetv-chat-panel__composer {
        padding: 0.9rem 1rem;
    }

    .livetv-chat-panel__identity {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .livetv-chat-panel__avatar,
    .chat-message-item__avatar {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        overflow: hidden;
        flex: 0 0 36px;
        background: #222;
    }

    .livetv-chat-panel__avatar img,
    .chat-message-item__avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        border-radius: 999px;
    }

    #chat-avatar-list .avatar-choice {
        padding: 3px;
        border: 2px solid transparent;
        border-radius: 999px;
        background: transparent;
        cursor: pointer;
        transition: border-color 0.15s;
    }

    #chat-avatar-list .avatar-choice img {
        width: 44px;
        height: 44px;
        border-radius: 999px;
        display: block;
        object-fit: cover;
    }

    #chat-avatar-list .avatar-choice.active,
    #chat-avatar-list .avatar-choice:hover {
        border-color: #3ea6ff;
    }

    .livetv-chat-panel__messages {
        flex: 1 1 auto;
        min-height: 320px;
        max-height: 340px;
        overflow-y: auto;
        padding: 0.75rem 1rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }

    .chat-message-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .chat-message-item__body {
        min-width: 0;
    }

    .chat-message-item__meta {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 0.2rem;
    }

    .chat-message-item p {
        margin: 0;
        color: #fff;
        word-break: break-word;
    }

    .chat-empty-state {
        min-height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border: 1px dashed rgba(255, 255, 255, 0.12);
        border-radius: 12px;
        padding: 1rem;
    }

    .livetv-chat-panel__composer {
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        background: #181818;
    }

    .livetv-chat-panel__composer textarea.form-control {
        background: #121212;
        border-color: rgba(255, 255, 255, 0.09);
        color: #fff;
        resize: none;
    }

    .livetv-chat-panel__composer textarea.form-control::placeholder {
        color: rgba(255, 255, 255, 0.45);
    }

    .livetv-chat-panel .btn-primary {
        background: #3ea6ff;
        border-color: #3ea6ff;
        color: #081018;
        font-weight: 600;
    }

    @media (max-width: 1199px) {
        .livetv-chat-panel__messages {
            max-height: 380px;
        }
    }

    /* Custom chat modal overlay (no Bootstrap JS required) */
    #ChatIdentityModal {
        position: fixed;
        inset: 0;
        z-index: 1055;
        background: rgba(0,0,0,0.65);
        align-items: center;
        justify-content: center;
    }
    #ChatIdentityModal.chat-modal--open {
        display: flex !important;
    }
    #ChatIdentityModal .modal-dialog {
        width: 100%;
        max-width: 460px;
        margin: 1rem;
    }
</style>
@endpush

@push('after-scripts')
<script>
    (function () {
        const panel = document.getElementById('livetv-chat-panel');
        if (!panel) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const changeNameButton = document.getElementById('chat-change-name');
        const composerCard = document.getElementById('chat-composer-card');
        const sendButton = document.getElementById('chat-send-message');
        const messageInput = document.getElementById('chat-message-input');
        const wordCount = document.getElementById('chat-word-count');
        const messagesContainer = document.getElementById('chat-messages');
        const activeName = document.getElementById('chat-active-name');
        const avatar = document.getElementById('chat-avatar');
        const errorBox = document.getElementById('chat-error');
        const canChangeName = panel.dataset.canChangeName === 'true';
        let poller = null;
        let hasBootstrappedMessages = !!messagesContainer.querySelector('[data-message-id]');
        let isIdentified = false;

        function showError(message) {
            errorBox.textContent = message;
            errorBox.classList.remove('d-none');
        }

        function clearError() {
            errorBox.textContent = '';
            errorBox.classList.add('d-none');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function countWords(value) {
            const normalized = value.trim();
            if (!normalized) {
                return 0;
            }

            return normalized.split(/\s+/).filter(Boolean).length;
        }

        function updateWordCount() {
            if (!messageInput || !wordCount) {
                return;
            }

            wordCount.textContent = `${countWords(messageInput.value)} / 120 words`;
        }

        function initialsFromName(name) {
            return (name || 'AN').replace(/[^a-z0-9]/gi, '').substring(0, 2).toUpperCase() || 'AN';
        }

        function hasChatIdentityCookie() {
            return document.cookie.split(';').some(function (c) {
                return c.trim().startsWith('livetv_chat_identity=');
            });
        }

        function showNotIdentified() {
            isIdentified = false;
            document.getElementById('chat-not-identified')?.classList.remove('d-none');
            document.getElementById('chat-identified')?.classList.add('d-none');
            composerCard?.classList.add('d-none');
        }

        let currentAvatarUrl = '{{ asset('dummy-images/avatars/icon1.png') }}';
        const AVATAR_BASE = '{{ asset('dummy-images/avatars/') }}';
        const AVATAR_COUNT = 8;

        // Deterministic avatar from name — consistent for every viewer
        function nameToAvatarUrl(name) {
            let hash = 0;
            const s = String(name || 'anonymous');
            for (let i = 0; i < s.length; i++) { hash = (hash * 31 + s.charCodeAt(i)) >>> 0; }
            const idx = (hash % AVATAR_COUNT) + 1;
            return AVATAR_BASE + '/icon' + idx + '.png';
        }

        function showIdentified(name, label, avatarUrl) {
            isIdentified = true;
            if (avatarUrl != null) currentAvatarUrl = avatarUrl;
            document.getElementById('chat-not-identified')?.classList.add('d-none');
            document.getElementById('chat-identified')?.classList.remove('d-none');
            composerCard?.classList.remove('d-none');
            if (activeName) activeName.textContent = name || 'Anonymous';
            const avatarImg = document.getElementById('chat-avatar-img');
            if (avatarImg) avatarImg.src = currentAvatarUrl;
            if (messageInput) messageInput.setAttribute('placeholder', `Chat publicly as ${name || 'Anonymous'}`);
            const labelEl = document.getElementById('chat-identity-label');
            if (labelEl) labelEl.textContent = label || 'Guest';
        }

        function toggleComposer(name) {
            if (isIdentified) {
                showIdentified(name, null, null);
            }
        }

        function renderMessages(messages) {
            messagesContainer.innerHTML = '';
            if (!messages.length) {
                messagesContainer.innerHTML = '<div class="chat-empty-state" id="chat-empty-state">No comments yet. Start the conversation.</div>';
                return;
            }

            messages.forEach((item) => appendMessage(item, false));
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function appendMessage(item, shouldScroll = true) {
            const emptyState = document.getElementById('chat-empty-state');
            if (emptyState) {
                emptyState.remove();
            }

            if (messagesContainer.querySelector(`[data-message-id="${item.id}"]`)) {
                return;
            }

            const article = document.createElement('article');
            article.className = 'chat-message-item';
            article.dataset.messageId = item.id;
            article.innerHTML = `
                <div class="chat-message-item__avatar"><img src="${escapeHtml(item.avatar_url || nameToAvatarUrl(item.guest_name))}"></div>
                <div class="chat-message-item__body">
                    <div class="chat-message-item__meta">
                        <strong>${escapeHtml(item.guest_name)}</strong>
                        <span>${escapeHtml(item.time || 'just now')}</span>
                    </div>
                    <p>${escapeHtml(item.message)}</p>
                </div>
            `;

            messagesContainer.appendChild(article);
            if (shouldScroll) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        async function fetchMessages() {
            const response = await fetch(panel.dataset.fetchUrl, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Unable to load chat messages.');
            }

            if (!payload.enabled) {
                messagesContainer.innerHTML = '<div class="chat-empty-state">Live chat is currently turned off for this channel.</div>';
                composerCard?.classList.add('d-none');
                return;
            }

            if (isIdentified) {
                toggleComposer(payload.guest_name || '');
            }

            if (!hasBootstrappedMessages) {
                renderMessages(payload.messages || []);
                hasBootstrappedMessages = true;
                return;
            }

            (payload.messages || []).forEach((item) => appendMessage(item, false));
        }

        async function postJson(url, body) {
            const response = await fetch(url, {
                method: 'POST', 
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Request failed.');
            }

            return payload;
        }

        async function refreshGuestName() {
            clearError();
            try {
                const payload = await postJson(panel.dataset.sessionUrl, {});
                toggleComposer(payload.guest_name);
            } catch (error) {
                showError(error.message);
            }
        }

        function showIdentityModal() {
            const nameInput = document.getElementById('chat-guest-name');
            if (nameInput && !nameInput.value) {
                nameInput.value = generateAnonName();
            }
            const modalEl = document.getElementById('ChatIdentityModal');
            if (!modalEl) return;
            modalEl.style.display = 'flex';
            modalEl.classList.add('chat-modal--open');
            document.body.classList.add('chat-modal-open');
        }

        function hideIdentityModal() {
            const modalEl = document.getElementById('ChatIdentityModal');
            if (!modalEl) return;
            modalEl.style.display = 'none';
            modalEl.classList.remove('chat-modal--open');
            document.body.classList.remove('chat-modal-open');
        }

        function deleteIdentityCookie() {
            document.cookie = 'livetv_chat_identity=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            localStorage.removeItem('livetv_chat_avatar');
            localStorage.removeItem('livetv_chat_name');
        }

        function generateAnonName() {
            const suffix = Math.random().toString(36).substring(2, 8).toUpperCase();
            return 'Anonymous__' + suffix;
        }

        // Guest Login button opens the modal
        document.getElementById('chat-open-guest-modal')?.addEventListener('click', function () {
            showIdentityModal();
        });

        // Close modal via X button or backdrop click
        document.getElementById('chat-modal-close')?.addEventListener('click', hideIdentityModal);
        document.getElementById('ChatIdentityModal')?.addEventListener('click', function (e) {
            if (e.target === this) hideIdentityModal();
        });

        // Generate new name
        document.getElementById('chat-guest-generate')?.addEventListener('click', function () {
            const input = document.getElementById('chat-guest-name');
            if (input) input.value = generateAnonName();
        });

        // Avatar picker
        document.querySelectorAll('#chat-avatar-list .avatar-choice').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#chat-avatar-list .avatar-choice').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
            });
        });

        // Join chat (guest submit)
        document.getElementById('chat-guest-submit')?.addEventListener('click', async function () {
            const nameInput = document.getElementById('chat-guest-name');
            const guestName = (nameInput?.value || '').trim() || generateAnonName();
            const selectedAvatarBtn = document.querySelector('#chat-avatar-list .avatar-choice.active');
            const avatarUrl = selectedAvatarBtn?.dataset.avatar || currentAvatarUrl;
            localStorage.setItem('livetv_chat_avatar', avatarUrl);
            localStorage.setItem('livetv_chat_name', guestName);
            try {
                const res = await postJson(panel.dataset.sessionUrl, { guest_name: guestName });
                showIdentified(res.guest_name || guestName, 'Guest', avatarUrl);
                hideIdentityModal();
            } catch (err) {
                showError(err.message);
            }
        });

        // Logout
        document.getElementById('chat-logout')?.addEventListener('click', function () {
            deleteIdentityCookie();
            fetch(panel.dataset.sessionUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                body: JSON.stringify({ logout: true })
            }).finally(function () {
                showNotIdentified();
            });
        });

        async function sendMessage() {
            clearError();

            if (countWords(messageInput.value) > 120) {
                showError('Messages must be 120 words or fewer.');
                return;
            }

            try {
                const payload = await postJson(panel.dataset.postUrl, {
                    message: messageInput.value
                });
                // Inject the current user's chosen avatar so their own messages show correctly
                payload.message.avatar_url = currentAvatarUrl;
                appendMessage(payload.message);
                hasBootstrappedMessages = true;
                messageInput.value = '';
                updateWordCount();
            } catch (error) {
                showError(error.message);
            }
        }

        if (canChangeName) {
            changeNameButton?.addEventListener('click', function () {
                refreshGuestName();
            });
        }
        sendButton?.addEventListener('click', sendMessage);
        messageInput?.addEventListener('input', updateWordCount);
        messageInput?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });

        updateWordCount();

        // Initialise identity state from page context
        // PHP reliably decodes Laravel's encrypted cookie server-side, so we trust it over JS cookie checks
        (function initIdentity() {
            @if(auth()->check())
                const userAvatar = '{{ auth()->user()->profile_image ? asset('storage/'.auth()->user()->profile_image) : asset('dummy-images/avatars/icon1.png') }}';
                showIdentified('{{ addslashes($chatGuestName ?? '') }}', 'Signed in as', userAvatar);
            @elseif(!empty($chatIsIdentifiedGuest))
                const savedAvatar = localStorage.getItem('livetv_chat_avatar') || currentAvatarUrl;
                const savedName   = localStorage.getItem('livetv_chat_name') || '{{ addslashes($chatGuestName ?? '') }}';
                showIdentified(savedName, 'Guest', savedAvatar);
            @else
                showNotIdentified();
            @endif
        })();

        fetchMessages().catch((error) => showError(error.message));
        poller = window.setInterval(() => {
            fetchMessages().catch(() => {});
        }, 10000);

        window.addEventListener('beforeunload', function () {
            if (poller) {
                window.clearInterval(poller);
            }
        });
    })();
</script>
@endpush

@push('ezstats-meta')
<script>
    window._ezPageMeta = { content_type: 'livetv', content_id: {{ (int)($data['id'] ?? 0) }} };
</script>
@endpush
