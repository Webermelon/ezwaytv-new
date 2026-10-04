@once
    @push('after-scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const durationInput = document.getElementById('duration');
                const previewContainer = document.getElementById('selectedImageContainer4');
                const urlInput = document.getElementById('video_url_input');
                const fileInput = document.getElementById('file_url4');
                let probe = null;
                let probeTimer = null;

                if (!durationInput) return;

                function setDuration(seconds) {
                    if (!Number.isFinite(seconds) || seconds <= 0) return;

                    const totalSeconds = Math.floor(seconds);
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const remainingSeconds = totalSeconds % 60;
                    durationInput.value = [hours, minutes, remainingSeconds]
                        .map(function (value) { return String(value).padStart(2, '0'); })
                        .join(':');
                    durationInput.dispatchEvent(new Event('change', { bubbles: true }));
                }

                function bindVideo(video) {
                    if (!video || video.dataset.durationDetectorBound === '1') return;

                    video.dataset.durationDetectorBound = '1';
                    video.addEventListener('loadedmetadata', function () {
                        setDuration(video.duration);
                    });
                    if (video.readyState >= 1) setDuration(video.duration);
                }

                function detectFromUrl(url) {
                    if (!url || !/^https?:\/\//i.test(url)) return;

                    if (probe) {
                        probe.removeAttribute('src');
                        probe.load();
                    }
                    probe = document.createElement('video');
                    probe.preload = 'metadata';
                    probe.muted = true;
                    probe.addEventListener('loadedmetadata', function () {
                        setDuration(probe.duration);
                        probe.removeAttribute('src');
                        probe.load();
                    }, { once: true });
                    probe.src = url;
                }

                function scanPreview() {
                    previewContainer?.querySelectorAll('video').forEach(bindVideo);
                    const selectedUrl = fileInput?.value || urlInput?.value;
                    if (selectedUrl) detectFromUrl(selectedUrl);
                }

                if (previewContainer) {
                    new MutationObserver(scanPreview).observe(previewContainer, { childList: true, subtree: true });
                }

                [urlInput, fileInput].forEach(function (input) {
                    input?.addEventListener('change', function () {
                        window.clearTimeout(probeTimer);
                        probeTimer = window.setTimeout(scanPreview, 150);
                    });
                });

                scanPreview();
            });
        </script>
    @endpush
@endonce
