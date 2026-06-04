@extends('frontend::layouts.master')

@section('title')
    Stream Your Music
@endsection

@push('after-styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('music-landing/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('music-landing/assets/css/page.css') }}">
    <style>
        .music-upload-page {
            min-height: 100vh;
            background: var(--ez-dark);
            color: var(--ez-white);
            font-family: "Poppins", sans-serif;
            padding: clamp(48px, 7vw, 96px) 0;
        }

        .music-upload-shell {
            background: linear-gradient(160deg, rgba(31, 28, 72, 0.7) 0%, rgba(16, 13, 36, 0.94) 100%);
            border: 1px solid rgba(245, 166, 35, 0.22);
            border-radius: 18px;
            padding: clamp(24px, 4vw, 42px);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.32);
        }

        .music-upload-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .music-upload-header .ez-section-label {
            width: fit-content;
            margin-right: auto;
            margin-left: auto;
            justify-content: center;
        }

        .music-upload-page .form-label {
            color: var(--ez-white);
            font-size: 13px;
            font-weight: 600;
        }

        .music-upload-page .form-control {
            color: var(--ez-white);
            background: rgba(6, 4, 16, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .music-upload-page .form-control:focus {
            border-color: var(--ez-golden-border);
            box-shadow: 0 0 0 0.2rem rgba(245, 166, 35, 0.18);
        }

        .locked-channel {
            border: 1px solid rgba(245, 166, 35, 0.22);
            border-radius: 12px;
            background: rgba(245, 166, 35, 0.08);
            padding: 16px;
        }

        .upload-preview {
            position: relative;
            display: none;
            margin-top: 12px;
            border: 1px solid rgba(245, 166, 35, 0.22);
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }

        .upload-preview.is-visible {
            display: block;
        }

        .upload-preview img,
        .upload-preview video {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: contain;
            display: block;
            background: #000;
        }

        .upload-preview-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            color: var(--ez-muted);
            font-size: 12px;
            background: rgba(6, 4, 16, 0.92);
        }

        .upload-preview-remove {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            color: var(--ez-white);
            background: rgba(6, 4, 16, 0.82);
            backdrop-filter: blur(8px);
        }

        .upload-status-panel {
            display: none;
            margin-top: 24px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            background: rgba(6, 4, 16, 0.58);
        }

        .upload-status-panel.is-visible {
            display: block;
        }

        .upload-status-row {
            display: grid;
            grid-template-columns: 160px 1fr 92px;
            gap: 12px;
            align-items: center;
            margin-bottom: 12px;
        }

        .upload-status-label {
            color: var(--ez-white);
            font-size: 13px;
            font-weight: 700;
        }

        .upload-status-track {
            height: 10px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
        }

        .upload-status-fill {
            width: 0%;
            height: 100%;
            border-radius: inherit;
            background: var(--ez-golden);
            transition: width 0.2s ease;
        }

        .upload-status-text {
            color: var(--ez-muted);
            font-size: 12px;
            text-align: right;
        }

        .upload-status-row.is-success .upload-status-fill {
            background: #22c55e;
        }

        .upload-status-row.is-success .upload-status-text {
            color: #22c55e;
            font-weight: 700;
        }

        .upload-message {
            margin-top: 12px;
            color: var(--ez-muted);
            font-size: 13px;
        }

        @media (max-width: 640px) {
            .upload-status-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .upload-status-text {
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    <div class="music-upload-page">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-9">
                    <div class="music-upload-header">
                        <div class="ez-section-label">
                            <i class="bi bi-cloud-arrow-up-fill"></i> STREAM YOUR MUSIC
                        </div>
                        <h1 class="ez-h-2 mb-2">
                            Upload Your <span class="ez-gold-text">Music Video</span>
                        </h1>
                        <p class="ez-text-xl mb-5">
                            Your submission will be stored in media storage for backend review, download, and scheduling.
                        </p>
                    </div>

                    @if (session('music_submission_success') || request('submitted'))
                        <div class="alert alert-success mb-4">
                            {{ session('music_submission_success') ?: 'Your music video was uploaded successfully. Our team will review it and schedule it for the channel.' }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger mb-4">
                            <strong>Please check your submission.</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('music.video-submissions.store') }}" method="POST" enctype="multipart/form-data" class="music-upload-shell">
                        @csrf
                        <input type="hidden" name="channel_slug" value="{{ $channel['slug'] }}">

                        <div class="locked-channel mb-4">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-lock-fill ez-gold-text"></i>
                                <strong>Locked Channel</strong>
                            </div>
                            <div class="ez-text-xl mb-1">{{ $channel['name'] }}</div>
                            <div class="ez-text-sm">{{ $channel['description'] }}</div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="music-title" class="form-label">Music Video Title</label>
                                <input id="music-title" type="text" name="title" value="{{ old('title') }}" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="artist-name" class="form-label">Artist Name</label>
                                <input id="artist-name" type="text" name="artist_name" value="{{ old('artist_name') }}" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="submitter-name" class="form-label">Your Name</label>
                                <input id="submitter-name" type="text" name="submitter_name" value="{{ old('submitter_name', $user?->name) }}" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="submitter-email" class="form-label">Email</label>
                                <input id="submitter-email" type="email" name="submitter_email" value="{{ old('submitter_email', $user?->email) }}" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="submitter-phone" class="form-label">Phone</label>
                                <input id="submitter-phone" type="text" name="submitter_phone" value="{{ old('submitter_phone') }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="poster" class="form-label">Music Video Poster</label>
                                <input id="poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp" class="form-control" required>
                                <div id="poster-preview" class="upload-preview">
                                    <button type="button" id="poster-preview-remove" class="upload-preview-remove" aria-label="Remove poster">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <img id="poster-preview-image" src="" alt="Poster preview">
                                    <div class="upload-preview-meta">
                                        <span id="poster-preview-name"></span>
                                        <span id="poster-preview-size"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="video" class="form-label">Video File</label>
                                <input id="video" type="file" name="video" accept="video/mp4,video/quicktime,video/webm" class="form-control" required>
                                <div id="video-preview" class="upload-preview">
                                    <button type="button" id="video-preview-remove" class="upload-preview-remove" aria-label="Remove video">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <video id="video-preview-player" controls preload="metadata"></video>
                                    <div class="upload-preview-meta">
                                        <span id="video-preview-name"></span>
                                        <span id="video-preview-size"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="purchase-reference" class="form-label">Payment Reference</label>
                                <input id="purchase-reference" type="text" name="purchase_reference" value="{{ old('purchase_reference') }}" class="form-control" placeholder="Required: order ID, receipt ID, or payment email" required>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <label class="d-flex align-items-start gap-2 mb-0">
                                    <input type="checkbox" name="purchase_confirmation" value="1" class="form-check-input mt-1" required>
                                    <span>I confirm this payment reference is valid for this channel submission.</span>
                                </label>
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea id="notes" name="notes" rows="4" class="form-control">{{ old('notes') }}</textarea>
                            </div>
                            <div class="col-12 d-flex justify-content-center">
                                <button type="submit" id="upload-submit-button" class="ez-btn-gold ez-btn-lg">
                                    <i class="bi bi-cloud-arrow-up-fill"></i>
                                    Upload for Review
                                </button>
                            </div>
                        </div>

                        <div id="upload-status-panel" class="upload-status-panel">
                            <div id="poster-status-row" class="upload-status-row">
                                <div class="upload-status-label">Poster Image</div>
                                <div class="upload-status-track">
                                    <div id="poster-status-fill" class="upload-status-fill"></div>
                                </div>
                                <div id="poster-status-text" class="upload-status-text">Waiting</div>
                            </div>
                            <div id="video-status-row" class="upload-status-row">
                                <div class="upload-status-label">Video File</div>
                                <div class="upload-status-track">
                                    <div id="video-status-fill" class="upload-status-fill"></div>
                                </div>
                                <div id="video-status-text" class="upload-status-text">Waiting</div>
                            </div>
                            <div id="upload-message" class="upload-message">Upload status will appear here.</div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after-scripts')
    <script>
        (() => {
            const formatSize = (bytes) => {
                if (!bytes) return "";
                const units = ["B", "KB", "MB", "GB"];
                let size = bytes;
                let unit = 0;

                while (size >= 1024 && unit < units.length - 1) {
                    size /= 1024;
                    unit++;
                }

                return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
            };

            let posterUrl = null;
            let videoUrl = null;

            const posterInput = document.getElementById("poster");
            const posterPreview = document.getElementById("poster-preview");
            const posterImage = document.getElementById("poster-preview-image");
            const posterName = document.getElementById("poster-preview-name");
            const posterSize = document.getElementById("poster-preview-size");
            const posterRemove = document.getElementById("poster-preview-remove");

            const videoInput = document.getElementById("video");
            const videoPreview = document.getElementById("video-preview");
            const videoPlayer = document.getElementById("video-preview-player");
            const videoName = document.getElementById("video-preview-name");
            const videoSize = document.getElementById("video-preview-size");
            const videoRemove = document.getElementById("video-preview-remove");
            const form = document.querySelector(".music-upload-shell");
            const submitButton = document.getElementById("upload-submit-button");
            const statusPanel = document.getElementById("upload-status-panel");
            const uploadMessage = document.getElementById("upload-message");
            const posterStatusRow = document.getElementById("poster-status-row");
            const videoStatusRow = document.getElementById("video-status-row");
            const posterStatusFill = document.getElementById("poster-status-fill");
            const videoStatusFill = document.getElementById("video-status-fill");
            const posterStatusText = document.getElementById("poster-status-text");
            const videoStatusText = document.getElementById("video-status-text");

            const setStatus = (type, percent, text, success = false) => {
                const row = type === "poster" ? posterStatusRow : videoStatusRow;
                const fill = type === "poster" ? posterStatusFill : videoStatusFill;
                const label = type === "poster" ? posterStatusText : videoStatusText;
                const cleanPercent = Math.max(0, Math.min(100, Math.round(percent)));

                if (fill) fill.style.width = `${cleanPercent}%`;
                if (label) label.textContent = text || `${cleanPercent}%`;
                row?.classList.toggle("is-success", success);
            };

            const setUploading = (isUploading) => {
                if (!submitButton) return;
                submitButton.disabled = isUploading;
                submitButton.style.opacity = isUploading ? "0.72" : "";
                submitButton.style.pointerEvents = isUploading ? "none" : "";
                submitButton.innerHTML = isUploading
                    ? '<i class="bi bi-cloud-arrow-up-fill"></i> Uploading...'
                    : '<i class="bi bi-cloud-arrow-up-fill"></i> Upload for Review';
            };

            const clearPoster = () => {
                if (posterUrl) URL.revokeObjectURL(posterUrl);
                posterUrl = null;
                if (posterInput) posterInput.value = "";
                posterPreview?.classList.remove("is-visible");
                posterImage?.removeAttribute("src");
                if (posterName) posterName.textContent = "";
                if (posterSize) posterSize.textContent = "";
                setStatus("poster", 0, "Waiting");
            };

            const clearVideo = () => {
                if (videoUrl) URL.revokeObjectURL(videoUrl);
                videoUrl = null;
                if (videoInput) videoInput.value = "";
                videoPreview?.classList.remove("is-visible");
                videoPlayer?.removeAttribute("src");
                videoPlayer?.load();
                if (videoName) videoName.textContent = "";
                if (videoSize) videoSize.textContent = "";
                setStatus("video", 0, "Waiting");
            };

            posterInput?.addEventListener("change", () => {
                const file = posterInput.files?.[0];
                if (posterUrl) URL.revokeObjectURL(posterUrl);

                if (!file) {
                    clearPoster();
                    return;
                }

                posterUrl = URL.createObjectURL(file);
                posterImage.src = posterUrl;
                posterName.textContent = file.name;
                posterSize.textContent = formatSize(file.size);
                posterPreview?.classList.add("is-visible");
            });

            videoInput?.addEventListener("change", () => {
                const file = videoInput.files?.[0];
                if (videoUrl) URL.revokeObjectURL(videoUrl);

                if (!file) {
                    clearVideo();
                    return;
                }

                videoUrl = URL.createObjectURL(file);
                videoPlayer.src = videoUrl;
                videoPlayer.load();
                videoName.textContent = file.name;
                videoSize.textContent = formatSize(file.size);
                videoPreview?.classList.add("is-visible");
            });

            posterRemove?.addEventListener("click", clearPoster);
            videoRemove?.addEventListener("click", clearVideo);

            form?.addEventListener("submit", (event) => {
                event.preventDefault();

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const posterFile = posterInput?.files?.[0];
                const videoFile = videoInput?.files?.[0];
                const posterBytes = posterFile?.size || 0;
                const videoBytes = videoFile?.size || 0;
                const fileBytes = posterBytes + videoBytes;

                statusPanel?.classList.add("is-visible");
                setStatus("poster", 0, "Starting");
                setStatus("video", 0, "Starting");
                if (uploadMessage) {
                    uploadMessage.textContent = "Uploading files to cloud media storage. Keep this page open until both items show success.";
                }
                setUploading(true);

                const xhr = new XMLHttpRequest();
                const formData = new FormData(form);

                xhr.open("POST", form.action);
                xhr.setRequestHeader("Accept", "application/json");
                xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

                xhr.upload.addEventListener("progress", (progressEvent) => {
                    if (!progressEvent.lengthComputable || !fileBytes) return;

                    const uploaded = Math.min(progressEvent.loaded, fileBytes);
                    const posterUploaded = Math.min(uploaded, posterBytes);
                    const videoUploaded = Math.max(0, Math.min(uploaded - posterBytes, videoBytes));

                    const posterPercent = posterBytes ? (posterUploaded / posterBytes) * 100 : 0;
                    const videoPercent = videoBytes ? (videoUploaded / videoBytes) * 100 : 0;

                    setStatus("poster", posterPercent, posterPercent >= 100 ? "Processing" : `${Math.round(posterPercent)}%`);
                    setStatus("video", videoPercent, videoPercent >= 100 ? "Processing" : `${Math.round(videoPercent)}%`);
                });

                xhr.addEventListener("load", () => {
                    let response = {};

                    try {
                        response = JSON.parse(xhr.responseText || "{}");
                    } catch (error) {
                        response = {};
                    }

                    if (xhr.status >= 200 && xhr.status < 300) {
                        setStatus("poster", 100, "✓ Success", true);
                        setStatus("video", 100, "✓ Success", true);
                        if (uploadMessage) {
                            uploadMessage.textContent = response.message || "Upload complete. Your submission has been received.";
                            uploadMessage.style.color = "#22c55e";
                        }
                        window.setTimeout(() => {
                            window.location.href = response.redirect_url || window.location.href;
                        }, 1400);
                        return;
                    }

                    const errors = response.errors
                        ? Object.values(response.errors).flat().join(" ")
                        : response.message || "Upload failed. Please check the form and try again.";

                    if (uploadMessage) {
                        uploadMessage.textContent = errors;
                        uploadMessage.style.color = "#ef4444";
                    }
                    setUploading(false);
                });

                xhr.addEventListener("error", () => {
                    if (uploadMessage) {
                        uploadMessage.textContent = "Network error during upload. Please try again.";
                        uploadMessage.style.color = "#ef4444";
                    }
                    setUploading(false);
                });

                xhr.send(formData);
            });
        })();
    </script>
@endpush
