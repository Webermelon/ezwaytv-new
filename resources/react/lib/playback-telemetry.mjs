export function createPlaybackTracker({ live, now, record }) {
  let requestedAt = null
  let started = false
  let completed = false
  let playing = false
  let waitingAt = null
  let sample = null
  let watched = 0
  let lastError = null

  const measure = (position, rate) => {
    const at = now()
    if (playing && sample && Number.isFinite(position) && rate > 0) {
      const elapsed = at - sample.at
      const advanced = (position - sample.position) * 1000 / rate
      // Seek and live-edge jumps usually overrun the elapsed wall clock.
      if (advanced > 0 && advanced <= elapsed * 1.5 + 250) {
        watched += Math.min(advanced, elapsed)
      }
    }
    sample = Number.isFinite(position) ? { at, position } : null
  }

  const flush = () => {
    const amount = Math.min(60000, Math.floor(watched))
    if (amount > 0) {
      record('progress', { watch_delta_ms: amount })
      watched -= amount
    }
  }

  const closeBuffer = () => {
    if (waitingAt === null) return
    const duration = Math.min(600000, Math.round(now() - waitingAt))
    waitingAt = null
    if (duration > 0) record('buffering', { buffer_delta_ms: duration })
  }

  return {
    play() {
      if (requestedAt === null) requestedAt = now()
    },
    playing(position) {
      if (playing) return
      closeBuffer()
      if (!started) {
        started = true
        const startup = requestedAt === null ? null : Math.min(600000, Math.round(now() - requestedAt))
        record('started', startup === null ? {} : { startup_ms: startup })
      }
      lastError = null
      playing = true
      measure(position, 1)
    },
    sample(position, rate) {
      if (playing) measure(position, rate)
    },
    waiting(position, rate) {
      if (!playing) return
      measure(position, rate)
      waitingAt = now()
      playing = false
      sample = null
    },
    suspend(position, rate) {
      measure(position, rate)
      playing = false
      sample = null
      closeBuffer()
      flush()
    },
    seek() {
      sample = null
      playing = false
      closeBuffer()
    },
    flush,
    ended(position, rate) {
      this.suspend(position, rate)
      if (started && !live && !completed) {
        completed = true
        record('completed', {})
      }
    },
    error(code, position, rate) {
      this.suspend(position, rate)
      if (lastError !== null) return
      lastError = code
      record('error', { error_code: code })
    },
  }
}

export function observePlayback({ player, metadata, blocked, live }) {
  const playbackId = window.crypto.randomUUID().replaceAll('-', '')
  const queued = []
  let sequence = 0
  let sending = false
  let disposed = false
  const tracker = createPlaybackTracker({
    live,
    now: () => performance.now(),
    record(event, fields) {
      queued.push({ ...fields, event, sequence_no: ++sequence })
      if (queued.length >= 10) send()
    },
  })

  function send(final = false) {
    if (!queued.length || (sending && !final)) return
    const events = queued.splice(0, 20)
    const body = JSON.stringify({ ...metadata, playback_id: playbackId, events })
    if (final && navigator.sendBeacon?.('/api/statistics/playback-telemetry', new Blob([body], { type: 'application/json' }))) return
    if (!final) sending = true
    fetch('/api/statistics/playback-telemetry', {
      method: 'POST', credentials: 'same-origin', keepalive: true,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body,
    }).then((response) => {
      if (response.status === 429 || response.status >= 500) throw new Error('Telemetry temporarily unavailable')
    }).catch(() => {
      if (!disposed && queued.length < 40) queued.unshift(...events)
    }).finally(() => {
      if (!final) sending = false
    })
  }

  const sample = () => [player.currentTime(), player.playbackRate()]
  const handlers = {
    play: () => tracker.play(),
    playing: () => tracker.playing(player.currentTime()),
    timeupdate: () => tracker.sample(...sample()),
    waiting: () => { if (!player.seeking()) tracker.waiting(...sample()) },
    pause: () => tracker.suspend(...sample()),
    seeking: () => tracker.seek(),
    seeked: () => { if (!player.paused() && player.readyState() >= 3) tracker.playing(player.currentTime()) },
    ended: () => tracker.ended(...sample()),
    error: () => {
      const code = player.error()?.code
      if (code) tracker.error('media_' + code, ...sample())
    },
    adstart: () => tracker.suspend(Number.NaN, 1),
  }

  const listeners = Object.entries(handlers).map(([event, handler]) => {
    const listener = () => {
      if (event === 'adstart' || !blocked()) handler()
      else tracker.suspend(Number.NaN, 1)
    }
    player.on(event, listener)
    return [event, listener]
  })

  const flush = (final = false) => {
    if (!blocked() && !player.paused() && !player.seeking()) tracker.sample(...sample())
    tracker.flush()
    send(final)
  }
  const timer = window.setInterval(flush, 12000)
  const pagehide = () => { flush(true) }
  window.addEventListener('pagehide', pagehide)

  return () => {
    disposed = true
    window.removeEventListener('pagehide', pagehide)
    window.clearInterval(timer)
    for (const [event, listener] of listeners) player.off(event, listener)
    if (!blocked()) tracker.suspend(...sample())
    else tracker.suspend(Number.NaN, 1)
    while (queued.length) send(true)
  }
}
