@once
    <style>
        .ez-media-info {
            display: grid;
            gap: .2rem;
            margin-top: .5rem;
            font-size: .72rem;
            line-height: 1.25;
        }

        .ez-media-info span,
        .ez-media-url {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ez-media-actions {
            display: flex;
            gap: .35rem;
            margin-top: .5rem;
        }

        .ez-media-actions .btn {
            flex: 1 1 auto;
            min-height: 1.875rem;
            padding-inline: .5rem;
        }

        .ez-processing-track {
            display: grid;
            gap: .25rem;
            margin-top: .45rem;
        }

        .ez-processing-track .progress {
            height: .35rem;
            background: rgba(255, 255, 255, .16);
        }

        .ez-processing-track .progress-bar {
            width: 100%;
        }

        .ez-media-processing-notice {
            border: 1px solid rgba(255, 193, 7, .45);
            border-radius: .375rem;
            margin-bottom: 1rem;
            padding: .65rem .8rem;
        }
    </style>
@endonce

<div class="bd-example">
    <nav>
        <div class="mb-3 nav nav-underline nav-tabs justify-content-between p-0 border-bottom rounded-0 bg-transparent"
            id="nav-tab" role="tablist">
            <div class="d-flex align-items-center gap-3">
                <button class="nav-link rounded-0 d-flex align-items-center" id="nav-upload-files-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-upload" type="button" role="tab" aria-controls="nav-upload"
                    aria-selected="true">{{ __('messages.upload_file') }}</button>
                <button class="nav-link  rounded-0 active" id="nav-media-library-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-media" type="button" role="tab" aria-controls="nav-media"
                    aria-selected="false">{{ __('messages.media_library') }} </button>
            </div>

            <div class="media-search py-2" id="media-search-container">
                {{-- <div class="d-flex">
                    <input type="text" id="media-search" class="form-control"
                        placeholder="{{ __('messages.search_media') }}">
                    <button class="btn text-danger close-icon d-none px-2" type="button" id="clear-search">
                        <i class="ph ph-x"></i> <!-- Change this icon to your desired close icon -->
                    </button>
                </div> --}}
            </div>
        </div>
    </nav>
    <div class="tab-content iq-tab-fade-up" id="nav-tab-content">
        <div class="tab-pane fade" id="nav-upload" role="tabpanel" aria-labelledby="nav-upload-files-tab">

            <div class="card m-0 bg-transparent">

                <input type="hidden" id="page_type" value="{{ $page_type ?? 'default' }}">
                <div class="input-group btn-file-upload">
                    {{ html()->button(__('<i class="ph ph-image"></i>' . __('messages.lbl_choose_image')))->class('input-group-text form-control')->type('button')->attribute('onclick', "document.getElementById('file_url_media').click()")->style('height:16rem') }}
                    {{ html()->file('file_url[]')->id('file_url_media')->class('form-control')->attribute('accept', '.jpeg, .jpg, .png, .gif, .mov, .mp4, .avi')->attribute('multiple', true)->style('display: none;')->required() }}
                </div>
                <div class="uploaded-image" id="selectedImageContainerThumbnail">
                    @php
                        $fileUrl = old('file_url', '');
                        if (empty($fileUrl) && isset($data)) {
                            if (is_array($data)) {
                                $fileUrl = $data['file_url'] ?? '';
                            } elseif (is_object($data)) {
                                $fileUrl = $data->file_url ?? '';
                            }
                        }
                    @endphp
                    @if (!empty($fileUrl))
                        <img src="{{ $fileUrl }}" class="img-fluid mb-2"
                            style="max-width: 100px; max-height: 100px;">
                    @endif
                </div>
                <div id="uploadedImages" class="my-3 d-flex flex-wrap align-items-center gap-3"></div>
                <div class="invalid-feedback text-center" id="file_url_media-error">File field is
                    required</div>
            </div>

            <div class="text-end">
                {{ html()->submit(trans('messages.save'))->class('btn btn-md btn-primary float-right')->id('submitButton')->attribute('disabled') }}
            </div>
        </div>
        <div class="tab-pane fade show active" id="nav-media" role="tabpanel" aria-labelledby="nav-media-library-tab"
            style="position: relative;">

            <div>
                <!-- Folder Navigation -->
                <div class="mb-3" id="folder-navigation" style="display: none;">
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="mb-0" id="current-folder-name"></h3>
                        <div class="d-flex align-items-center gap-3">
                            <div class="mb-0" id="search-bar-container" style="display: none;">
                                <div class="input-group">
                                    <span class="input-group-text pe-1">
                                        <i class="ph ph-magnifying-glass"></i>
                                    </span>
                                    <input type="text" class="form-control" id="mediaSearchInput"
                                        placeholder="{{ __('frontend.search_placeholder') }}"
                                        onkeyup="filterMediaContent()">
                                    <button class="btn btn-link clear-search" type="button" onclick="clearSearch()">
                                        <i class="ph ph-x"></i>
                                    </button>
                                </div>
                            </div>
                            <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"
                                type="button"
                                onclick="goBackToFolders()">
                                <i class="ph ph-caret-double-left"></i> {{ __('frontend.back') }}
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Folders Grid -->
                <div class="row gy-3" id="folders-grid">
                    @php
                        $activeDisk = env('ACTIVE_STORAGE', 'local');
                        $folders = [];

                        // Folders to exclude from UI
                        $excluded = ['avatars', 'subtitles'];

                        $formatFolder = function (string $path) {
                            $name = basename(trim($path, '/'));
                            $translationKey = 'folder_' . strtolower($name);

                            if (\Lang::has('messages.' . $translationKey)) {
                                $displayName = __('messages.' . $translationKey);
                            } else {
                                $displayName = ucfirst(str_replace(['-', '_'], ' ', $name));
                            }

                            return [
                                'name' => $name,
                                'display_name' => $displayName,
                                'path' => trim($path, '/'),
                            ];
                        };

                        if ($activeDisk === 'local') {
                            $root = storage_path('app/public');
                            if (is_dir($root)) {
                                // Read only directories, skip dot entries, and excluded names
                                foreach (scandir($root) as $entry) {
                                    if ($entry === '.' || $entry === '..') {
                                        continue;
                                    }
                                    if (in_array($entry, $excluded, true)) {
                                        continue;
                                    }
                                    $full = $root . '/' . $entry;
                                    if (is_dir($full)) {
                                        $folders[] = $formatFolder($entry);
                                    }
                                }
                            }
                        } else {
                            $disk = Storage::disk($activeDisk);
                            // Root-level directories in bucket
                            foreach ($disk->directories('') as $dir) {
                                $dir = trim($dir, '/');
                                $name = basename($dir);
                                if (in_array($name, $excluded, true)) {
                                    continue;
                                }
                                $folders[] = $formatFolder($dir);
                            }
                        }
                    @endphp

                    @foreach ($folders as $folder)
                        @if ($folder['name'] != 'avatars' && $folder['name'] != 'subtitles' && $folder['name'] != 'logo')
                            <div class="col-lg-3 col-md-2 col-sm-1">
                                <div class="card h-100 folder-card folder-card-clickable" data-folder-name="{{ $folder['name'] }}"
                                    style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <i class="ph ph-folder text-primary" style="font-size: 3rem;"></i>
                                        <h6 class="mt-2 mb-1">{{ $folder['display_name'] }}</h6>
                                        {{-- <small class="text-muted">{{ $folder['name'] }}</small> --}}
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <!-- Folder Contents -->
                <div class="row" id="folder-contents" style="display: none;">
                    <div class="col-12">
                        <!-- Search Bar for Images and Videos - Only shown when folder has media files -->
                        {{-- <div class="mb-3" id="search-bar-container" style="display: none;">
                            <div class="input-group">
                                <span class="input-group-text pe-1">
                                    <i class="ph ph-magnifying-glass"></i>
                                </span>
                                <input type="text" class="form-control" id="mediaSearchInput"
                                    placeholder="Search images and videos..." onkeyup="filterMediaContent()">
                                <button class="btn btn-primary" type="button" onclick="clearSearch()">
                                    <i class="ph ph-x"></i>
                                </button>
                            </div>
                        </div> --}}

                        <div class="media-scroll-container mb-3"
                            style="max-height: 540px; overflow-y: auto; overflow-x: hidden;">
                            <div class="row gy-3" id="mediaLibraryContent">
                                <!-- Contents will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="text-end">
               {{ html()
    ->button(__('messages.save'))
    ->type('button')
    ->class('btn btn-md btn-primary mt-2')
    ->id('mediaSubmitButton')
}}

            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL= '{{ url('/') }}';
    // Optimized File Manager - Single consolidated implementation
    const FileManager = {
        // Configuration
        config: {
            baseUrl: (function() {
                const metaTag = BASE_URL;
                if (metaTag) {
                    return metaTag;
                }
                // Fallback: use window.location.origin
                return window.location.origin;
            })(),
            pageLimit: 60,
            csrfToken: (function() {
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    return metaTag.getAttribute('content');
                }
                return '';
            })()
        },

        // State management
        state: {
            currentFolder: '',
            nextOffset: 0,
            isLoading: false,
            abortController: null,
            infiniteInitDone: false,
            ioObserver: null,
            originalContents: [],
            autoOpenFolder: new URLSearchParams(window.location.search).get('open_folder'),
            pendingSelectFile: new URLSearchParams(window.location.search).get('select_file'),
            autoOpenedFromQuery: false,
            processingRefreshTimer: null
        },
        // Track last applied search to avoid redundant renders
        lastSearchTerm: '',

        // File type mappings (cached for performance)
        fileMappings: {
            icons: {
                'jpg': 'ph-image',
                'jpeg': 'ph-image',
                'png': 'ph-image',
                'gif': 'ph-image',
                'webp': 'ph-image',
                'svg': 'ph-image',
                'mp4': 'ph-video',
                'avi': 'ph-video',
                'mov': 'ph-video',
                'webm': 'ph-video',
                'pdf': 'ph-file-pdf',
                'doc': 'ph-file-doc',
                'docx': 'ph-file-doc',
                'zip': 'ph-archive',
                'rar': 'ph-archive',
                '7z': 'ph-archive'
            },
            colors: {
                'jpg': 'text-success',
                'jpeg': 'text-success',
                'png': 'text-success',
                'gif': 'text-success',
                'webp': 'text-success',
                'svg': 'text-success',
                'mp4': 'text-primary',
                'avi': 'text-primary',
                'mov': 'text-primary',
                'webm': 'text-primary',
                'pdf': 'text-danger',
                'doc': 'text-info',
                'docx': 'text-info',
                'zip': 'text-warning',
                'rar': 'text-warning',
                '7z': 'text-warning'
            }
        },

        // Utility functions
        utils: {
            getFileIcon: (filename) => {
                const ext = filename.split('.').pop().toLowerCase();
                return FileManager.fileMappings.icons[ext] || 'ph-file';
            },

            getFileColor: (filename) => {
                const ext = filename.split('.').pop().toLowerCase();
                return FileManager.fileMappings.colors[ext] || 'text-secondary';
            },

            formatFileSize: (bytes) => {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },

            escapeHtml: (value) => {
                return String(value ?? '').replace(/[&<>"']/g, function(match) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[match];
                });
            },

            escapeJsString: (value) => {
                return String(value ?? '')
                    .replace(/\\/g, '\\\\')
                    .replace(/'/g, "\\'")
                    .replace(/\r/g, '')
                    .replace(/\n/g, '\\n');
            },

            formatUploadDate: (timestamp) => {
                if (!timestamp) return '';
                return new Date(timestamp * 1000).toLocaleString();
            },

            buildDateLine: (timestamp) => {
                const date = FileManager.utils.formatUploadDate(timestamp);
                return date ? `<div class="mt-2"><small class="text-muted">${FileManager.utils.escapeHtml(date)}</small></div>` : '';
            },

            getFileExtension: (filename) => {
                const parts = String(filename || '').split('.');
                return parts.length > 1 ? parts.pop().toUpperCase() : 'FILE';
            },

            getFolderFromUrl: (url) => {
                try {
                    const urlParts = url.split('/storage/');
                    return urlParts.length > 1 ? urlParts[1].split('/')[0] : 'default';
                } catch (error) {
                    return 'default';
                }
            }
        },

        // DOM operations
        dom: {
            updateSaveButtonState: () => {
                const btn = document.getElementById('mediaSubmitButton');
                if (!btn) return;
                const selectedCount = document.querySelectorAll(
                    '#mediaLibraryContent .iq-media-images.selected').length;
                btn.disabled = selectedCount === 0;
            },
            showLoading: () => {
                const container = document.getElementById('mediaLibraryContent');
                if (container) {
                    container.style.transition = 'opacity 0.3s ease';
                    container.style.opacity = '0.7';
                    container.innerHTML =
                        '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">{{ __('messages.loading') }}...</span></div><div class="mt-2">{{ __('messages.loading') }} folder contents...</div></div>';
                }
            },

            hideLoading: () => {
                const container = document.getElementById('mediaLibraryContent');
                if (container) {
                    container.style.opacity = '1';
                    container.style.transition = 'opacity 0.3s ease';
                }
            },

            showInfiniteScrollLoader: (show) => {
                let loader = document.getElementById('infiniteScrollLoader');
                if (!loader) {
                    loader = document.createElement('div');
                    loader.id = 'infiniteScrollLoader';
                    loader.className = 'text-center py-3';
                    loader.innerHTML =
                        '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">{{ __('frontend.loading_more') }}</span></div><div class="mt-1 small text-muted">{{ __('frontend.loading_more_content') }}</div>';
                    loader.style.display = 'none';
                    loader.style.transition = 'opacity 0.3s ease';
                    document.getElementById('mediaLibraryContent').appendChild(loader);
                }

                if (show) {
                    loader.style.display = 'block';
                    loader.style.opacity = '0';
                    setTimeout(() => {
                        loader.style.opacity = '1';
                    }, 10);
                } else {
                    loader.style.opacity = '0';
                    setTimeout(() => {
                        loader.style.display = 'none';
                    }, 300);
                }
            },

            showError: (message) => {
                const container = document.getElementById('mediaLibraryContent');
                if (container) {
                    container.innerHTML = `<div class="text-center text-danger">${message}</div>`;
                }
            },

            toggleSaveButton: (show) => {
                const saveBtn = document.getElementById('mediaSubmitButton');
                if (saveBtn) {
                    if (show) {
                        saveBtn.classList.remove('d-none');
                    } else {
                        saveBtn.classList.add('d-none');
                    }
                }
            },

            toggleSearchBar: (show) => {
                const searchBar = document.getElementById('search-bar-container');
                if (searchBar) {
                    searchBar.style.display = show ? 'block' : 'none';
                }
            }
        },

        // Navigation functions
        navigation: {
            openFolder: (folderName) => {
                FileManager.cleanup();
                FileManager.state.currentFolder = folderName;
                FileManager.state.nextOffset = 0;
                FileManager.state.infiniteInitDone = false;

                // Clear any existing content to ensure fresh load
                const mediaLibraryContent = document.getElementById('mediaLibraryContent');
                if (mediaLibraryContent) {
                    mediaLibraryContent.innerHTML = '';
                }

                // Update UI
                document.getElementById('folder-navigation').style.display = 'block';
                (function() {
                    const el = document.getElementById('current-folder-name');
                    const transKey = 'folder_' + folderName.toLowerCase();
                    const display = (window.localMessagesUpdate?.messages?.[transKey] && window.localMessagesUpdate.messages[transKey] !== 'messages.' + transKey)
                        ? window.localMessagesUpdate.messages[transKey]
                        : ((typeof folderName === 'string' && folderName.length > 0) ? (folderName.charAt(0).toUpperCase() + folderName.slice(1)) : folderName);
                    if (el) el.textContent = display;
                })();
                document.getElementById('folders-grid').style.display = 'none';
                document.getElementById('folder-contents').style.display = 'block';
                FileManager.dom.toggleSaveButton(false);

                FileManager.loadFolderContents(folderName);
            },

            goBackToFolders: () => {
                FileManager.cleanup();
                const current = FileManager.state.currentFolder || '';
                const hasParent = current && current.includes('/');

                if (hasParent) {
                    // Navigate one level up (parent folder)
                    const parent = current.substring(0, current.lastIndexOf('/'));
                    FileManager.state.currentFolder = parent;
                    FileManager.state.nextOffset = 0;
                    FileManager.state.infiniteInitDone = false;

                    // Keep folder view visible, update header and reload contents
                    document.getElementById('folder-navigation').style.display = 'block';
                    (function() {
                        const base = parent.split('/').pop();
                        const el = document.getElementById('current-folder-name');
                        const transKey = 'folder_' + base.toLowerCase();
                        const display = (window.localMessagesUpdate?.messages?.[transKey] && window.localMessagesUpdate.messages[transKey] !== 'messages.' + transKey)
                            ? window.localMessagesUpdate.messages[transKey]
                            : ((typeof base === 'string' && base.length > 0) ? (base.charAt(0).toUpperCase() + base.slice(1)) : base);
                        if (el) el.textContent = display || '';
                    })();
                    document.getElementById('folders-grid').style.display = 'none';
                    document.getElementById('folder-contents').style.display = 'block';
                    document.getElementById('mediaLibraryContent').innerHTML = '';
                    FileManager.dom.toggleSaveButton(false);
                    FileManager.loadFolderContents(parent);
                } else {
                    // At root level → show root folders
                    FileManager.state.currentFolder = '';
                    FileManager.state.nextOffset = 0;
                    FileManager.state.infiniteInitDone = false;

                    document.getElementById('folder-navigation').style.display = 'none';
                    document.getElementById('folder-contents').style.display = 'none';
                    document.getElementById('folders-grid').style.display = 'flex';
                    document.getElementById('mediaLibraryContent').innerHTML = '';
                    FileManager.dom.toggleSaveButton(false);
                }
            },

            openSubFolder: (folderPath) => {
                FileManager.cleanup();
                FileManager.state.currentFolder = folderPath;
                FileManager.state.nextOffset = 0;
                FileManager.state.infiniteInitDone = false;
                FileManager.loadFolderContents(folderPath);
                (function() {
                    const base = folderPath.split('/').pop();
                    const el = document.getElementById('current-folder-name');
                    const transKey = 'folder_' + base.toLowerCase();
                    const display = (window.localMessagesUpdate?.messages?.[transKey] && window.localMessagesUpdate.messages[transKey] !== 'messages.' + transKey)
                        ? window.localMessagesUpdate.messages[transKey]
                        : ((typeof base === 'string' && base.length > 0) ? (base.charAt(0).toUpperCase() + base.slice(1)) : base);
                    if (el) el.textContent = display;
                })();
            }
        },

        // API functions
        api: {
            fetchFolderContents: async (folderName, offset = 0) => {
                // Add cache-busting parameter to ensure fresh data
                const timestamp = new Date().getTime();
                const sortParam = encodeURIComponent(FileManager.state.sort || window.fbFolderSort || 'modified_desc');
                const url =
                    `${FileManager.config.baseUrl}/app/media-library/get-folder-contents?folder=${encodeURIComponent(folderName)}&limit=${FileManager.config.pageLimit}&offset=${offset}&sort=${sortParam}&_t=${timestamp}`;

                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': FileManager.config.csrfToken,
                        'Cache-Control': 'no-cache'
                    },
                    signal: FileManager.state.abortController?.signal
                });

                return response.json();
            },

            deleteFile: async (fileId, url, path = null) => {
                const response = await fetch(
                    `${FileManager.config.baseUrl}/app/media-library/destroy`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': FileManager.config.csrfToken
                        },
                        body: JSON.stringify({ url, path })
                    });

                return response.json();
            },
        },

        copyUrl: (url, button) => {
            const done = () => {
                if (!button) return;
                const original = button.innerHTML;
                button.innerHTML = '<i class="ph ph-check"></i> Copied';
                setTimeout(() => {
                    button.innerHTML = original;
                }, 1400);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done).catch(() => {
                    FileManager.fallbackCopyUrl(url);
                    done();
                });
                return;
            }

            FileManager.fallbackCopyUrl(url);
            done();
        },

        fallbackCopyUrl: (url) => {
            const input = document.createElement('textarea');
            input.value = url;
            input.setAttribute('readonly', 'readonly');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
        },

        selectTile: (tile) => {
            if (!tile) return;

            document.querySelectorAll('#mediaLibraryContent .iq-media-images.selected').forEach(function(selectedTile) {
                if (selectedTile !== tile) {
                    selectedTile.classList.remove('selected');
                }
            });

            document.querySelectorAll('#mediaLibraryContent .iq-media-select-button').forEach(function(button) {
                button.classList.remove('btn-primary');
                button.classList.add('btn-outline-primary');
                button.innerHTML = '<i class="ph ph-check"></i> Select';
            });

            tile.classList.add('selected');
            const selectButton = tile.querySelector('.iq-media-select-button');
            if (selectButton) {
                selectButton.classList.remove('btn-outline-primary');
                selectButton.classList.add('btn-primary');
                selectButton.innerHTML = '<i class="ph ph-check-circle"></i> Selected';
            }

            FileManager.dom.updateSaveButtonState();
        },

        // Content rendering
        render: {
            mediaInfoHTML: (item, url, type) => {
                const size = FileManager.utils.formatFileSize(Number(item.size || 0));
                const date = FileManager.utils.formatUploadDate(item.uploaded_at || item.modified);
                const ext = FileManager.utils.getFileExtension(item.name);
                const status = item.status || 'ready';
                const isReady = status === 'ready';
                const isFailed = status === 'failed';
                const isProcessing = !isReady && !isFailed;
                const safeSize = FileManager.utils.escapeHtml(size);
                const safeDate = FileManager.utils.escapeHtml(date);
                const safeType = FileManager.utils.escapeHtml(type);
                const safeExt = FileManager.utils.escapeHtml(ext);
                const safeUrl = FileManager.utils.escapeHtml(url);
                const statusText = isFailed ? 'Failed' : (status === 'queued' ? 'Queued' : 'Processing');
                const safeStatus = FileManager.utils.escapeHtml(statusText);
                const jsUrl = FileManager.utils.escapeJsString(url);
                const statusLine = isReady ? '' : `<span title="${safeStatus}"><i class="ph ${isFailed ? 'ph-warning-circle' : 'ph-clock'} me-1"></i>${safeStatus}</span>`;
                const processingTrack = isProcessing ? `
                    <div class="ez-processing-track">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted">Video is compressing. Please wait before selecting it.</small>
                    </div>
                ` : '';
                const selectButton = isReady
                    ? `<button type="button" class="btn btn-sm btn-outline-primary iq-media-action iq-media-select-button" onclick="event.stopPropagation(); FileManager.selectTile(this.closest('.iq-media-images'))">
                            <i class="ph ph-check"></i> Select
                        </button>`
                    : `<button type="button" class="btn btn-sm btn-outline-secondary iq-media-action" disabled>
                            <i class="ph ${isFailed ? 'ph-warning-circle' : 'ph-clock'}"></i> ${isFailed ? safeStatus : 'Please wait'}
                        </button>`;
                const copyButton = isReady
                    ? `<button type="button" class="btn btn-sm btn-outline-primary iq-media-action" onclick="event.stopPropagation(); FileManager.copyUrl('${jsUrl}', this)">
                            <i class="ph ph-copy"></i> URL
                        </button>`
                    : `<button type="button" class="btn btn-sm btn-outline-secondary iq-media-action" disabled>
                            <i class="ph ph-link"></i> URL
                        </button>`;

                return `
                    <div class="ez-media-info text-muted">
                        <span title="${safeSize}"><i class="ph ph-hard-drives me-1"></i>${safeSize}</span>
                        <span title="${safeDate}"><i class="ph ph-calendar me-1"></i>${safeDate}</span>
                        <span title="${safeType} / ${safeExt}"><i class="ph ph-info me-1"></i>${safeType} / ${safeExt}</span>
                        ${statusLine}
                        <span class="ez-media-url" title="${safeUrl}"><i class="ph ph-link me-1"></i>${safeUrl}</span>
                    </div>
                    ${processingTrack}
                    <div class="ez-media-actions">
                        ${selectButton}
                        ${copyButton}
                    </div>
                `;
            },

            generateItemHTML: (item) => {
                const {
                    is_dir,
                    is_video,
                    is_image,
                    name,
                    media_url,
                    path,
                    size,
                    modified
                } = item;
                const status = item.status || 'ready';
                const isReady = status === 'ready';
                let displayName = (typeof name === 'string' && name.length > 0) ?
                    (name.charAt(0).toUpperCase() + name.slice(1)) :
                    name;
                const safeDisplayName = FileManager.utils.escapeHtml(displayName);
                const safeName = FileManager.utils.escapeHtml(name);
                const jsName = FileManager.utils.escapeJsString(name);
                const jsMediaUrl = FileManager.utils.escapeJsString(media_url);
                const safeMediaUrl = FileManager.utils.escapeHtml(media_url);
                const jsFolder = FileManager.utils.escapeJsString(FileManager.utils.getFolderFromUrl(media_url || ''));
                const jsPath = FileManager.utils.escapeJsString(path || '');
                const isFailed = status === 'failed';
                const statusText = isFailed ? 'Failed' : (status === 'queued' ? 'Queued' : 'Processing');
                const safeStatus = FileManager.utils.escapeHtml(statusText);

                if (is_dir) {
                    const transKey = 'folder_' + name.toLowerCase();
                    if (window.localMessagesUpdate?.messages?.[transKey] && window.localMessagesUpdate.messages[transKey] !== 'messages.' + transKey) {
                        displayName = window.localMessagesUpdate.messages[transKey];
                    }
                }

                if (is_dir) {
                    const safeFolderName = FileManager.utils.escapeHtml(displayName);
                    const jsPath = FileManager.utils.escapeJsString(path);
                    const folderDate = FileManager.utils.buildDateLine(modified);
                    return `
                        <div class="col-md-2 col-sm-1">
                            <div class="card h-100" onclick="FileManager.navigation.openSubFolder('${jsPath}')" style="cursor: pointer;">
                                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                                    <i class="ph ph-folder text-warning" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2 mb-1 text-truncate" title="${safeFolderName}">${safeFolderName}</h6>

                                    ${folderDate}
                                </div>
                            </div>
                        </div>
                    `;
                } else if (is_video) {
                    const videoPreview = isReady
                        ? `<video class="img-fluid object-fit-cover" preload="none" controlsList="nodownload" controls>
                                    <source src="${safeMediaUrl}" type="video/mp4">
                                </video>`
                        : `<div class="ratio ratio-16x9 bg-body-tertiary d-flex align-items-center justify-content-center text-muted">
                                    <div class="text-center"><i class="ph ${isFailed ? 'ph-warning-circle' : 'ph-clock'} fs-2 d-block"></i><span>${safeStatus}</span></div>
                                </div>`;
                    return `
                        <div class="col-md-2 col-sm-1">
                            <div class="iq-media-images position-relative" data-file-name="${safeName}" data-media-url="${safeMediaUrl}">
                                ${videoPreview}
                                <button type="button" class="btn btn-danger position-absolute top-0 end-0 m-2 py-1 px-2 iq-button-delete" onclick="FileManager.deleteFile('${jsName}', '${jsMediaUrl}', '${jsPath}', 'video', '${jsFolder}')">
                                    <i class="ph ph-trash"></i>
                                </button>
                                <p class="media-title pt-2 mb-0" data-bs-toggle="tooltip" data-bs-title="${safeName}">${safeName}</p>
                                ${FileManager.render.mediaInfoHTML(item, media_url, 'video')}
                            </div>
                        </div>
                    `;
                } else if (is_image) {
                    const imagePreview = isReady
                        ? `<img class="img-fluid object-fit-cover" src="${safeMediaUrl}" loading="lazy" decoding="async" onload="this.style.opacity=1">`
                        : `<div class="ratio ratio-16x9 bg-body-tertiary d-flex align-items-center justify-content-center text-muted">
                                    <div class="text-center"><i class="ph ${isFailed ? 'ph-warning-circle' : 'ph-clock'} fs-2 d-block"></i><span>${safeStatus}</span></div>
                                </div>`;
                    return `
                        <div class="col-md-2 col-sm-1">
                            <div class="iq-media-images position-relative" data-file-name="${safeName}" data-media-url="${safeMediaUrl}">
                                ${imagePreview}
                                <button type="button" class="btn btn-danger position-absolute top-0 end-0 m-2 py-1 px-2 iq-button-delete" onclick="FileManager.deleteFile('${jsName}', '${jsMediaUrl}', '${jsPath}', 'image', '${jsFolder}')">
                                    <i class="ph ph-trash"></i>
                                </button>
                                <p class="media-title pt-2 mb-0" data-bs-toggle="tooltip" data-bs-title="${safeName}">${safeName}</p>
                                ${FileManager.render.mediaInfoHTML(item, media_url, 'image')}
                            </div>
                        </div>
                    `;
                } else {
                    const iconClass = FileManager.utils.getFileIcon(name);
                    const iconColor = FileManager.utils.getFileColor(name);
                    const fileSize = FileManager.utils.formatFileSize(size);
                    const fileUrl = `${FileManager.config.baseUrl}/storage/app/public/${path}`;
                    const safeFileUrl = FileManager.utils.escapeHtml(fileUrl);
                    const jsFileUrl = FileManager.utils.escapeJsString(fileUrl);

                    return `
                        <div class="col-md-2 col-sm-1">
                            <div class="card h-100 position-relative">
                                <div class="card-body text-center">
                                    <i class="ph ${iconClass} ${iconColor}" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2 mb-1 text-truncate" title="${safeDisplayName}">${safeDisplayName}</h6>
                                    <small class="text-muted">${FileManager.utils.escapeHtml(fileSize)}</small>
                                    <div class="mt-2">
                                        <small class="text-muted">${FileManager.utils.formatUploadDate(modified)}</small>
                                    </div>
                                    <div class="ez-media-actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary iq-media-action" onclick="event.stopPropagation(); FileManager.copyUrl('${jsFileUrl}', this)" title="${safeFileUrl}">
                                            <i class="ph ph-copy"></i> URL
                                        </button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-danger position-absolute top-0 end-0 m-2 py-1 px-2 iq-button-delete" onclick="FileManager.deleteFile('${jsName}', '${jsFileUrl}', '${jsPath}', 'file', '${FileManager.utils.escapeJsString(FileManager.utils.getFolderFromUrl(fileUrl))}')">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }
            },

            displayContents: (contents, append = false) => {
                let html = '';
                let hasSelectable = false;
                let hasMediaFiles = false;
                let hasProcessing = false;

                if (contents.length === 0) {
                    html =
                        '<div class="text-center text-muted">{{ __('frontend.no_files_found_in_folder') }}</div>';
                } else {
                    contents.forEach(item => {
                        html += FileManager.render.generateItemHTML(item);
                        const status = String(item.status || 'ready').toLowerCase();
                        if ((item.is_video || item.is_image) && ['queued', 'processing', 'pending'].includes(status)) {
                            hasProcessing = true;
                            hasMediaFiles = true;
                        }
                        if ((item.is_video || item.is_image) && (!item.status || item.status === 'ready')) {
                            hasSelectable = true;
                            hasMediaFiles = true;
                        }
                    });

                    if (!append && hasProcessing) {
                        html = `
                            <div class="col-12">
                                <div class="ez-media-processing-notice text-warning bg-warning bg-opacity-10">
                                    <i class="ph ph-clock me-1"></i>
                                    Video upload received. Compression is running; please wait here or refresh Media Library until the video becomes selectable.
                                </div>
                            </div>
                        ` + html;
                    }
                }

                // Update UI with smooth transition
                const container = document.getElementById('mediaLibraryContent');
                if (append) {
                    // For infinite scroll, add content smoothly
                    container.insertAdjacentHTML('beforeend', html);
                    // Add fade-in effect to new content
                    const newItems = container.querySelectorAll('.col-md-2:not(.fade-in)');
                    newItems.forEach((item, index) => {
                        item.style.opacity = '0';
                        item.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => {
                            item.style.opacity = '1';
                            item.classList.add('fade-in');
                        }, index * 50); // Stagger the animation
                    });
                } else {
                    // For initial load, use smooth transition
                    container.style.opacity = '0';
                    container.innerHTML = html;
                    setTimeout(() => {
                        container.style.opacity = '1';
                    }, 100);
                }

                FileManager.dom.toggleSaveButton(hasSelectable);

                // Show search bar only if there are media files OR if there's an active search
                const searchInput = document.getElementById('mediaSearchInput');
                const hasActiveSearch = searchInput && searchInput.value.trim() !== '';
                FileManager.dom.toggleSearchBar(hasMediaFiles || hasActiveSearch);

                // Scroll initialization is now handled in loadFolderContents after loader is hidden
                // This prevents premature scroll setup during content rendering

                // Disconnect observer if no more data
                if (FileManager.state.nextOffset === null && FileManager.state.ioObserver) {
                    FileManager.state.ioObserver.disconnect();
                    FileManager.state.ioObserver = null;
                }
                // After rendering, ensure save button reflects current selection (likely none)
                FileManager.dom.updateSaveButtonState();
                FileManager.applyPendingUploadedSelection();
                FileManager.scheduleProcessingRefresh();
            }
        },

        applyPendingUploadedSelection: () => {
            const fileName = FileManager.state.pendingSelectFile;
            if (!fileName) return;

            const escaped = (window.CSS && typeof CSS.escape === 'function')
                ? CSS.escape(fileName)
                : fileName.replace(/"/g, '\\"');

            const tile = document.querySelector(`#mediaLibraryContent .iq-media-images[data-file-name="${escaped}"]`);
            if (!tile) {
                if (!FileManager.state.isLoading && FileManager.state.nextOffset !== null && FileManager.state.nextOffset !== undefined) {
                    FileManager.loadMoreContents();
                }
                return;
            }

            tile.classList.add('selected');
            tile.scrollIntoView({ behavior: 'smooth', block: 'center' });
            FileManager.dom.updateSaveButtonState();

            FileManager.state.pendingSelectFile = null;

            if (window.history && window.history.replaceState) {
                const params = new URLSearchParams(window.location.search);
                params.delete('open_folder');
                params.delete('select_file');
                const query = params.toString();
                const cleanUrl = `${window.location.pathname}${query ? `?${query}` : ''}`;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        },

        scheduleProcessingRefresh: () => {
            if (FileManager.state.processingRefreshTimer) {
                clearTimeout(FileManager.state.processingRefreshTimer);
                FileManager.state.processingRefreshTimer = null;
            }

            const hasProcessing = (FileManager.state.originalContents || []).some(function (item) {
                const status = String(item.status || 'ready').toLowerCase();
                return !item.is_dir && ['queued', 'processing', 'pending'].includes(status);
            });

            if (!hasProcessing || !FileManager.state.currentFolder) {
                return;
            }

            FileManager.state.processingRefreshTimer = setTimeout(function () {
                FileManager.state.processingRefreshTimer = null;
                FileManager.loadFolderContents(FileManager.state.currentFolder, { silent: true });
            }, 5000);
        },

        // Core functions
        loadFolderContents: async (folderName, options = {}) => {
            if (!options.silent) {
                FileManager.dom.showLoading();
            }
            // Ensure sort state is in sync with global folder sort (set by folder-browser)
            FileManager.state.sort = FileManager.state.sort || window.fbFolderSort || 'modified_desc';

            // Abort previous request
            if (FileManager.state.abortController) {
                try {
                    FileManager.state.abortController.abort();
                } catch (e) {}
            }

            FileManager.state.abortController = new AbortController();
            FileManager.state.isLoading = true;

            try {
                const data = await FileManager.api.fetchFolderContents(folderName, 0);

                if (data.success) {
                    FileManager.state.nextOffset = data.pagination?.next_offset || null;
                    FileManager.state.originalContents = data.contents;
                    FileManager.render.displayContents(data.contents, false);


                    // Initialize scroll after content is loaded and loader is hidden
                    setTimeout(() => {
                        if (!FileManager.state.infiniteInitDone &&
                            FileManager.state.nextOffset !== null &&
                            FileManager.state.originalContents.length > 0) {
                            FileManager.initInfiniteScroll();
                        } else {}
                    }, 300);
                } else {
                    FileManager.dom.showError('{{ __('frontend.error_loading_folder_contents') }}');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    FileManager.dom.showError('{{ __('frontend.error_loading_folder_contents') }}');
                }
            } finally {
                if (!options.silent) {
                    FileManager.dom.hideLoading();
                }
                FileManager.state.isLoading = false;
            }
        },

        loadMoreContents: async () => {
            if (FileManager.state.isLoading || FileManager.state.nextOffset === null || FileManager.state
                .nextOffset === undefined || !FileManager.state.currentFolder) {
                return;
            }


            // Show smooth infinite scroll loader
            FileManager.dom.showInfiniteScrollLoader(true);
            FileManager.state.isLoading = true;

            try {
                const data = await FileManager.api.fetchFolderContents(FileManager.state.currentFolder,
                    FileManager.state.nextOffset);

                if (data && data.success) {
                    FileManager.state.nextOffset = data.pagination?.next_offset || null;
                    FileManager.state.originalContents = FileManager.state.originalContents.concat(data
                        .contents || []);
                    FileManager.render.displayContents(data.contents || [], true);


                    // If no more data, disconnect observer
                    if (FileManager.state.nextOffset === null && FileManager.state.ioObserver) {
                        FileManager.state.ioObserver.disconnect();
                        FileManager.state.ioObserver = null;
                    }
                }
            } catch (error) {} finally {
                // Hide smooth infinite scroll loader
                FileManager.dom.showInfiniteScrollLoader(false);
                FileManager.state.isLoading = false;
            }
        },

        deleteFile: (fileName, url, pathOrType = null, typeOrFolder = null, maybeFolderName = null) => {
            if (typeof fileName === 'string' && /^https?:\/\//i.test(fileName)) {
                const originalUrl = fileName;
                const originalType = url;
                const originalFileName = pathOrType;
                const originalFolderName = typeOrFolder;
                const originalPath = maybeFolderName;
                fileName = originalFileName || '';
                url = originalUrl;
                pathOrType = originalPath || null;
                typeOrFolder = originalType || (url && url.match(/\.(jpe?g|png|gif|webp|svg)(\?|$)/i) ? 'image' : 'video');
                maybeFolderName = originalFolderName || FileManager.utils.getFolderFromUrl(url);
            }

            const path = pathOrType && !['video', 'image', 'file'].includes(pathOrType) ? pathOrType : null;
            const type = ['video', 'image', 'file'].includes(pathOrType) ? pathOrType : typeOrFolder;
            const folderName = maybeFolderName || (pathOrType && ['video', 'image', 'file'].includes(pathOrType) ? typeOrFolder : null);

            const i18n = {
                delete_confirm_title: @json(__('frontend.delete_confirm_title')),
                delete_confirm_text: @json(__('frontend.delete_confirm_text')),
                delete_confirm_ok: @json(__('frontend.delete_confirm_ok')),
                deleted_title: @json(__('frontend.deleted_title')),
                deleted_text: @json(__('frontend.deleted_text')),
                delete_error_title: @json(__('frontend.delete_error_title')),
                delete_error_text: @json(__('frontend.delete_error_text')),
                from_folder_suffix: @json(__('frontend.from_folder_suffix')),
                type_video: @json(__('frontend.video')),
                type_image: @json(__('frontend.image')),
                type_file: @json(__('frontend.file')),
                cancel: @json(__('frontend.cancel')),
                close: @json(__('frontend.close')),
            };

            const fileTypeText = type === 'video' ? i18n.type_video : type === 'image' ? i18n.type_image : i18n
                .type_file;
            const displayName = fileName || `this ${fileTypeText}`;
            const folderDisplay = folderName && folderName !== 'default' && folderName !== 'Folders' ?
                ' ' + i18n.from_folder_suffix.replace(':folder', folderName) : '';

            Swal.fire({
                title: i18n.delete_confirm_title.replace(':type', String(fileTypeText).toLowerCase()),
                text: i18n.delete_confirm_text
                    .replace(':name', displayName)
                    .replace(':folder', folderDisplay),
                icon: undefined,
                iconHtml: '<i class="ph ph-trash text-warning warning-icon" ></i>',
                customClass: {
                    icon: 'swal2-icon--custom'
                },
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                cancelButtonText: i18n.cancel,
                confirmButtonText: i18n.delete_confirm_ok,
                reverseButtons: true,
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const fileId = fileName.split('.')[0];

                    try {
                        const data = await FileManager.api.deleteFile(fileId, url, path);

                        if (data.success) {
                            const mediaContainer = document.querySelector(
                                `img[src="${url}"], video source[src="${url}"]`);
                            if (mediaContainer) {
                                mediaContainer.closest('.col-md-2').remove();
                            }

                            Swal.fire({
                                title: i18n.deleted_title,
                                text: i18n.deleted_text.replace(':type', String(
                                    fileTypeText).toLowerCase()).replace(':name',
                                    displayName).replace(':folder', folderName || ''),
                                icon: 'success',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                        } else {
                            Swal.fire(i18n.delete_error_title,
                                i18n.delete_error_text.replace(':type', String(fileTypeText)
                                    .toLowerCase()), 'error');
                        }
                    } catch (error) {
                        Swal.fire(i18n.delete_error_title, i18n.delete_error_text.replace(':type',
                            String(fileTypeText).toLowerCase()), 'error');
                    }
                }
            });
        },

        // Infinite scroll
        initInfiniteScroll: () => {
            // Double check to prevent multiple initialization
            if (FileManager.state.infiniteInitDone) {
                return;
            }

            // Ensure loading is complete before setting up scroll
            if (FileManager.state.isLoading) {
                setTimeout(() => {
                    FileManager.initInfiniteScroll();
                }, 100);
                return;
            }

            const scroller = document.querySelector('.media-scroll-container');
            if (!scroller) {
                return;
            }

            // Only initialize if we have more data to load
            if (FileManager.state.nextOffset === null || FileManager.state.nextOffset === undefined) {
                return;
            }

            // Only initialize if we have content
            if (FileManager.state.originalContents.length === 0) {
                return;
            }


            let sentinel = scroller.querySelector('.fm-bottom-sentinel');
            if (!sentinel) {
                sentinel = document.createElement('div');
                sentinel.className = 'fm-bottom-sentinel';
                sentinel.style.height = '1px';
                scroller.appendChild(sentinel);
            }

            // Disconnect any existing observer first
            if (FileManager.state.ioObserver) {
                try {
                    FileManager.state.ioObserver.disconnect();
                } catch (e) {}
                FileManager.state.ioObserver = null;
            }

            FileManager.state.ioObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !FileManager.state.isLoading &&
                        FileManager.state.nextOffset !== null && FileManager.state
                        .nextOffset !== undefined) {
                        FileManager.loadMoreContents();
                    }
                });
            }, {
                root: scroller,
                rootMargin: '120px',
                threshold: 0
            });

            FileManager.state.ioObserver.observe(sentinel);
            FileManager.state.infiniteInitDone = true;
        },

        cleanup: () => {
            if (FileManager.state.ioObserver) {
                try {
                    FileManager.state.ioObserver.disconnect();
                } catch (e) {}
                FileManager.state.ioObserver = null;
            }
            FileManager.state.infiniteInitDone = false;
        },

        // Search functionality
        search: {
            filterContent: () => {
                const searchTerm = document.getElementById('mediaSearchInput')?.value.toLowerCase().trim() ||
                    '';

                if (searchTerm === '') {
                    // Only render originals if the previous term wasn't already empty
                    if (FileManager.lastSearchTerm !== '') {
                        FileManager.render.displayContents(FileManager.state.originalContents);
                        FileManager.lastSearchTerm = '';
                    }
                    // Keep search bar visible; only hide clear icon
                    FileManager.dom.toggleSearchBar(true);
                    const clearBtn = document.querySelector('#search-bar-container .clear-search');
                    if (clearBtn) {
                        clearBtn.classList.add('d-none');
                    }
                    return;
                }

                const filteredContents = FileManager.state.originalContents.filter(item => {
                    const isImage = item.is_image;
                    const isVideo = item.is_video;
                    const fileName = item.name.toLowerCase();

                    return (isImage || isVideo) && fileName.includes(searchTerm);
                });

                // Show search bar when searching (even if no results)
                FileManager.dom.toggleSearchBar(true);
                FileManager.lastSearchTerm = searchTerm;
                FileManager.render.displayContents(filteredContents);
            },

            clear: () => {
                const searchInput = document.getElementById('mediaSearchInput');
                if (searchInput) {
                    searchInput.value = '';
                }
                // Keep search bar visible when clearing search
                FileManager.dom.toggleSearchBar(true);
                // Hide clear icon after clearing
                const clearBtn = document.querySelector('#search-bar-container .clear-search');
                if (clearBtn) {
                    clearBtn.classList.add('d-none');
                }
                FileManager.lastSearchTerm = '';
                FileManager.render.displayContents(FileManager.state.originalContents);
            }
        }
    };

    // Global function aliases for backward compatibility
    window.openFolder = FileManager.navigation.openFolder;
    window.goBackToFolders = FileManager.navigation.goBackToFolders;
    window.openSubFolder = FileManager.navigation.openSubFolder;
    window.loadFolderContents = FileManager.loadFolderContents;
    window.displayFolderContents = FileManager.render.displayContents;
    window.deleteImage = FileManager.deleteFile;
    window.filterMediaContent = FileManager.search.filterContent;
    window.clearSearch = FileManager.search.clear;
    window.FileManager = FileManager;

    // Add event delegation for folder cards (in case they're rendered before script loads)
    document.addEventListener('DOMContentLoaded', function() {
        // Use event delegation for folder cards
        const foldersGrid = document.getElementById('folders-grid');
        if (foldersGrid) {
            foldersGrid.addEventListener('click', function(e) {
                const folderCard = e.target.closest('.folder-card-clickable');
                if (folderCard) {
                    const folderName = folderCard.getAttribute('data-folder-name');
                    if (folderName && typeof window.openFolder === 'function') {
                        window.openFolder(folderName);
                    } else if (folderName && FileManager && FileManager.navigation) {
                        FileManager.navigation.openFolder(folderName);
                    }
                }
            });
        }
    });
    window.getFolderFromUrl = FileManager.utils.getFolderFromUrl;

    // File upload handling
    document.getElementById('file_url_media')?.addEventListener('change', function() {
        const fileInput = document.getElementById('file_url_media');
        const submitButton = document.getElementById('submitButton');
        const fileError = document.getElementById('file_url_media-error');

        if (fileInput && submitButton) {
            const hasFiles = fileInput.files.length > 0;

            if (hasFiles) {
                fileInput.removeAttribute('required');
                fileError.style.display = 'none';
                submitButton.removeAttribute('disabled');
            } else {
                fileInput.setAttribute('required', 'required');
                fileError.style.display = 'block';
                submitButton.setAttribute('disabled', 'disabled');
            }
        }
    });

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        FileManager.dom.toggleSaveButton(false);
        // Disable save by default until a media item is selected
        const btn = document.getElementById('mediaSubmitButton');
        if (btn) btn.disabled = true;

        if (FileManager.state.autoOpenFolder && !FileManager.state.autoOpenedFromQuery) {
            FileManager.state.autoOpenedFromQuery = true;
            FileManager.navigation.openFolder(FileManager.state.autoOpenFolder);
        }

        // Toggle selection on media tiles (ignore delete button clicks)
        const grid = document.getElementById('mediaLibraryContent');
        if (grid) {
            grid.addEventListener('click', function(e) {
                const deleteBtn = e.target.closest('.iq-button-delete');
                if (deleteBtn) return;
                const actionBtn = e.target.closest('.iq-media-action');
                if (actionBtn) return;
                const tile = e.target.closest('.iq-media-images');
                if (tile) {
                    FileManager.selectTile(tile);
                }
            });
        }
    });
</script>

<script>
    // Toggle clear search icon visibility based on input content
    (function() {
        const searchInput = document.getElementById('mediaSearchInput');
        const clearBtn = document.querySelector('#search-bar-container .clear-search');
        if (!searchInput || !clearBtn) return;

        // Initial state
        clearBtn.classList.toggle('d-none', !(searchInput.value || '').trim());

        // On typing, toggle visibility
        searchInput.addEventListener('input', function() {
            clearBtn.classList.toggle('d-none', !(this.value || '').trim());
        });
    })();
</script>
