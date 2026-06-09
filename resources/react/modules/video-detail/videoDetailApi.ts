import { api } from '@/lib/api'
import type { ApiEnvelope, MediaItem } from '@/modules/home/types'

export type VideoAd = {
  id?: number | string
  name?: string
  title?: string
  url?: string
  vast_url?: string
  redirect_url?: string
  image?: string
  image_url?: string
}

export async function loadVideoDetail(slug: string, ondemandChannel?: string | null) {
  const params = new URLSearchParams({ slug })

  if (ondemandChannel) {
    params.set('ondemand_channel', ondemandChannel)
  }

  const response = await api.get<ApiEnvelope<MediaItem>>(`/api/video-details?${params.toString()}`)

  return response.data
}

export async function loadVideoAds(videoId: string | number) {
  const vastParams = new URLSearchParams({
    type: 'video',
    content_id: String(videoId),
    video_type: 'full',
  })
  const customParams = new URLSearchParams({
    type: 'video',
    content_id: String(videoId),
  })

  const [vast, custom] = await Promise.allSettled([
    api.get<ApiEnvelope<VideoAd[]>>(`/api/vast-ads/get-active?${vastParams.toString()}`),
    api.get<ApiEnvelope<VideoAd[]>>(`/api/custom-ads/get-active?${customParams.toString()}`),
  ])

  return {
    vast: vast.status === 'fulfilled' ? vast.value.data ?? [] : [],
    custom: custom.status === 'fulfilled' ? custom.value.data ?? [] : [],
  }
}

export async function trackVideoView(video: MediaItem, channelId?: string | number | null) {
  return api.post('/api/statistics/track-view', {
    content_type: channelId ? 'ondemand_video' : 'video',
    content_id: Number(video.id),
    channel_id: channelId ? Number(channelId) : undefined,
    page_url: window.location.href,
    page_name: video.name,
    route_name: 'video-details',
    platform: 'web',
    session_id: getStatSessionId(),
  })
}

export async function trackVideoPlay(video: MediaItem, channelId?: string | number | null) {
  return api.post<{ status?: string; play_id?: number }>('/api/statistics/track-play', {
    content_type: channelId ? 'ondemand_video' : 'video',
    content_id: Number(video.id),
    channel_id: channelId ? Number(channelId) : undefined,
    platform: 'web',
    quality: 'auto',
    session_id: getStatSessionId(),
  })
}

export async function updateWatchTime(playId: number, watchSeconds: number) {
  return api.post('/api/statistics/update-watch-time', {
    play_id: playId,
    watch_seconds: Math.max(1, Math.round(watchSeconds)),
  })
}

function getStatSessionId() {
  const key = 'ezway_stat_session_id'
  const existing = window.sessionStorage.getItem(key)

  if (existing) return existing

  const next = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`
  window.sessionStorage.setItem(key, next)

  return next
}
