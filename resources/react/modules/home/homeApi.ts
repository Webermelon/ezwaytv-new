import { api } from '@/lib/api'
import type { ApiEnvelope, DashboardData, LiveTvDashboard, MediaItem, PaginatedData } from './types'

export async function loadHomeModule() {
  const [dashboard, genres, videos, liveTv, ondemand] = await Promise.allSettled([
    api.get<ApiEnvelope<DashboardData>>('/api/v3/dashboard-detail'),
    api.get<ApiEnvelope<MediaItem[]>>('/api/genre-list'),
    api.get<ApiEnvelope<MediaItem[]>>('/api/v3/video-list?is_ajax=1&per_page=14'),
    api.get<ApiEnvelope<LiveTvDashboard>>('/api/v3/livetv-dashboard'),
    api.get<ApiEnvelope<PaginatedData<MediaItem>>>('/api/v3/ondemand?per_page=12'),
  ])

  return {
    dashboard: unwrap(dashboard)?.data ?? {},
    genres: unwrap(genres)?.data ?? [],
    videos: unwrap(videos)?.data ?? [],
    liveTv: unwrap(liveTv)?.data ?? {},
    ondemandChannels: unwrap(ondemand)?.data?.data ?? [],
  }
}

function unwrap<T>(result: PromiseSettledResult<T>) {
  if (result.status === 'fulfilled') {
    return result.value
  }

  return null
}
