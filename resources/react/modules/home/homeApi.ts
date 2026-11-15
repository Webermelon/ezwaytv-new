import type { QueryClient } from '@tanstack/react-query'

import { appConfigurationQuery, type AppConfiguration } from '@/lib/app-configuration'
import { api } from '@/lib/api'
import type { ApiEnvelope, DashboardData, LiveTvDashboard, MediaItem, PaginatedData } from './types'

const DEFAULT_HOME_RAIL_LIMIT = 15

export async function loadHomeModule(queryClient: QueryClient) {
  const catalog = Promise.allSettled([
    api.get<ApiEnvelope<DashboardData>>('/api/v3/dashboard-detail'),
    api.get<ApiEnvelope<MediaItem[]>>('/api/genre-list'),
    api.get<ApiEnvelope<LiveTvDashboard>>('/api/v3/livetv-dashboard'),
  ])
  const appConfig = await queryClient.fetchQuery(appConfigurationQuery).catch(() => null)
  const railLimit = normalizeRailLimit(appConfig?.homepage_rail_item_limit)
  const [[dashboard, genres, liveTv], [videos, ondemand]] = await Promise.all([
    catalog,
    Promise.allSettled([
      api.get<ApiEnvelope<MediaItem[]>>(`/api/v3/video-list?is_ajax=1&per_page=${railLimit}`),
      api.get<ApiEnvelope<PaginatedData<MediaItem>>>(`/api/v3/ondemand?per_page=${railLimit}`),
    ]),
  ])

  return {
    dashboard: {
      ...(unwrap(dashboard)?.data ?? {}),
      homepage_rail_item_limit: railLimit,
    },
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

function normalizeRailLimit(value: AppConfiguration['homepage_rail_item_limit']) {
  const limit = Number(value)

  if (!Number.isFinite(limit) || limit < 1) {
    return DEFAULT_HOME_RAIL_LIMIT
  }

  return Math.floor(limit)
}
