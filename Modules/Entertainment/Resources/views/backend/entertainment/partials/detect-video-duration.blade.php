@once
    @push('after-scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const durationInput = document.getElementById('duration');
                const previewContainers = [
                    document.getElementById('selectedImageContainerVideourl'),
                    document.getElementById('selectedImageContainer4')
                ].filter(Boolean);
                const urlInput = document.getElementById('video_url_input');
                const fileInputs = [
                    document.getElementById('file_url_video'),
                    document.getElementById('file_url4')
                ].filter(Boolean);
                let probe = null;
                let probeTimer = null;
                let lastProbedUrl = '';

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
                    if (!url) return;

                    let sourceUrl;
                    try {
                        sourceUrl = new URL(url, window.location.origin).href;
                    } catch (error) {
                        return;
                    }

                    if (sourceUrl === lastProbedUrl) return;
                    lastProbedUrl = sourceUrl;

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
                    probe.addEventListener('error', function () {
                        lastProbedUrl = '';
                    }, { once: true });
                    probe.src = sourceUrl;
                }

                function scanPreview() {
                    previewContainers.forEach(function (container) {
                        container.querySelectorAll('video').forEach(bindVideo);
                    });
                    const selectedFileInput = fileInputs.find(function (input) { return input.value; });
                    const selectedUrl = selectedFileInput?.value || urlInput?.value;
                    if (selectedUrl) detectFromUrl(selectedUrl);
                }

                previewContainers.forEach(function (container) {
                    new MutationObserver(scanPreview).observe(container, { childList: true, subtree: true });
                });

                [urlInput].concat(fileInputs).forEach(function (input) {
                    ['input', 'change'].forEach(function (eventName) {
                        input?.addEventListener(eventName, function () {
                            lastProbedUrl = '';
                            window.clearTimeout(probeTimer);
                            probeTimer = window.setTimeout(scanPreview, 250);
                        });
                    });
                });

                scanPreview();
            });
        </script>
    @endpush
@endonce
