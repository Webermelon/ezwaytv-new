import { useEffect } from 'react'

const INSTALLATION_KEY = 'ezway-tv-installation-id'
const INSTALLED_KEY = 'ezway-tv-installation-observed'
const OPENED_KEY = 'ezway-tv-session-opened'
const HEARTBEAT_SECONDS = 60

export function useAppAnalyticsTelemetry() {
  useEffect(() => {
    if (!isAuthenticated()) return

    const installationId = installationIdentifier()
    const platform = detectPlatform()
    let engagedSince = document.visibilityState === 'visible' ? Date.now() : null
    let queuedSeconds = 0

    const send = (event: 'install' | 'open' | 'heartbeat', engagedSeconds = 0) => {
      void fetch('/api/app-analytics/events', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: event === 'heartbeat',
        headers: analyticsHeaders(),
        body: JSON.stringify({
          event,
          installation_id: installationId,
          platform,
          ...(event === 'heartbeat' ? { engaged_seconds: engagedSeconds } : {}),
        }),
      }).catch(() => undefined)
    }

    if (!localStorage.getItem(INSTALLED_KEY)) {
      localStorage.setItem(INSTALLED_KEY, '1')
      send('install')
    }
    if (!sessionStorage.getItem(OPENED_KEY)) {
      sessionStorage.setItem(OPENED_KEY, '1')
      send('open')
    }

    const flush = () => {
      if (engagedSince !== null) {
        queuedSeconds += Math.floor((Date.now() - engagedSince) / 1000)
        engagedSince = Date.now()
      }
      if (queuedSeconds <= 0) return
      send('heartbeat', Math.min(HEARTBEAT_SECONDS * 2, queuedSeconds))
      queuedSeconds = 0
    }

    const onVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        engagedSince = Date.now()
        return
      }
      flush()
      engagedSince = null
    }

    const timer = window.setInterval(flush, HEARTBEAT_SECONDS * 1000)
    document.addEventListener('visibilitychange', onVisibilityChange)
    window.addEventListener('pagehide', flush)

    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', onVisibilityChange)
      window.removeEventListener('pagehide', flush)
      flush()
    }
  }, [])
}

function isAuthenticated() {
  return window.isAuthenticated !== false && Boolean(window.ezwayAuth?.id)
}

function installationIdentifier() {
  const existing = localStorage.getItem(INSTALLATION_KEY)
  if (existing) return existing

  const identifier = crypto.randomUUID()
  localStorage.setItem(INSTALLATION_KEY, identifier)
  return identifier
}

function detectPlatform(): 'android' | 'ios' | 'pwa' {
  const nativePlatform = window.Capacitor?.getPlatform?.().toLowerCase()
  if (nativePlatform === 'android') return 'android'
  if (nativePlatform === 'ios') return 'ios'
  return 'pwa'
}

function analyticsHeaders() {
  const headers: Record<string, string> = { 'Content-Type': 'application/json', Accept: 'application/json' }
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  if (token) headers['X-CSRF-TOKEN'] = token
  return headers
}
