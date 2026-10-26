@php
    $channel = $data ?? null;
    $chatBanners = old('chat_ad_banners', $channel?->chat_ad_banners ?? []);
    if (empty($chatBanners)) {
        $chatBanners = [['image_url' => '', 'link_url' => '', 'label' => '']];
    }
@endphp

<div class="col-12">
    <div class="border rounded p-3 mt-2">
        <h6 class="mb-1">Chat Settings</h6>
        <small class="text-muted d-block mb-3">These settings apply only to this Live TV channel.</small>
        <div class="row gy-3">
            <div class="col-md-6">
                {{ html()->label('Live Chat', 'enable_live_chat')->class('form-label') }}
                <div class="d-flex justify-content-between align-items-center form-control">
                    {{ html()->label('Enable live chat beside player', 'enable_live_chat')->class('form-label mb-0 text-body') }}
                    <div class="form-check form-switch">
                        {{ html()->hidden('enable_live_chat', 0) }}
                        {{ html()->checkbox('enable_live_chat', old('enable_live_chat', $channel?->enable_live_chat ?? false), 1)->class('form-check-input')->id('enable_live_chat') }}
                    </div>
                </div>
                <small class="text-muted d-block mt-1">Messages remain saved when chat is disabled.</small>
            </div>
            <div class="col-md-6">
                {{ html()->label('Chat Heading', 'chat_title')->class('form-label') }}
                {{ html()->text('chat_title', old('chat_title', $channel?->chat_title ?? 'Live Chat'))->class('form-control')->id('chat_title')->placeholder('Live Chat') }}
                @error('chat_title')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>
</div>

<div class="col-12">
    <div class="border rounded p-3 mt-2">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
            <div>
                <h6 class="mb-1">Chat Ad Banner Settings</h6>
                <small class="text-muted">Rotate channel-specific promotional banners above the chat messages.</small>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" id="add-chat-banner">
                <i class="ph ph-plus me-1"></i>Add Banner
            </button>
        </div>
        <div class="row gy-3 mb-3">
            <div class="col-md-12">
                {{ html()->label('Banner Slider', 'chat_ads_enabled')->class('form-label') }}
                <div class="d-flex justify-content-between align-items-center form-control">
                    {{ html()->label('Show ads in live chat', 'chat_ads_enabled')->class('form-label mb-0 text-body') }}
                    <div class="form-check form-switch">
                        {{ html()->hidden('chat_ads_enabled', 0) }}
                        {{ html()->checkbox('chat_ads_enabled', old('chat_ads_enabled', $channel?->chat_ads_enabled ?? false), 1)->class('form-check-input')->id('chat_ads_enabled') }}
                    </div>
                </div>
            </div>
        </div>
        <div id="chat-banner-list" class="d-grid gap-3">
            @foreach ($chatBanners as $index => $banner)
                <div class="chat-banner-row border rounded p-3" data-index="{{ $index }}">
                    <div class="row gy-2 align-items-end">
                        <div class="col-lg-4">
                            <label class="form-label">Image URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" name="chat_ad_banners[{{ $index }}][image_url]" value="{{ $banner['image_url'] ?? '' }}" placeholder="https://example.com/banner.jpg">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Destination URL</label>
                            <input type="url" class="form-control" name="chat_ad_banners[{{ $index }}][link_url]" value="{{ $banner['link_url'] ?? '' }}" placeholder="https://example.com">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Accessible Label</label>
                            <input type="text" class="form-control" maxlength="100" name="chat_ad_banners[{{ $index }}][label]" value="{{ $banner['label'] ?? '' }}" placeholder="Sponsor name">
                        </div>
                        <div class="col-lg-1 d-grid">
                            <button type="button" class="btn btn-outline-danger remove-chat-banner" title="Remove banner"><i class="ph ph-trash"></i></button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @error('chat_ad_banners.*.image_url')<span class="text-danger d-block mt-2">{{ $message }}</span>@enderror
    </div>
</div>

@push('after-scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const list = document.getElementById('chat-banner-list');
        const addButton = document.getElementById('add-chat-banner');
        if (!list || !addButton) return;

        const reindex = function () {
            list.querySelectorAll('.chat-banner-row').forEach(function (row, index) {
                row.dataset.index = index;
                row.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace(/chat_ad_banners\[\d+\]/, 'chat_ad_banners[' + index + ']');
                });
            });
        };

        addButton.addEventListener('click', function () {
            const index = list.querySelectorAll('.chat-banner-row').length;
            const row = document.createElement('div');
            row.className = 'chat-banner-row border rounded p-3';
            row.innerHTML = '<div class="row gy-2 align-items-end">' +
                '<div class="col-lg-4"><label class="form-label">Image URL <span class="text-danger">*</span></label><input type="url" class="form-control" name="chat_ad_banners[' + index + '][image_url]" placeholder="https://example.com/banner.jpg"></div>' +
                '<div class="col-lg-4"><label class="form-label">Destination URL</label><input type="url" class="form-control" name="chat_ad_banners[' + index + '][link_url]" placeholder="https://example.com"></div>' +
                '<div class="col-lg-3"><label class="form-label">Accessible Label</label><input type="text" class="form-control" maxlength="100" name="chat_ad_banners[' + index + '][label]" placeholder="Sponsor name"></div>' +
                '<div class="col-lg-1 d-grid"><button type="button" class="btn btn-outline-danger remove-chat-banner" title="Remove banner"><i class="ph ph-trash"></i></button></div>' +
                '</div>';
            list.appendChild(row);
        });

        list.addEventListener('click', function (event) {
            const button = event.target.closest('.remove-chat-banner');
            if (!button) return;
            button.closest('.chat-banner-row').remove();
            reindex();
        });
    });
</script>
@endpush
