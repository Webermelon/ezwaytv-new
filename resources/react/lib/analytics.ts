import { useEffect } from 'react'

import { api } from '@/lib/api'

type AnalyticsContentType = 'page' | 'video' | 'movie' | 'tvshow' | 'entertainment' | 'episode' | 'livetv' | 'ondemand_channel' | 'ondemand_video'

type TrackViewPayload = {
  content_type?: AnalyticsContentType
  content_id?: number | string | null
  channel_id?: number | string | null
  page_name?: string | null
  route_name?: string | null
  page_url?: string
  referrer?: string | null
}

type TrackPlayPayload = {
  content_type: Exclude<AnalyticsContentType, 'page'>
  content_id: number | string
  channel_id?: number | string | null
  quality?: string | null
}

let lastPageViewKey = ''
let lastReferrer = typeof document !== 'undefined' ? document.referrer : ''

export function useAnalyticsPageView(path: string) {
  useEffect(() => {
    const metadata = routeAnalyticsMetadata(path)
    const pageUrl = window.location.href
    const key = `${metadata.route_name}:${pageUrl}`

    if (lastPageViewKey === key) return

    const referrer = lastReferrer || document.referrer || null
    lastPageViewKey = key

    trackView({
      content_type: 'page',
      page_name: metadata.page_name,
      route_name: metadata.route_name,
      page_url: pageUrl,
      referrer,
    }).catch(() => undefined)

    lastReferrer = pageUrl
  }, [path])
}

export async function trackView(payload: TrackViewPayload) {
  return api.post('/api/statistics/track-view', cleanPayload({
    ...payload,
    content_id: numericOrUndefined(payload.content_id),
    channel_id: numericOrUndefined(payload.channel_id),
    page_url: payload.page_url ?? window.location.href,
    referrer: payload.referrer ?? document.referrer ?? undefined,
    platform: 'web',
    session_id: getStatSessionId(),
  }))
}

export async function trackPlay(payload: TrackPlayPayload) {
  return api.post<{ status?: string; play_id?: number }>('/api/statistics/track-play', cleanPayload({
    ...payload,
    content_id: Number(payload.content_id),
    channel_id: numericOrUndefined(payload.channel_id),
    platform: 'web',
    quality: payload.quality ?? 'auto',
    session_id: getStatSessionId(),
  }))
}

export async function updateWatchTime(playId: number, watchSeconds: number) {
  return api.post('/api/statistics/update-watch-time', {
    play_id: playId,
    watch_seconds: Math.max(1, Math.round(watchSeconds)),
  })
}

export function getStatSessionId() {
  const key = 'ezway_stat_session_id'
  const existing = window.sessionStorage.getItem(key)

  if (existing) return existing

  const next = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`
  window.sessionStorage.setItem(key, next)

  return next
}

function routeAnalyticsMetadata(path: string) {
  const pathname = path.split('?')[0].replace(/\/+$/, '') || '/'

  if (pathname === '/') return { route_name: 'home', page_name: 'Home' }
  if (pathname === '/on-demand') return { route_name: 'ondemand.index', page_name: 'On Demand Channels' }
  if (pathname.startsWith('/on-demand/')) return { route_name: 'ondemand.show', page_name: `On Demand: ${lastSegment(pathname)}` }
  if (pathname === '/livetv') return { route_name: 'livetv.index', page_name: 'Live TV' }
  if (pathname.startsWith('/livetv/')) return { route_name: 'livetv.show', page_name: `Live TV: ${lastSegment(pathname)}` }
  if (pathname === '/videos') return { route_name: 'videos.index', page_name: 'Videos' }
  if (pathname.startsWith('/videos/category/')) return { route_name: 'videos.category', page_name: `Video Category: ${lastSegment(pathname)}` }
  if (pathname.startsWith('/video-details/')) return { route_name: 'video-details', page_name: `Video: ${lastSegment(pathname)}` }
  if (pathname === '/movies') return { route_name: 'movies.index', page_name: 'Movies' }
  if (pathname.startsWith('/movie-details/')) return { route_name: 'movie-details', page_name: `Movie: ${lastSegment(pathname)}` }
  if (pathname === '/tv-shows') return { route_name: 'tvshows.index', page_name: 'TV Shows' }
  if (pathname.startsWith('/tvshow-details/')) return { route_name: 'tvshow-details', page_name: `TV Show: ${lastSegment(pathname)}` }
  if (pathname.startsWith('/episode-details/')) return { route_name: 'episode-details', page_name: `Episode: ${lastSegment(pathname)}` }
  if (pathname === '/castcrew-list') return { route_name: 'castcrew.index', page_name: 'Cast & Crew' }
  if (pathname.startsWith('/castcrew-detail/')) return { route_name: 'castcrew.show', page_name: `Cast/Crew: ${lastSegment(pathname)}` }
  if (pathname === '/search') return { route_name: 'search', page_name: 'Search' }
  if (pathname === '/distribution') return { route_name: 'distribution', page_name: 'Distribution' }
  if (pathname === '/faq') return { route_name: 'faq', page_name: 'FAQ' }
  if (pathname.startsWith('/pay-per-view')) return { route_name: 'pay-per-view', page_name: 'Pay Per View' }

  return { route_name: pathname.replace(/^\//, '').replace(/\//g, '.') || 'page', page_name: titleize(lastSegment(pathname) || 'Page') }
}

function lastSegment(pathname: string) {
  return decodeURIComponent(pathname.split('/').filter(Boolean).pop() ?? '')
}

function titleize(value: string) {
  return value.replace(/[-_]+/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

function numericOrUndefined(value?: number | string | null) {
  if (value === null || value === undefined || value === '') return undefined

  const numeric = Number(value)

  return Number.isFinite(numeric) && numeric > 0 ? numeric : undefined
}

function cleanPayload<T extends Record<string, unknown>>(payload: T) {
  return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== ''))
}
