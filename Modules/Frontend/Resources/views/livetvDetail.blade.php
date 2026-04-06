@extends('frontend::layouts.master')

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
                                <div class="livetv-chat-panel__avatar" id="chat-avatar">{{ strtoupper(substr($chatGuestName ?? 'AN', 0, 2)) }}</div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="small text-muted">{{ ($chatIdentityType ?? 'guest') === 'user' ? 'Signed in as' : 'Guest alias' }}</div>
                                    <div class="fw-semibold text-white text-truncate" id="chat-active-name">{{ $chatGuestName ?? 'Anonymous' }}</div>
                                </div>
                                @if (!empty($chatCanChangeName))
                                    <button type="button" class="btn btn-sm btn-outline-light" id="chat-change-name">New name</button>
                                @endif
                            </div>

                            <div class="livetv-chat-panel__messages" id="chat-messages">
                                @forelse ($chatMessages as $chatMessage)
                                    <article class="chat-message-item" data-message-id="{{ $chatMessage['id'] }}">
                                        <div class="chat-message-item__avatar">{{ strtoupper(substr($chatMessage['guest_name'], 0, 1)) }}</div>
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

                            <div class="livetv-chat-panel__composer" id="chat-composer-card">
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
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.82rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, #ff4e45, #ff8c42);
        flex: 0 0 36px;
    }

    .livetv-chat-panel__messages {
        flex: 1 1 auto;
        min-height: 320px;
        max-height: 540px;
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

        function toggleComposer(name) {
            composerCard.classList.remove('d-none');
            activeName.textContent = name || 'Anonymous';
            if (avatar) {
                avatar.textContent = initialsFromName(name);
            }
            if (changeNameButton) {
                changeNameButton.classList.toggle('d-none', !canChangeName);
            }
            if (messageInput) {
                messageInput.setAttribute('placeholder', `Chat publicly as ${name || 'Anonymous'}`);
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
                <div class="chat-message-item__avatar">${escapeHtml(initialsFromName(item.guest_name).substring(0, 1))}</div>
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
                authCard.classList.add('d-none');
                composerCard.classList.add('d-none');
                return;
            }

            toggleComposer(payload.guest_name || '');

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
