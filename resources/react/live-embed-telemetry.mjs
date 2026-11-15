import { observePlayback } from './lib/playback-telemetry.mjs'

const video = document.querySelector('#live-player[data-channel-id]')
const channelId = Number(video?.dataset.channelId)

if (video && Number.isSafeInteger(channelId) && channelId > 0
    && typeof window.crypto?.randomUUID === 'function') {
  const listeners = new Map()
  let fatalHlsCode = null
  const player = {
    currentTime: () => video.currentTime,
    playbackRate: () => video.playbackRate,
    paused: () => video.paused,
    seeking: () => video.seeking,
    readyState: () => video.readyState,
    error: () => video.error ?? (fatalHlsCode ? { code: fatalHlsCode } : null),
    on(event, listener) {
      listeners.set(event, listener)
      video.addEventListener(event, listener)
    },
    off(event, listener) {
      listeners.delete(event)
      video.removeEventListener(event, listener)
    },
  }

  observePlayback({
    player,
    live: true,
    blocked: () => false,
    metadata: { content_type: 'livetv', content_id: channelId, player_surface: 'embed' },
  })
  video.addEventListener('ezway:hls-error', (event) => {
    fatalHlsCode = event.detail
    listeners.get('error')?.()
    fatalHlsCode = null
  })
}
