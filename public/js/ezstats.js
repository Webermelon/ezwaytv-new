/**
 * EzwayTV Statistics Tracker
 * Include this script on frontend pages/player to track views and plays.
 *
 * Usage:
 *   EzStats.trackView({ content_type: 'video', content_id: 123 });
 *   EzStats.trackPlay({ content_type: 'video', content_id: 123 });
 *   EzStats.startWatchTime(playId, 30); // heartbeat every 30s
 */
(function (window) {
    'use strict';

    const BASE_URL = (window.location.origin || '') + '/api/statistics/';
    const SESSION_KEY = 'ezstats_session';

    function getSessionId() {
        let sid = sessionStorage.getItem(SESSION_KEY);
        if (!sid) {
            sid = 'sess_' + Math.random().toString(36).substr(2, 12) + '_' + Date.now();
            sessionStorage.setItem(SESSION_KEY, sid);
        }
        return sid;
    }

    function post(endpoint, data) {
        return fetch(BASE_URL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ session_id: getSessionId() }, data)),
        }).then(function (r) { return r.json(); }).catch(function () { return null; });
    }

    const EzStats = {
        /**
         * Track a page / content view.
         * @param {object} opts - { content_type, content_id, platform }
         */
        trackView: function (opts) {
            return post('track-view', Object.assign({
                page_url: window.location.href,
                referrer: document.referrer || '',
            }, opts));
        },

        /**
         * Track a play event.
         * @param {object} opts - { content_type, content_id, platform, quality }
         * @returns {Promise<{play_id: number}>}
         */
        trackPlay: function (opts) {
            return post('track-play', opts).then(function (res) {
                if (res && res.play_id) {
                    sessionStorage.setItem('ezstats_play_id', res.play_id);
                }
                return res;
            });
        },

        /**
         * Start watch-time heartbeat for a play event.
         * @param {number} playId      - returned by trackPlay
         * @param {number} intervalSec - how often to report (default 30)
         * @returns {function} stop - call to stop the heartbeat
         */
        startWatchTime: function (playId, intervalSec) {
            intervalSec = intervalSec || 30;
            let watchSeconds = 0;

            const timer = setInterval(function () {
                watchSeconds += intervalSec;
                post('update-watch-time', { play_id: playId, watch_seconds: watchSeconds });
            }, intervalSec * 1000);

            return function stop() { clearInterval(timer); };
        },

        /**
         * Convenience: attach to an HTML5 video/audio element.
         * Call after trackPlay resolves.
         *
         * @param {HTMLMediaElement} mediaEl
         * @param {object} contentInfo - { content_type, content_id }
         * @param {number} intervalSec
         */
        attachToPlayer: function (mediaEl, contentInfo, intervalSec) {
            let stopFn = null;
            let playId = null;

            mediaEl.addEventListener('play', function () {
                EzStats.trackPlay(contentInfo).then(function (res) {
                    if (res && res.play_id) {
                        playId = res.play_id;
                        stopFn = EzStats.startWatchTime(playId, intervalSec || 30);
                    }
                });
            });

            mediaEl.addEventListener('pause', function () {
                if (stopFn) { stopFn(); stopFn = null; }
            });

            mediaEl.addEventListener('ended', function () {
                if (stopFn) { stopFn(); stopFn = null; }
            });
        },
    };

    window.EzStats = EzStats;

}(window));
