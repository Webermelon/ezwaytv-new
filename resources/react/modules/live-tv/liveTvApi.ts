import { api } from '@/lib/api'
import { trackPlay, trackView, updateWatchTime as updateAnalyticsWatchTime } from '@/lib/analytics'
import type { ApiEnvelope, LiveTvDashboard, MediaItem } from '@/modules/home/types'
import type { VideoAd } from '@/modules/video-detail/videoDetailApi'

export async function loadLiveTvDashboard() {
  const response = await api.get<ApiEnvelope<LiveTvDashboard>>('/api/v3/livetv-dashboard')

  return response.data ?? {}
}

export async function loadLiveTvDetail(channelId: number | string) {
  const params = new URLSearchParams({ channel_id: String(channelId) })
  const response = await api.get<ApiEnvelope<MediaItem>>(`/api/v3/livetv-details?${params.toString()}`)

  return response.data
}

export type LiveTvChatMessage = {
  id: number | string
  guest_name?: string | null
  message: string
  time?: string | null
  created_at?: string | null
}

export type LiveTvChatState = {
  enabled: boolean
  guest_name?: string | null
  identity_type?: string | null
  can_change_name?: boolean
  messages: LiveTvChatMessage[]
}

export type LiveTvScheduleItem = {
  id?: number | string
  title?: string | null
  start_at?: string | null
  end_at?: string | null
  start_time?: string | null
  end_time?: string | null
  duration_seconds?: number | null
  meta?: Record<string, unknown> | null
}

export type LiveTvGuideChannel = {
  channel: MediaItem
  schedule: LiveTvScheduleItem[]
}

export async function loadLiveTvAds(channelId: string | number) {
  const vastParams = new URLSearchParams({
    type: 'livetv',
    content_id: String(channelId),
    video_type: 'full',
  })
  const customParams = new URLSearchParams({
    type: 'livetv',
    content_id: String(channelId),
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

export async function trackLiveTvView(channel: MediaItem) {
  return trackView({
    content_type: 'livetv',
    content_id: channel.id,
    page_name: channel.details?.name ?? channel.name,
    route_name: 'livetv',
  })
}

export async function trackLiveTvPlay(channel: MediaItem) {
  return trackPlay({
    content_type: 'livetv',
    content_id: channel.id,
    quality: 'auto',
  })
}

export async function updateLiveTvWatchTime(playId: number, watchSeconds: number) {
  return updateAnalyticsWatchTime(playId, watchSeconds)
}

export async function loadLiveTvChat(channelId: string | number) {
  return api.get<LiveTvChatState>(`/livetv-chat/${channelId}/messages`)
}

export async function sendLiveTvChatMessage(channelId: string | number, message: string) {
  return api.post<{ message: LiveTvChatMessage }>(`/livetv-chat/${channelId}/messages`, { message })
}

export async function loadLiveTvSchedule(channelId: string | number, schedulesUrl?: string | null) {
  if (schedulesUrl) {
    try {
      const response = await fetch(resolveScheduleUrl(schedulesUrl), {
        headers: { Accept: 'application/json' },
      })

      if (response.ok) {
        const payload = await response.json()
        const externalSchedules = Array.isArray(payload?.schedules) ? payload.schedules : []

        if (externalSchedules.length > 0) {
          return externalSchedules.map((item: any, index: number) => ({
            id: item.id ?? index,
            title: item.media?.title ?? item.title ?? null,
            start_at: item.start_at ?? item.start_time ?? null,
            end_at: item.end_at ?? item.end_time ?? null,
            meta: item.media ?? item.meta ?? null,
          })) as LiveTvScheduleItem[]
        }
      }
    } catch {
      // Fall back to the internal schedule endpoint below.
    }
  }

  const params = new URLSearchParams({ channel_id: String(channelId) })
  const response = await api.get<ApiEnvelope<LiveTvScheduleItem[]>>(`/api/channel-schedules?${params.toString()}`)

  return response.data ?? []
}

export async function loadLiveTvGuide(channels: MediaItem[]) {
  const results = await Promise.allSettled(
    channels.map((channel) => loadLiveTvGuideChannel(channel)),
  )

  return results
    .filter((result): result is PromiseFulfilledResult<LiveTvGuideChannel> => result.status === 'fulfilled')
    .map((result) => result.value)
}

export async function loadLiveTvGuideChannel(channel: MediaItem) {
  const detail = await loadLiveTvDetail(channel.id)
  const schedule = Array.isArray(detail?.full_schedule) && detail.full_schedule.length > 0
    ? detail.full_schedule
    : await loadLiveTvSchedule(channel.id, detail?.schedules_url)

  return {
    channel: { ...channel, ...detail },
    schedule,
  } satisfies LiveTvGuideChannel
}

function resolveScheduleUrl(value: string) {
  if (/^https?:\/\//i.test(value)) {
    return value
  }

  return `https://stream.ezway.tv/api/public/schedules/${encodeURIComponent(value)}`
}
