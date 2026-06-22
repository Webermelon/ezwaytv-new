import { api } from '@/lib/api'
import { trackPlay, trackView, updateWatchTime as updateAnalyticsWatchTime } from '@/lib/analytics'
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
  enable_skip?: boolean | number | string
  skip_after?: string | number | null
}

export type ContentStats = {
  real_plays?: number
  real_views?: number
  boost_plays?: number
  boost_views?: number
  display_plays?: number
  display_views?: number
  total_plays?: number
  total_views?: number
  views_display_mode?: 'combined' | 'real' | 'boosted' | 'hidden'
  plays_display_mode?: 'combined' | 'real' | 'boosted' | 'hidden'
  show_views_frontend?: boolean
  show_plays_frontend?: boolean
}

export async function loadVideoDetail(slug: string, ondemandChannel?: string | null) {
  const params = new URLSearchParams({ slug })

  if (ondemandChannel) {
    params.set('ondemand_channel', ondemandChannel)
  }

  const response = await api.get<ApiEnvelope<MediaItem>>(`/api/video-details?${params.toString()}`)

  return response.data
}

export async function loadContentStats(contentType: string, contentId: string | number) {
  const params = new URLSearchParams({
    content_type: contentType,
    content_id: String(contentId),
  })

  return api.get<ContentStats>(`/api/statistics/content-stats?${params.toString()}`)
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
  return trackView({
    content_type: channelId ? 'ondemand_video' : 'video',
    content_id: video.id,
    channel_id: channelId,
    page_name: video.name,
    route_name: 'video-details',
  })
}

export async function trackVideoPlay(video: MediaItem, channelId?: string | number | null) {
  return trackPlay({
    content_type: channelId ? 'ondemand_video' : 'video',
    content_id: video.id,
    channel_id: channelId,
    quality: 'auto',
  })
}

export async function updateWatchTime(playId: number, watchSeconds: number) {
  return updateAnalyticsWatchTime(playId, watchSeconds)
}
